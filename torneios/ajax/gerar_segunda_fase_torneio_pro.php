<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

if (!podeGerenciarTorneio($pdo, $torneio_id, $_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Criar grupos Ouro A e Ouro B com os 1º lugares das chaves especificadas
try {
    // Debug: Verificar chaves existentes antes de iniciar transação
    $sql_debug_chaves = "SELECT id, nome, ordem FROM torneio_grupos WHERE torneio_id = ? ORDER BY ordem";
    $stmt_debug_chaves = executeQuery($pdo, $sql_debug_chaves, [$torneio_id]);
    $chaves_existentes = $stmt_debug_chaves ? $stmt_debug_chaves->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Verificar se há classificação
    $sql_debug_class = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ?";
    $stmt_debug_class = executeQuery($pdo, $sql_debug_class, [$torneio_id]);
    $total_classificacao = $stmt_debug_class ? (int)$stmt_debug_class->fetch()['total'] : 0;
    
    // Verificar classificação por grupo
    if (!empty($chaves_existentes)) {
        foreach ($chaves_existentes as $chave) {
            $sql_class_chave = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id = ?";
            $stmt_class_chave = executeQuery($pdo, $sql_class_chave, [$torneio_id, $chave['id']]);
            $total_chave = $stmt_class_chave ? (int)$stmt_class_chave->fetch()['total'] : 0;
        }
    }
    
    $pdo->beginTransaction();
    executeQuery($pdo, "SELECT id FROM torneios WHERE id = ? FOR UPDATE", [$torneio_id]);

    // Verificar e remover grupos existentes da 2ª fase antes de criar novos.
    $sql_check_existentes = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND (nome = ? OR nome = ? OR nome = ? OR nome = ? OR nome = ? OR nome = ?)";
    $stmt_check = executeQuery($pdo, $sql_check_existentes, [$torneio_id, "2ª Fase - Ouro A", "2ª Fase - Ouro B", "2ª Fase - Prata A", "2ª Fase - Prata B", "2ª Fase - Bronze A", "2ª Fase - Bronze B"]);
    $grupos_existentes_ids = $stmt_check ? $stmt_check->fetchAll(PDO::FETCH_COLUMN) : [];

    if (!empty($grupos_existentes_ids)) {
        $placeholders = implode(',', array_fill(0, count($grupos_existentes_ids), '?'));
        executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id IN ($placeholders)", $grupos_existentes_ids);
        executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id IN ($placeholders)", $grupos_existentes_ids);
        executeQuery($pdo, "DELETE FROM torneio_grupos WHERE id IN ($placeholders)", $grupos_existentes_ids);
    }

    
    // Função para converter número em letra (1 -> A, 2 -> B, etc.)
    $numeroParaLetra = function($numero) {
        return chr(64 + $numero); // 65 = 'A', 66 = 'B', etc.
    };
    
    // Grupos para Ouro A: A, C, E
    // Grupos para Ouro B: B, D, F
    $grupos_ouro_a = [1, 3, 5]; // Grupo A, C, E
    $grupos_ouro_b = [2, 4, 6]; // Grupo B, D, F
    
    // DEBUG: Verificar estrutura da tabela torneio_classificacao
    try {
        $sql_estrutura = "SHOW COLUMNS FROM torneio_classificacao";
        $stmt_estrutura = $pdo->query($sql_estrutura);
        $colunas = $stmt_estrutura ? $stmt_estrutura->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Verificar se existe grupo_id
        $tem_grupo_id = false;
        foreach ($colunas as $coluna) {
            if ($coluna['Field'] === 'grupo_id') {
                $tem_grupo_id = true;
                break;
            }
        }
        if (!$tem_grupo_id) {
        }
    } catch (Exception $e) {
    }
    
    // DEBUG: Verificar se a tabela torneio_classificacao_2fase existe
    // IMPORTANTE: Para buscar classificação da 1ª fase, SEMPRE usar torneio_classificacao, não torneio_classificacao_2fase
    // A tabela torneio_classificacao_2fase é apenas para histórico/snapshot, não para buscar classificação atual
    $sql_check_table_2fase = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
    $stmt_check_table_2fase = $pdo->query($sql_check_table_2fase);
    $tabela_2fase_existe = $stmt_check_table_2fase && $stmt_check_table_2fase->rowCount() > 0;
    
    // SEMPRE usar torneio_classificacao para buscar classificação da 1ª fase
    $tabela_2fase_existe = false; // Forçar uso da tabela principal
    
    if ($tabela_2fase_existe) {
        $sql_count_2fase = "SELECT COUNT(*) as total FROM torneio_classificacao_2fase WHERE torneio_id = ?";
        $stmt_count_2fase = executeQuery($pdo, $sql_count_2fase, [$torneio_id]);
        $total_class_2fase = $stmt_count_2fase ? (int)$stmt_count_2fase->fetch()['total'] : 0;
    } else {
    }
    
    // Função auxiliar para calcular classificação completa de um grupo diretamente dos jogos
    $calcular_classificacao_grupo = function($grupo_id, $torneio_id) use ($pdo) {
        // Buscar todos os times do grupo
        $sql_times_grupo = "SELECT tt.id as time_id, tt.nome as time_nome, tt.cor as time_cor
                           FROM torneio_times tt
                           JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                           WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
        $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo_id, $torneio_id]);
        $times_grupo = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (empty($times_grupo)) {
            return [];
        }
        
        // Calcular classificação de cada time
        $classificacao = [];
        foreach ($times_grupo as $time_grupo) {
            $time_id = (int)$time_grupo['time_id'];
            
            // Buscar jogos finalizados
            $sql_jogos_time = "SELECT pontos_time1, pontos_time2, time1_id, time2_id
                               FROM torneio_partidas
                               WHERE grupo_id = ? 
                                   AND status = 'Finalizada'
                                   AND (time1_id = ? OR time2_id = ?)";
            
            $stmt_jogos_time = executeQuery($pdo, $sql_jogos_time, [$grupo_id, $time_id, $time_id]);
            $jogos_time = $stmt_jogos_time ? $stmt_jogos_time->fetchAll(PDO::FETCH_ASSOC) : [];
            
            if (count($jogos_time) > 0) {
                // Calcular estatísticas
                $vitorias = 0;
                $derrotas = 0;
                $empates = 0;
                $pontos_pro = 0;
                $pontos_contra = 0;
                
                foreach ($jogos_time as $jogo) {
                    $pontos1 = (int)$jogo['pontos_time1'];
                    $pontos2 = (int)$jogo['pontos_time2'];
                    
                    if ($jogo['time1_id'] == $time_id) {
                        $pontos_pro += $pontos1;
                        $pontos_contra += $pontos2;
                        if ($pontos1 > $pontos2) $vitorias++;
                        elseif ($pontos1 < $pontos2) $derrotas++;
                        else $empates++;
                    } else {
                        $pontos_pro += $pontos2;
                        $pontos_contra += $pontos1;
                        if ($pontos2 > $pontos1) $vitorias++;
                        elseif ($pontos2 < $pontos1) $derrotas++;
                        else $empates++;
                    }
                }
                
                $saldo = $pontos_pro - $pontos_contra;
                $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
                $pontos_total = ($vitorias * 3) + ($empates * 1);
                
                $classificacao[] = [
                    'time_id' => $time_id,
                    'time_nome' => $time_grupo['time_nome'],
                    'time_cor' => $time_grupo['time_cor'],
                    'vitorias' => $vitorias,
                    'derrotas' => $derrotas,
                    'empates' => $empates,
                    'pontos_pro' => $pontos_pro,
                    'pontos_contra' => $pontos_contra,
                    'saldo_pontos' => $saldo,
                    'average' => $average,
                    'pontos_total' => $pontos_total
                ];
            }
        }
        
        // Ordenar
        usort($classificacao, function($a, $b) {
            if ($a['pontos_total'] != $b['pontos_total']) return $b['pontos_total'] - $a['pontos_total'];
            if ($a['vitorias'] != $b['vitorias']) return $b['vitorias'] - $a['vitorias'];
            if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];
            return $b['saldo_pontos'] - $a['saldo_pontos'];
        });
        
        return $classificacao;
    };
    
    // Função para buscar 1º lugar de um grupo (usando nova tabela se disponível)
    $buscar_1lugar_grupo = function($numero_grupo) use ($pdo, $torneio_id, $tabela_2fase_existe, $numeroParaLetra, $calcular_classificacao_grupo) {
        $letra_grupo = $numeroParaLetra($numero_grupo);
        $nome_grupo = "Grupo $letra_grupo";
        
        // SEMPRE buscar da tabela torneio_classificacao (dados da 1ª fase estão aqui)
        $sql_grupo = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
        $stmt_grupo = executeQuery($pdo, $sql_grupo, [$torneio_id, $nome_grupo]);
        
        if ($stmt_grupo === false || !$stmt_grupo) {
            return null;
        }
        
        $grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            return null;
        }
        
        $grupo_id = (int)$grupo['id'];
        
        // DEBUG: Verificar quantos registros existem na tabela para este grupo
        $sql_debug_count = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id = ?";
        $stmt_debug_count = executeQuery($pdo, $sql_debug_count, [$torneio_id, $grupo_id]);
        $total_registros = $stmt_debug_count ? (int)$stmt_debug_count->fetch()['total'] : 0;
        
        // DEBUG: Listar todos os registros deste grupo
        $sql_debug_all = "SELECT tc.time_id, tc.grupo_id, tc.pontos_total, tc.vitorias, tc.average, tt.nome as time_nome
                         FROM torneio_classificacao tc
                         JOIN torneio_times tt ON tt.id = tc.time_id
                         WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC";
        $stmt_debug_all = executeQuery($pdo, $sql_debug_all, [$torneio_id, $grupo_id]);
        $todos_registros = $stmt_debug_all ? $stmt_debug_all->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $sql_1lugar = "SELECT 
            tc.time_id,
            tc.grupo_id,
            tc.vitorias,
            tc.derrotas,
            tc.empates,
            tc.pontos_pro,
            tc.pontos_contra,
            tc.saldo_pontos,
            tc.average,
            tc.pontos_total,
            tt.nome AS time_nome,
            tt.cor AS time_cor,
            tg.nome AS grupo_nome
        FROM torneio_classificacao tc
        JOIN torneio_times tt ON tt.id = tc.time_id
        LEFT JOIN torneio_grupos tg ON tg.id = tc.grupo_id
        WHERE tc.torneio_id = ?
          AND tc.grupo_id = ?
        ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
        LIMIT 1";
        
        $stmt_1lugar = executeQuery($pdo, $sql_1lugar, [$torneio_id, $grupo_id]);
        
        if ($stmt_1lugar === false || !$stmt_1lugar) {
            $error_info = $pdo->errorInfo();
            if ($error_info) {
            }
            return null;
        }
        
        $primeiro = $stmt_1lugar->fetch(PDO::FETCH_ASSOC);
        
        // DEBUG: Verificar se encontrou resultado
        if ($primeiro) {
        } else {
        }
        
        if (!$primeiro) {
            
            // Se não encontrou na tabela, calcular diretamente dos jogos
            $letra_grupo = $numeroParaLetra($numero_grupo);
            $nome_grupo = "Grupo $letra_grupo";
            
            // Buscar grupo
            $sql_grupo_calc = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
            $stmt_grupo_calc = executeQuery($pdo, $sql_grupo_calc, [$torneio_id, $nome_grupo]);
            $grupo_calc = $stmt_grupo_calc ? $stmt_grupo_calc->fetch(PDO::FETCH_ASSOC) : null;
            
            if (!$grupo_calc) {
                return null;
            }
            
            $grupo_id_calc = (int)$grupo_calc['id'];
            
            // Calcular classificação completa
            $classificacao_calculada = $calcular_classificacao_grupo($grupo_id_calc, $torneio_id);
            
            if (empty($classificacao_calculada)) {
                return null;
            }
            
            // Pegar o primeiro
            $primeiro = [
                'time_id' => $classificacao_calculada[0]['time_id'],
                'time_nome' => $classificacao_calculada[0]['time_nome'],
                'time_cor' => $classificacao_calculada[0]['time_cor'],
                'grupo_id' => $grupo_id_calc,
                'grupo_nome' => $nome_grupo,
                'vitorias' => $classificacao_calculada[0]['vitorias'],
                'derrotas' => $classificacao_calculada[0]['derrotas'],
                'empates' => $classificacao_calculada[0]['empates'],
                'pontos_pro' => $classificacao_calculada[0]['pontos_pro'],
                'pontos_contra' => $classificacao_calculada[0]['pontos_contra'],
                'saldo_pontos' => $classificacao_calculada[0]['saldo_pontos'],
                'average' => $classificacao_calculada[0]['average'],
                'pontos_total' => $classificacao_calculada[0]['pontos_total']
            ];
            
        } else {
        }
        
        return [
            'time_id' => (int)$primeiro['time_id'],
            'time_nome' => $primeiro['time_nome'],
            'grupo_nome' => $primeiro['grupo_nome'] ?? $nome_grupo,
            'grupo_id' => (int)$primeiro['grupo_id'],
            'grupo_numero' => $numero_grupo,
            'pontos_total' => (int)$primeiro['pontos_total'],
            'vitorias' => (int)$primeiro['vitorias'],
            'derrotas' => (int)($primeiro['derrotas'] ?? 0),
            'empates' => (int)($primeiro['empates'] ?? 0),
            'pontos_pro' => (int)($primeiro['pontos_pro'] ?? 0),
            'pontos_contra' => (int)($primeiro['pontos_contra'] ?? 0),
            'average' => (float)$primeiro['average'],
            'saldo_pontos' => (int)$primeiro['saldo_pontos']
        ];
    };
    
    // Função para buscar 4º lugar de um grupo (usando nova tabela se disponível)
    $buscar_4lugar_grupo = function($numero_grupo) use ($pdo, $torneio_id, $tabela_2fase_existe, $numeroParaLetra) {
        $letra_grupo = $numeroParaLetra($numero_grupo);
        $nome_grupo = "Grupo $letra_grupo";
        
        // SEMPRE buscar da tabela torneio_classificacao (dados da 1ª fase estão aqui)
        $sql_grupo = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
        $stmt_grupo = executeQuery($pdo, $sql_grupo, [$torneio_id, $nome_grupo]);
        
        if ($stmt_grupo === false || !$stmt_grupo) {
            return null;
        }
        
        $grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            return null;
        }
        
        $grupo_id = (int)$grupo['id'];
        
        $sql_4lugar = "SELECT 
            tc.time_id,
            tc.grupo_id,
            tc.vitorias,
            tc.derrotas,
            tc.empates,
            tc.pontos_pro,
            tc.pontos_contra,
            tc.saldo_pontos,
            tc.average,
            tc.pontos_total,
            tt.nome AS time_nome,
            tt.cor AS time_cor,
            tg.nome AS grupo_nome
        FROM torneio_classificacao tc
        JOIN torneio_times tt ON tt.id = tc.time_id
        LEFT JOIN torneio_grupos tg ON tg.id = tc.grupo_id
        WHERE tc.torneio_id = ?
          AND tc.grupo_id = ?
        ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
        LIMIT 1 OFFSET 3";
        
        $stmt_4lugar = executeQuery($pdo, $sql_4lugar, [$torneio_id, $grupo_id]);
        
        if ($stmt_4lugar === false || !$stmt_4lugar) {
            return null;
        }
        
        $quarto = $stmt_4lugar->fetch(PDO::FETCH_ASSOC);
        
        if (!$quarto) {
            return null;
        }
        
        return [
            'time_id' => (int)$quarto['time_id'],
            'time_nome' => $quarto['time_nome'],
            'grupo_nome' => $quarto['grupo_nome'] ?? $nome_grupo,
            'grupo_id' => (int)$quarto['grupo_id'],
            'grupo_numero' => $numero_grupo,
            'pontos_total' => (int)$quarto['pontos_total'],
            'vitorias' => (int)$quarto['vitorias'],
            'derrotas' => (int)($quarto['derrotas'] ?? 0),
            'empates' => (int)($quarto['empates'] ?? 0),
            'pontos_pro' => (int)($quarto['pontos_pro'] ?? 0),
            'pontos_contra' => (int)($quarto['pontos_contra'] ?? 0),
            'average' => (float)$quarto['average'],
            'saldo_pontos' => (int)$quarto['saldo_pontos']
        ];
    };
    
    // Função para buscar 3º lugar de um grupo (usando nova tabela se disponível)
    $buscar_3lugar_grupo = function($numero_grupo) use ($pdo, $torneio_id, $tabela_2fase_existe, $numeroParaLetra) {
        $letra_grupo = $numeroParaLetra($numero_grupo);
        $nome_grupo = "Grupo $letra_grupo";
        
        // SEMPRE buscar da tabela torneio_classificacao (dados da 1ª fase estão aqui)
        $sql_grupo = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
        $stmt_grupo = executeQuery($pdo, $sql_grupo, [$torneio_id, $nome_grupo]);
        
        if ($stmt_grupo === false || !$stmt_grupo) {
            return null;
        }
        
        $grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            return null;
        }
        
        $grupo_id = (int)$grupo['id'];
        
        $sql_3lugar = "SELECT 
            tc.time_id,
            tc.grupo_id,
            tc.vitorias,
            tc.derrotas,
            tc.empates,
            tc.pontos_pro,
            tc.pontos_contra,
            tc.saldo_pontos,
            tc.average,
            tc.pontos_total,
            tt.nome AS time_nome,
            tt.cor AS time_cor,
            tg.nome AS grupo_nome
        FROM torneio_classificacao tc
        JOIN torneio_times tt ON tt.id = tc.time_id
        LEFT JOIN torneio_grupos tg ON tg.id = tc.grupo_id
        WHERE tc.torneio_id = ?
          AND tc.grupo_id = ?
        ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
        LIMIT 1 OFFSET 2";
        
        $stmt_3lugar = executeQuery($pdo, $sql_3lugar, [$torneio_id, $grupo_id]);
        
        if ($stmt_3lugar === false || !$stmt_3lugar) {
            return null;
        }
        
        $terceiro = $stmt_3lugar->fetch(PDO::FETCH_ASSOC);
        
        if (!$terceiro) {
            return null;
        }
        
        return [
            'time_id' => (int)$terceiro['time_id'],
            'time_nome' => $terceiro['time_nome'],
            'grupo_nome' => $terceiro['grupo_nome'] ?? $nome_grupo,
            'grupo_id' => (int)$terceiro['grupo_id'],
            'grupo_numero' => $numero_grupo,
            'pontos_total' => (int)$terceiro['pontos_total'],
            'vitorias' => (int)$terceiro['vitorias'],
            'derrotas' => (int)($terceiro['derrotas'] ?? 0),
            'empates' => (int)($terceiro['empates'] ?? 0),
            'pontos_pro' => (int)($terceiro['pontos_pro'] ?? 0),
            'pontos_contra' => (int)($terceiro['pontos_contra'] ?? 0),
            'average' => (float)$terceiro['average'],
            'saldo_pontos' => (int)$terceiro['saldo_pontos']
        ];
    };
    
    // Função para buscar 2º lugar de um grupo (usando nova tabela se disponível)
    $buscar_2lugar_grupo = function($numero_grupo) use ($pdo, $torneio_id, $tabela_2fase_existe, $numeroParaLetra) {
        $letra_grupo = $numeroParaLetra($numero_grupo);
        $nome_grupo = "Grupo $letra_grupo";
        
        // SEMPRE buscar da tabela torneio_classificacao (dados da 1ª fase estão aqui)
        $sql_grupo = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
        $stmt_grupo = executeQuery($pdo, $sql_grupo, [$torneio_id, $nome_grupo]);
        
        if ($stmt_grupo === false || !$stmt_grupo) {
            return null;
        }
        
        $grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            return null;
        }
        
        $grupo_id = (int)$grupo['id'];
        
        $sql_2lugar = "SELECT 
            tc.time_id,
            tc.grupo_id,
            tc.vitorias,
            tc.derrotas,
            tc.empates,
            tc.pontos_pro,
            tc.pontos_contra,
            tc.saldo_pontos,
            tc.average,
            tc.pontos_total,
            tt.nome AS time_nome,
            tt.cor AS time_cor,
            tg.nome AS grupo_nome
        FROM torneio_classificacao tc
        JOIN torneio_times tt ON tt.id = tc.time_id
        LEFT JOIN torneio_grupos tg ON tg.id = tc.grupo_id
        WHERE tc.torneio_id = ?
          AND tc.grupo_id = ?
        ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
        LIMIT 1 OFFSET 1";
        
        $stmt_2lugar = executeQuery($pdo, $sql_2lugar, [$torneio_id, $grupo_id]);
        
        if ($stmt_2lugar === false || !$stmt_2lugar) {
            return null;
        }
        
        $segundo = $stmt_2lugar->fetch(PDO::FETCH_ASSOC);
        
        if (!$segundo) {
            return null;
        }
        
        return [
            'time_id' => (int)$segundo['time_id'],
            'time_nome' => $segundo['time_nome'],
            'grupo_nome' => $segundo['grupo_nome'] ?? $nome_grupo,
            'grupo_id' => (int)$segundo['grupo_id'],
            'grupo_numero' => $numero_grupo,
            'pontos_total' => (int)$segundo['pontos_total'],
            'vitorias' => (int)$segundo['vitorias'],
            'derrotas' => (int)($segundo['derrotas'] ?? 0),
            'empates' => (int)($segundo['empates'] ?? 0),
            'pontos_pro' => (int)($segundo['pontos_pro'] ?? 0),
            'pontos_contra' => (int)($segundo['pontos_contra'] ?? 0),
            'average' => (float)$segundo['average'],
            'saldo_pontos' => (int)$segundo['saldo_pontos']
        ];
    };
    
    // Buscar 1º lugares para Ouro A
    $times_ouro_a = [];
    foreach ($grupos_ouro_a as $num_grupo) {
        $time = $buscar_1lugar_grupo($num_grupo);
        if ($time) {
            $times_ouro_a[] = $time;
        } else {
        }
    }
    
    // Buscar 1º lugares para Ouro B
    $times_ouro_b = [];
    foreach ($grupos_ouro_b as $num_grupo) {
        $time = $buscar_1lugar_grupo($num_grupo);
        if ($time) {
            $times_ouro_b[] = $time;
        } else {
        }
    }
    
    // Buscar todos os 2º lugares de todos os grupos (A a F)
    $todos_segundos_lugares = [];
    for ($num_grupo = 1; $num_grupo <= 6; $num_grupo++) {
        $segundo = $buscar_2lugar_grupo($num_grupo);
        if ($segundo) {
            $todos_segundos_lugares[] = $segundo;
        }
    }
    
    // Ordenar 2º lugares por pontos_total, depois vitorias, depois average, depois saldo
    usort($todos_segundos_lugares, function($a, $b) {
        if ($b['pontos_total'] != $a['pontos_total']) {
            return $b['pontos_total'] - $a['pontos_total'];
        }
        if ($b['vitorias'] != $a['vitorias']) {
            return $b['vitorias'] - $a['vitorias'];
        }
        if ($b['average'] != $a['average']) {
            return $b['average'] > $a['average'] ? 1 : -1;
        }
        return $b['saldo_pontos'] - $a['saldo_pontos'];
    });
    
    // Buscar todos os 3º lugares de todos os grupos (A a F)
    $todos_terceiros_lugares = [];
    for ($num_grupo = 1; $num_grupo <= 6; $num_grupo++) {
        $terceiro = $buscar_3lugar_grupo($num_grupo);
        if ($terceiro) {
            $todos_terceiros_lugares[] = $terceiro;
        }
    }
    
    // Ordenar 3º lugares por pontos_total, depois vitorias, depois average, depois saldo
    usort($todos_terceiros_lugares, function($a, $b) {
        if ($b['pontos_total'] != $a['pontos_total']) {
            return $b['pontos_total'] - $a['pontos_total'];
        }
        if ($b['vitorias'] != $a['vitorias']) {
            return $b['vitorias'] - $a['vitorias'];
        }
        if ($b['average'] != $a['average']) {
            return $b['average'] > $a['average'] ? 1 : -1;
        }
        return $b['saldo_pontos'] - $a['saldo_pontos'];
    });
    
    // Adicionar melhor 2º lugar ao Ouro A
    if (!empty($todos_segundos_lugares)) {
        $times_ouro_a[] = $todos_segundos_lugares[0];
    }
    
    // Adicionar 2º melhor 2º lugar ao Ouro B
    if (count($todos_segundos_lugares) >= 2) {
        $times_ouro_b[] = $todos_segundos_lugares[1];
    }
    
    // Criar grupos Prata A e Prata B
    // PRATA A: 3° melhor 2° lugar, 5° melhor 2° lugar, 1° melhor 3° lugar, 3° melhor 3° lugar
    $times_prata_a = [];
    
    if (count($todos_segundos_lugares) >= 3) {
        $times_prata_a[] = $todos_segundos_lugares[2]; // 3° melhor 2° lugar
    } else {
    }
    if (count($todos_segundos_lugares) >= 5) {
        $times_prata_a[] = $todos_segundos_lugares[4]; // 5° melhor 2° lugar
    } else {
    }
    if (!empty($todos_terceiros_lugares)) {
        $times_prata_a[] = $todos_terceiros_lugares[0]; // 1° melhor 3° lugar
    } else {
    }
    if (count($todos_terceiros_lugares) >= 3) {
        $times_prata_a[] = $todos_terceiros_lugares[2]; // 3° melhor 3° lugar
    } else {
    }
    
    
    // PRATA B: 4° melhor 2° lugar, 6° melhor 2° lugar, 2° melhor 3° lugar, 4° melhor 3° lugar
    $times_prata_b = [];
    
    if (count($todos_segundos_lugares) >= 4) {
        $times_prata_b[] = $todos_segundos_lugares[3]; // 4° melhor 2° lugar
    } else {
    }
    if (count($todos_segundos_lugares) >= 6) {
        $times_prata_b[] = $todos_segundos_lugares[5]; // 6° melhor 2° lugar
    } else {
    }
    if (count($todos_terceiros_lugares) >= 2) {
        $times_prata_b[] = $todos_terceiros_lugares[1]; // 2° melhor 3° lugar
    } else {
    }
    if (count($todos_terceiros_lugares) >= 4) {
        $times_prata_b[] = $todos_terceiros_lugares[3]; // 4° melhor 3° lugar
    } else {
    }
    
    
    // Criar grupos Bronze A e Bronze B
    // BRONZE A: 5° melhor 3° lugar + 4º lugar da chave 1, 4º lugar da chave 3, 4º lugar da chave 5
    $times_bronze_a = [];
    
    if (count($todos_terceiros_lugares) >= 5) {
        $times_bronze_a[] = $todos_terceiros_lugares[4]; // 5° melhor 3° lugar
    } else {
    }
    
    // Adicionar 4º lugares dos grupos ímpares (A, C, E)
    $grupos_bronze_a = [1, 3, 5];
    foreach ($grupos_bronze_a as $num_grupo) {
        $quarto = $buscar_4lugar_grupo($num_grupo);
        if ($quarto) {
            $times_bronze_a[] = $quarto;
            $letra_grupo = $numeroParaLetra($num_grupo);
        } else {
            $letra_grupo = $numeroParaLetra($num_grupo);
        }
    }
    
    
    // BRONZE B: 6° melhor 3° lugar + 4º lugar do grupo B, 4º lugar do grupo D, 4º lugar do grupo F
    $times_bronze_b = [];
    
    if (count($todos_terceiros_lugares) >= 6) {
        $times_bronze_b[] = $todos_terceiros_lugares[5]; // 6° melhor 3° lugar
    } else {
    }
    
    // Adicionar 4º lugares dos grupos pares (B, D, F)
    $grupos_bronze_b = [2, 4, 6];
    foreach ($grupos_bronze_b as $num_grupo) {
        $quarto = $buscar_4lugar_grupo($num_grupo);
        if ($quarto) {
            $times_bronze_b[] = $quarto;
            $letra_grupo = $numeroParaLetra($num_grupo);
        } else {
            $letra_grupo = $numeroParaLetra($num_grupo);
        }
    }
    
    
    // Verificar se encontrou times suficientes
    if (empty($times_ouro_a) && empty($times_ouro_b) && empty($times_prata_a) && empty($times_prata_b) && empty($times_bronze_a) && empty($times_bronze_b)) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Verificar se há classificação nas chaves
        $sql_check_classificacao = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ?";
        $stmt_check_class = executeQuery($pdo, $sql_check_classificacao, [$torneio_id]);
        $total_classificacao = $stmt_check_class ? (int)$stmt_check_class->fetch()['total'] : 0;
        
        // Verificar quais chaves existem
        $sql_grupos_existentes = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE 'Grupo%' ORDER BY ordem";
        $stmt_grupos = executeQuery($pdo, $sql_grupos_existentes, [$torneio_id]);
        $grupos_existentes_info = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_ASSOC) : [];
        $grupos_existentes = array_column($grupos_existentes_info, 'nome');
        
        // Debug detalhado: verificar classificação por grupo
        $debug_classificacao = [];
        foreach ($grupos_existentes_info as $grupo_info) {
            $grupo_id = (int)$grupo_info['id'];
            $grupo_nome = $grupo_info['nome'];
            
            // Verificar classificação com grupo_id
            $sql_class_grupo = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id = ?";
            $stmt_class_grupo = executeQuery($pdo, $sql_class_grupo, [$torneio_id, $grupo_id]);
            $total_grupo = $stmt_class_grupo ? (int)$stmt_class_grupo->fetch()['total'] : 0;
            
            // Verificar se há jogos finalizados no grupo
            $sql_jogos_grupo = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ? AND grupo_id = ? AND status = 'Finalizada'";
            $stmt_jogos_grupo = executeQuery($pdo, $sql_jogos_grupo, [$torneio_id, $grupo_id]);
            $total_jogos = $stmt_jogos_grupo ? (int)$stmt_jogos_grupo->fetch()['total'] : 0;
            
            $debug_classificacao[] = [
                'grupo' => $grupo_nome,
                'grupo_id' => $grupo_id,
                'classificacao_registrada' => $total_grupo,
                'jogos_finalizados' => $total_jogos
            ];
            
        }
        
        $mensagem_erro = 'Nenhum time encontrado nos grupos especificados.';
        if ($total_classificacao == 0) {
            $mensagem_erro = 'Não há classificação registrada na 1ª fase. Certifique-se de que todas as partidas foram finalizadas e a classificação foi atualizada.';
        } elseif (empty($grupos_existentes)) {
            $mensagem_erro = 'Nenhum grupo encontrado no torneio. Verifique se os grupos da 1ª fase foram criados corretamente.';
        } else {
            // Garantir que $grupos_existentes é um array simples
            $grupos_lista = is_array($grupos_existentes) ? $grupos_existentes : [];
            $mensagem_erro = 'Não foi possível encontrar times classificados nos grupos. Verifique se há classificação registrada para os grupos: ' . implode(', ', $grupos_lista);
        }
        
        echo json_encode([
            'success' => false,
            'message' => $mensagem_erro
        ]);
        exit();
    }
    
    // Verificar se a coluna grupo_id existe na tabela torneio_classificacao
    $columnsQuery_class = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
    $tem_grupo_id_classificacao = $columnsQuery_class && $columnsQuery_class->rowCount() > 0;
    
    // Criar grupos Ouro A, Ouro B, Prata A e Prata B
    $grupos_criados = [];
    
    // Criar Ouro A
    if (!empty($times_ouro_a)) {
        try {
            // Verificar se já existe antes de inserir
            $sql_check_ouro_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_ouro_a = executeQuery($pdo, $sql_check_ouro_a, [$torneio_id, "2ª Fase - Ouro A"]);
            $grupo_ouro_a_existente = $stmt_check_ouro_a ? $stmt_check_ouro_a->fetch() : null;
            
            if ($grupo_ouro_a_existente) {
                $grupo_ouro_a_id = (int)$grupo_ouro_a_existente['id'];
                // Limpar times e classificações do grupo existente
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_ouro_a_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_ouro_a_id]);
            } else {
                $sql_grupo_ouro_a = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_ouro_a = $pdo->prepare($sql_grupo_ouro_a);
                $result_ouro_a = $stmt_grupo_ouro_a->execute([$torneio_id, "2ª Fase - Ouro A", 100]);
                
                if (!$result_ouro_a) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Ouro A agora.'
                    ]);
                    exit();
                }
                
                $grupo_ouro_a_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Ouro A'] = ['id' => $grupo_ouro_a_id, 'times' => $times_ouro_a];
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Ouro A agora.'
            ]);
            exit();
        }
        
        // Adicionar times ao grupo Ouro A
        foreach ($times_ouro_a as $time) {
            $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
            executeQuery($pdo, $sql_add_time, [$grupo_ouro_a_id, $time['time_id']]);
            
            // Criar classificação inicial para a 2ª fase (apenas se não existir para este grupo)
            // IMPORTANTE: Não usar ON DUPLICATE KEY UPDATE para não sobrescrever a classificação geral
            if ($tem_grupo_id_classificacao) {
                // Verificar se já existe classificação para este time e grupo específico
                $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_ouro_a_id]);
                $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                
                if (!$existe_class) {
                    // Verificar se existe classificação geral (pode ter grupo_id NULL ou de outro grupo)
                    $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? LIMIT 1";
                    $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                    $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                    
                    if ($class_geral) {
                        // Se existe classificação geral com grupo_id diferente, criar nova entrada para este grupo
                        // Preservando os dados da classificação geral
                        if ($class_geral['grupo_id'] != $grupo_ouro_a_id) {
                            try {
                                $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                               SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                               FROM torneio_classificacao
                                               WHERE torneio_id = ? AND time_id = ? AND id = ?
                                               LIMIT 1";
                                executeQuery($pdo, $sql_duplicar, [$grupo_ouro_a_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                            } catch (PDOException $e) {
                                // Se falhar por causa da chave única, apenas atualizar o grupo_id mas preservar os dados
                                // Isso só acontece se a chave única não incluir grupo_id
                                error_log("Aviso: Não foi possível criar classificação duplicada para grupo $grupo_ouro_a_id: " . $e->getMessage());
                            }
                        }
                    } else {
                        // Se não existe nenhuma classificação, criar zerada
                        try {
                            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            $result_class = executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_ouro_a_id]);
                            if ($result_class) {
                            } else {
                                $error_info = $pdo->errorInfo();
                            }
                        } catch (PDOException $e) {
                            // Se falhar, a classificação já existe (pode ter sido criada em outro lugar)
                            error_log("Aviso: Classificação já existe para time {$time['time_id']} no grupo $grupo_ouro_a_id: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
    
    // Criar Ouro B
    if (!empty($times_ouro_b)) {
        try {
            // Verificar se já existe antes de inserir
            $sql_check_ouro_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_ouro_b = executeQuery($pdo, $sql_check_ouro_b, [$torneio_id, "2ª Fase - Ouro B"]);
            $grupo_ouro_b_existente = $stmt_check_ouro_b ? $stmt_check_ouro_b->fetch() : null;
            
            if ($grupo_ouro_b_existente) {
                $grupo_ouro_b_id = (int)$grupo_ouro_b_existente['id'];
                // Limpar times e classificações do grupo existente
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_ouro_b_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_ouro_b_id]);
            } else {
                $sql_grupo_ouro_b = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_ouro_b = $pdo->prepare($sql_grupo_ouro_b);
                $result_ouro_b = $stmt_grupo_ouro_b->execute([$torneio_id, "2ª Fase - Ouro B", 101]);
                
                if (!$result_ouro_b) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Ouro B agora.'
                    ]);
                    exit();
                }
                
                $grupo_ouro_b_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Ouro B'] = ['id' => $grupo_ouro_b_id, 'times' => $times_ouro_b];
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Ouro B agora.'
            ]);
            exit();
        }
        
        // Adicionar times ao grupo Ouro B
        foreach ($times_ouro_b as $time) {
            $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
            executeQuery($pdo, $sql_add_time, [$grupo_ouro_b_id, $time['time_id']]);
            
            // Criar classificação inicial para a 2ª fase (apenas se não existir para este grupo)
            if ($tem_grupo_id_classificacao) {
                // Verificar se já existe classificação para este time e grupo
                $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_ouro_b_id]);
                $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                
                if (!$existe_class) {
                    // Verificar se existe classificação geral (sem grupo_id ou com outro grupo_id)
                    $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
                    $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                    $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                    
                    if ($class_geral) {
                        // Se existe classificação geral, criar nova entrada para este grupo (duplicar dados)
                        if ($class_geral['grupo_id'] != $grupo_ouro_b_id) {
                            $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                           SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                           FROM torneio_classificacao
                                           WHERE torneio_id = ? AND time_id = ? AND id = ?
                                           LIMIT 1";
                            executeQuery($pdo, $sql_duplicar, [$grupo_ouro_b_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                        }
                    } else {
                        // Se não existe nenhuma classificação, criar zerada
                        $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                     VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                        executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_ouro_b_id]);
                    }
                }
            }
        }
    }
    
    // Criar Prata A
    if (!empty($times_prata_a)) {
        try {
            $sql_check_prata_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_prata_a = executeQuery($pdo, $sql_check_prata_a, [$torneio_id, "2ª Fase - Prata A"]);
            $grupo_prata_a_existente = $stmt_check_prata_a ? $stmt_check_prata_a->fetch() : null;
            
            if ($grupo_prata_a_existente) {
                $grupo_prata_a_id = (int)$grupo_prata_a_existente['id'];
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_prata_a_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_prata_a_id]);
            } else {
                $sql_grupo_prata_a = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_prata_a = $pdo->prepare($sql_grupo_prata_a);
                $result_prata_a = $stmt_grupo_prata_a->execute([$torneio_id, "2ª Fase - Prata A", 102]);
                
                if (!$result_prata_a) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Prata A agora.'
                    ]);
                    exit();
                }
                
                $grupo_prata_a_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Prata A'] = ['id' => $grupo_prata_a_id, 'times' => $times_prata_a];
            
            foreach ($times_prata_a as $time) {
                $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
                executeQuery($pdo, $sql_add_time, [$grupo_prata_a_id, $time['time_id']]);
                
                if ($tem_grupo_id_classificacao) {
                    // Verificar se já existe classificação para este time e grupo
                    $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_prata_a_id]);
                    $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                    
                    if (!$existe_class) {
                        // Verificar se existe classificação geral
                        $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
                        $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                        $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                        
                        if ($class_geral && $class_geral['grupo_id'] != $grupo_prata_a_id) {
                            // Duplicar dados da classificação geral para este grupo
                            $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                           SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                           FROM torneio_classificacao
                                           WHERE torneio_id = ? AND time_id = ? AND id = ?
                                           LIMIT 1";
                            executeQuery($pdo, $sql_duplicar, [$grupo_prata_a_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                        } else {
                            // Criar classificação zerada
                            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_prata_a_id]);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Prata A agora.'
            ]);
            exit();
        }
    } else {
    }
    
    // Criar Prata B
    if (!empty($times_prata_b)) {
        try {
            $sql_check_prata_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_prata_b = executeQuery($pdo, $sql_check_prata_b, [$torneio_id, "2ª Fase - Prata B"]);
            $grupo_prata_b_existente = $stmt_check_prata_b ? $stmt_check_prata_b->fetch() : null;
            
            if ($grupo_prata_b_existente) {
                $grupo_prata_b_id = (int)$grupo_prata_b_existente['id'];
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_prata_b_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_prata_b_id]);
            } else {
                $sql_grupo_prata_b = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_prata_b = $pdo->prepare($sql_grupo_prata_b);
                $result_prata_b = $stmt_grupo_prata_b->execute([$torneio_id, "2ª Fase - Prata B", 103]);
                
                if (!$result_prata_b) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Prata B agora.'
                    ]);
                    exit();
                }
                
                $grupo_prata_b_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Prata B'] = ['id' => $grupo_prata_b_id, 'times' => $times_prata_b];
            
            foreach ($times_prata_b as $time) {
                $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
                executeQuery($pdo, $sql_add_time, [$grupo_prata_b_id, $time['time_id']]);
                
                if ($tem_grupo_id_classificacao) {
                    // Verificar se já existe classificação para este time e grupo
                    $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_prata_b_id]);
                    $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                    
                    if (!$existe_class) {
                        // Verificar se existe classificação geral
                        $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
                        $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                        $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                        
                        if ($class_geral && $class_geral['grupo_id'] != $grupo_prata_b_id) {
                            // Duplicar dados da classificação geral para este grupo
                            $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                           SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                           FROM torneio_classificacao
                                           WHERE torneio_id = ? AND time_id = ? AND id = ?
                                           LIMIT 1";
                            executeQuery($pdo, $sql_duplicar, [$grupo_prata_b_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                        } else {
                            // Criar classificação zerada
                            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_prata_b_id]);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Prata B agora.'
            ]);
            exit();
        }
    } else {
    }
    
    // Criar Bronze A
    if (!empty($times_bronze_a)) {
        try {
            $sql_check_bronze_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_bronze_a = executeQuery($pdo, $sql_check_bronze_a, [$torneio_id, "2ª Fase - Bronze A"]);
            $grupo_bronze_a_existente = $stmt_check_bronze_a ? $stmt_check_bronze_a->fetch() : null;
            
            if ($grupo_bronze_a_existente) {
                $grupo_bronze_a_id = (int)$grupo_bronze_a_existente['id'];
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_bronze_a_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_bronze_a_id]);
            } else {
                $sql_grupo_bronze_a = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_bronze_a = $pdo->prepare($sql_grupo_bronze_a);
                $result_bronze_a = $stmt_grupo_bronze_a->execute([$torneio_id, "2ª Fase - Bronze A", 104]);
                
                if (!$result_bronze_a) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Bronze A agora.'
                    ]);
                    exit();
                }
                
                $grupo_bronze_a_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Bronze A'] = ['id' => $grupo_bronze_a_id, 'times' => $times_bronze_a];
            
            foreach ($times_bronze_a as $time) {
                $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
                executeQuery($pdo, $sql_add_time, [$grupo_bronze_a_id, $time['time_id']]);
                
                if ($tem_grupo_id_classificacao) {
                    // Verificar se já existe classificação para este time e grupo
                    $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_bronze_a_id]);
                    $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                    
                    if (!$existe_class) {
                        // Verificar se existe classificação geral
                        $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
                        $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                        $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                        
                        if ($class_geral && $class_geral['grupo_id'] != $grupo_bronze_a_id) {
                            // Duplicar dados da classificação geral para este grupo
                            $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                           SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                           FROM torneio_classificacao
                                           WHERE torneio_id = ? AND time_id = ? AND id = ?
                                           LIMIT 1";
                            executeQuery($pdo, $sql_duplicar, [$grupo_bronze_a_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                        } else {
                            // Criar classificação zerada
                            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_bronze_a_id]);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Bronze A agora.'
            ]);
            exit();
        }
    } else {
    }
    
    // Criar Bronze B
    if (!empty($times_bronze_b)) {
        try {
            $sql_check_bronze_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
            $stmt_check_bronze_b = executeQuery($pdo, $sql_check_bronze_b, [$torneio_id, "2ª Fase - Bronze B"]);
            $grupo_bronze_b_existente = $stmt_check_bronze_b ? $stmt_check_bronze_b->fetch() : null;
            
            if ($grupo_bronze_b_existente) {
                $grupo_bronze_b_id = (int)$grupo_bronze_b_existente['id'];
                executeQuery($pdo, "DELETE FROM torneio_grupo_times WHERE grupo_id = ?", [$grupo_bronze_b_id]);
                executeQuery($pdo, "DELETE FROM torneio_classificacao WHERE grupo_id = ?", [$grupo_bronze_b_id]);
            } else {
                $sql_grupo_bronze_b = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                $stmt_grupo_bronze_b = $pdo->prepare($sql_grupo_bronze_b);
                $result_bronze_b = $stmt_grupo_bronze_b->execute([$torneio_id, "2ª Fase - Bronze B", 105]);
                
                if (!$result_bronze_b) {
                    $error_info = $pdo->errorInfo();
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não foi possível criar o grupo Bronze B agora.'
                    ]);
                    exit();
                }
                
                $grupo_bronze_b_id = (int)$pdo->lastInsertId();
            }
            
            $grupos_criados['Bronze B'] = ['id' => $grupo_bronze_b_id, 'times' => $times_bronze_b];
            
            foreach ($times_bronze_b as $time) {
                $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
                executeQuery($pdo, $sql_add_time, [$grupo_bronze_b_id, $time['time_id']]);
                
                if ($tem_grupo_id_classificacao) {
                    // Verificar se já existe classificação para este time e grupo
                    $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time['time_id'], $grupo_bronze_b_id]);
                    $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
                    
                    if (!$existe_class) {
                        // Verificar se existe classificação geral
                        $sql_check_geral = "SELECT id, grupo_id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
                        $stmt_check_geral = executeQuery($pdo, $sql_check_geral, [$torneio_id, $time['time_id']]);
                        $class_geral = $stmt_check_geral ? $stmt_check_geral->fetch() : null;
                        
                        if ($class_geral && $class_geral['grupo_id'] != $grupo_bronze_b_id) {
                            // Duplicar dados da classificação geral para este grupo
                            $sql_duplicar = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                           SELECT torneio_id, time_id, ?, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total
                                           FROM torneio_classificacao
                                           WHERE torneio_id = ? AND time_id = ? AND id = ?
                                           LIMIT 1";
                            executeQuery($pdo, $sql_duplicar, [$grupo_bronze_b_id, $torneio_id, $time['time_id'], $class_geral['id']]);
                        } else {
                            // Criar classificação zerada
                            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            executeQuery($pdo, $sql_class, [$torneio_id, $time['time_id'], $grupo_bronze_b_id]);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível criar o grupo Bronze B agora.'
            ]);
            exit();
        }
    } else {
    }
    
    // DEBUG: Verificar se ainda está em transação antes de commitar
    if ($pdo->inTransaction()) {
        try {
            $pdo->commit();
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível finalizar a criação dos grupos agora.'
            ]);
            exit();
        }
    } else {
    }
    
    // DEBUG: Verificar se os grupos foram realmente criados após o commit
    $sql_verificar_grupos = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' ORDER BY ordem ASC";
    $stmt_verificar = executeQuery($pdo, $sql_verificar_grupos, [$torneio_id]);
    $grupos_verificados = $stmt_verificar ? $stmt_verificar->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($grupos_verificados as $grupo_ver) {
    }
    
    // Preparar lista de debug
    $lista_debug_ouro_a = [];
    foreach ($times_ouro_a as $idx => $time) {
        // Verificar se é 1º ou 2º lugar
        $posicao_origem = '1º lugar';
        // Verificar se está na lista de 2º lugares (os 2 melhores 2º lugares foram adicionados)
        $eh_segundo_lugar = false;
        if (!empty($todos_segundos_lugares) && isset($todos_segundos_lugares[0]) && $todos_segundos_lugares[0]['time_id'] == $time['time_id']) {
            $eh_segundo_lugar = true;
            $posicao_origem = '2º lugar (Melhor)';
        }
        
        $lista_debug_ouro_a[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    $lista_debug_ouro_b = [];
    foreach ($times_ouro_b as $idx => $time) {
        // Verificar se é 1º ou 2º lugar
        $posicao_origem = '1º lugar';
        // Verificar se está na lista de 2º lugares (os 2 melhores 2º lugares foram adicionados)
        $eh_segundo_lugar = false;
        if (!empty($todos_segundos_lugares) && isset($todos_segundos_lugares[1]) && $todos_segundos_lugares[1]['time_id'] == $time['time_id']) {
            $eh_segundo_lugar = true;
            $posicao_origem = '2º lugar (2º Melhor)';
        }
        
        $lista_debug_ouro_b[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    // Preparar lista de debug Prata A (3º e 5º melhor 2º lugar + 1º e 3º melhor 3º lugar)
    $lista_debug_prata_a = [];
    foreach ($times_prata_a as $idx => $time) {
        $posicao_origem = 'Desconhecido';
        // Verificar se é 2º ou 3º lugar
        $eh_segundo_lugar = false;
        
        // Verificar se está na lista de 2º lugares (3º e 5º melhores)
        if (!empty($todos_segundos_lugares)) {
            if (isset($todos_segundos_lugares[2]) && $todos_segundos_lugares[2]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (3º Melhor)';
                $eh_segundo_lugar = true;
            } elseif (isset($todos_segundos_lugares[4]) && $todos_segundos_lugares[4]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (5º Melhor)';
                $eh_segundo_lugar = true;
            }
        }
        
        // Verificar se está na lista de 3º lugares (1º e 3º melhores)
        if (!$eh_segundo_lugar && !empty($todos_terceiros_lugares)) {
            if (isset($todos_terceiros_lugares[0]) && $todos_terceiros_lugares[0]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (1º Melhor)';
            } elseif (isset($todos_terceiros_lugares[2]) && $todos_terceiros_lugares[2]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (3º Melhor)';
            }
        }
        
        $lista_debug_prata_a[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    // Preparar lista de debug Prata B (4º e 6º melhor 2º lugar + 2º e 4º melhor 3º lugar)
    $lista_debug_prata_b = [];
    foreach ($times_prata_b as $idx => $time) {
        $posicao_origem = 'Desconhecido';
        // Verificar se é 2º ou 3º lugar
        $eh_segundo_lugar = false;
        
        // Verificar se está na lista de 2º lugares (4º e 6º melhores)
        if (!empty($todos_segundos_lugares)) {
            if (isset($todos_segundos_lugares[3]) && $todos_segundos_lugares[3]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (4º Melhor)';
                $eh_segundo_lugar = true;
            } elseif (isset($todos_segundos_lugares[5]) && $todos_segundos_lugares[5]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (6º Melhor)';
                $eh_segundo_lugar = true;
            }
        }
        
        // Verificar se está na lista de 3º lugares (2º e 4º melhores)
        if (!$eh_segundo_lugar && !empty($todos_terceiros_lugares)) {
            if (isset($todos_terceiros_lugares[1]) && $todos_terceiros_lugares[1]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (2º Melhor)';
            } elseif (isset($todos_terceiros_lugares[3]) && $todos_terceiros_lugares[3]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (4º Melhor)';
            }
        }
        
        $lista_debug_prata_b[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    // Preparar lista de debug Prata B
    $lista_debug_prata_b = [];
    foreach ($times_prata_b as $idx => $time) {
        $posicao_origem = 'Desconhecido';
        // Verificar se é 2º ou 3º lugar
        $eh_segundo_lugar = false;
        $eh_terceiro_lugar = false;
        
        // Verificar se está na lista de 2º lugares (4º e 6º melhores)
        if (!empty($todos_segundos_lugares)) {
            if (isset($todos_segundos_lugares[3]) && $todos_segundos_lugares[3]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (4º Melhor)';
                $eh_segundo_lugar = true;
            } elseif (isset($todos_segundos_lugares[5]) && $todos_segundos_lugares[5]['time_id'] == $time['time_id']) {
                $posicao_origem = '2º lugar (6º Melhor)';
                $eh_segundo_lugar = true;
            }
        }
        
        // Verificar se está na lista de 3º lugares (2º e 4º melhores)
        if (!$eh_segundo_lugar && !empty($todos_terceiros_lugares)) {
            if (isset($todos_terceiros_lugares[1]) && $todos_terceiros_lugares[1]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (2º Melhor)';
                $eh_terceiro_lugar = true;
            } elseif (isset($todos_terceiros_lugares[3]) && $todos_terceiros_lugares[3]['time_id'] == $time['time_id']) {
                $posicao_origem = '3º lugar (4º Melhor)';
                $eh_terceiro_lugar = true;
            }
        }
        
        $lista_debug_prata_b[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    // Preparar lista de debug Bronze A (5º melhor 3º lugar + 4º lugares das chaves 1, 3, 5)
    $lista_debug_bronze_a = [];
    foreach ($times_bronze_a as $idx => $time) {
        $posicao_origem = 'Desconhecido';
        
        // Verificar se é o 5º melhor 3º lugar
        if (!empty($todos_terceiros_lugares) && isset($todos_terceiros_lugares[4]) && $todos_terceiros_lugares[4]['time_id'] == $time['time_id']) {
            $posicao_origem = '3º lugar (5º Melhor)';
        } else {
            // É um 4º lugar de uma chave
            $posicao_origem = '4º lugar';
        }
        
        $lista_debug_bronze_a[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    // Preparar lista de debug Bronze B (6º melhor 3º lugar + 4º lugares das chaves 2, 4, 6)
    $lista_debug_bronze_b = [];
    foreach ($times_bronze_b as $idx => $time) {
        $posicao_origem = 'Desconhecido';
        
        // Verificar se é o 6º melhor 3º lugar
        if (!empty($todos_terceiros_lugares) && isset($todos_terceiros_lugares[5]) && $todos_terceiros_lugares[5]['time_id'] == $time['time_id']) {
            $posicao_origem = '3º lugar (6º Melhor)';
        } else {
            // É um 4º lugar de uma chave
            $posicao_origem = '4º lugar';
        }
        
        $lista_debug_bronze_b[] = [
            'posicao' => $idx + 1,
            'time_id' => $time['time_id'],
            'time_nome' => $time['time_nome'],
            'chave_origem' => $time['grupo_nome'],
            'posicao_origem' => $posicao_origem,
            'pontos_total' => $time['pontos_total'],
            'vitorias' => $time['vitorias'] ?? 0,
            'derrotas' => $time['derrotas'] ?? 0,
            'empates' => $time['empates'] ?? 0,
            'pontos_pro' => $time['pontos_pro'] ?? 0,
            'pontos_contra' => $time['pontos_contra'] ?? 0,
            'average' => $time['average'],
            'saldo_pontos' => $time['saldo_pontos']
        ];
    }
    
    
    // DEBUG: Verificar se as classificações foram criadas
    foreach ($grupos_criados as $nome_grupo => $dados_grupo) {
        $grupo_id_debug = $dados_grupo['id'];
        $sql_check_class = "SELECT COUNT(*) as total FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id = ?";
        $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $grupo_id_debug]);
        $total_class = $stmt_check_class ? (int)$stmt_check_class->fetch()['total'] : 0;
        
        // Verificar times no grupo
        $sql_check_times = "SELECT COUNT(*) as total FROM torneio_grupo_times WHERE grupo_id = ?";
        $stmt_check_times = executeQuery($pdo, $sql_check_times, [$grupo_id_debug]);
        $total_times = $stmt_check_times ? (int)$stmt_check_times->fetch()['total'] : 0;
    }
    
    // DEBUG: Verificar todos os grupos da 2ª fase criados
    $sql_grupos_2fase_debug = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' ORDER BY ordem";
    $stmt_grupos_2fase_debug = executeQuery($pdo, $sql_grupos_2fase_debug, [$torneio_id]);
    $grupos_2fase_debug = $stmt_grupos_2fase_debug ? $stmt_grupos_2fase_debug->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // DEBUG: Verificar novamente os grupos antes de retornar sucesso
    $sql_verificar_final = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' ORDER BY ordem ASC";
    $stmt_verificar_final = executeQuery($pdo, $sql_verificar_final, [$torneio_id]);
    $grupos_verificados_final = $stmt_verificar_final ? $stmt_verificar_final->fetchAll(PDO::FETCH_ASSOC) : [];
    
    if (empty($grupos_verificados_final)) {
        echo json_encode([
            'success' => false,
            'message' => 'Os grupos foram criados, mas não foram encontrados na validação final. Verifique os logs do servidor.'
        ]);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Grupos Ouro A, Ouro B, Prata A, Prata B, Bronze A e Bronze B criados com sucesso! Total: ' . count($grupos_verificados_final) . ' grupos.',
        'grupos' => [
            'ouro_a' => [
                'grupo_id' => $grupos_criados['Ouro A']['id'] ?? null,
                'total_times' => count($times_ouro_a),
                'times' => $lista_debug_ouro_a
            ],
            'ouro_b' => [
                'grupo_id' => $grupos_criados['Ouro B']['id'] ?? null,
                'total_times' => count($times_ouro_b),
                'times' => $lista_debug_ouro_b
            ],
            'prata_a' => [
                'grupo_id' => $grupos_criados['Prata A']['id'] ?? null,
                'total_times' => count($times_prata_a),
                'times' => $lista_debug_prata_a
            ],
            'prata_b' => [
                'grupo_id' => $grupos_criados['Prata B']['id'] ?? null,
                'total_times' => count($times_prata_b),
                'times' => $lista_debug_prata_b
            ],
            'bronze_a' => [
                'grupo_id' => $grupos_criados['Bronze A']['id'] ?? null,
                'total_times' => count($times_bronze_a),
                'times' => $lista_debug_bronze_a
            ],
            'bronze_b' => [
                'grupo_id' => $grupos_criados['Bronze B']['id'] ?? null,
                'total_times' => count($times_bronze_b),
                'times' => $lista_debug_bronze_b
            ]
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível gerar a segunda fase agora.'
    ]);
}
?>
