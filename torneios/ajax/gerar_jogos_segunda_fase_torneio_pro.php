<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

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

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

if ($torneio['modalidade'] !== 'torneio_pro') {
    echo json_encode(['success' => false, 'message' => 'Esta função é apenas para torneios do tipo Torneio Pro.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se os grupos da 2ª fase existem
$sql_grupos_2fase = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' ORDER BY ordem ASC";
$stmt_grupos = executeQuery($pdo, $sql_grupos_2fase, [$torneio_id]);
$grupos_2fase = $stmt_grupos ? $stmt_grupos->fetchAll() : [];

if (empty($grupos_2fase)) {
    echo json_encode(['success' => false, 'message' => 'Os grupos da 2ª fase ainda não foram criados. Gere a 2ª fase primeiro.']);
    exit();
}

// Verificar se já existem jogos da 2ª fase (todos contra todos) na nova tabela
$sql_check_jogos = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ?";
$stmt_check_jogos = executeQuery($pdo, $sql_check_jogos, [$torneio_id]);
$tem_jogos_2fase = $stmt_check_jogos ? (int)$stmt_check_jogos->fetch()['total'] > 0 : false;

if ($tem_jogos_2fase) {
    echo json_encode(['success' => false, 'message' => 'Os jogos todos contra todos da 2ª fase já foram gerados.']);
    exit();
}

// Buscar quantidade de quadras do torneio
$quantidade_quadras = (int)($torneio['quantidade_quadras'] ?? 1);
if ($quantidade_quadras < 1) {
    $quantidade_quadras = 1;
}

// Verificar se a coluna grupo_id existe em torneio_partidas
$columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
$tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;

// Verificar se a coluna tipo_fase existe em torneio_partidas
$columnsQuery_tipo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
$tem_tipo_fase = $columnsQuery_tipo && $columnsQuery_tipo->rowCount() > 0;

// Mapear grupos para quadras
$grupos_para_quadras = [];
$quadras_grupos = ['Ouro A' => 1, 'Ouro B' => 2, 'Prata A' => 3, 'Prata B' => 4, 'Bronze A' => 5, 'Bronze B' => 6]; // Ouro A = Quadra 1, Ouro B = Quadra 2, Prata A = Quadra 3, Prata B = Quadra 4, Bronze A = Quadra 5, Bronze B = Quadra 6

foreach ($grupos_2fase as $grupo) {
    $nome_grupo = str_replace('2ª Fase - ', '', $grupo['nome']);
    if (isset($quadras_grupos[$nome_grupo])) {
        $grupos_para_quadras[$grupo['id']] = $quadras_grupos[$nome_grupo];
    }
}

$pdo->beginTransaction();

try {
    // NÃO zerar a classificação dos grupos originais - eles devem permanecer para mostrar a origem
    
    // Filtrar apenas grupos originais (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)
    $grupos_originais_2fase = [];
    foreach ($grupos_2fase as $grupo) {
        $nome_grupo = str_replace('2ª Fase - ', '', $grupo['nome']);
        // Ignorar grupos de classificação e chaves
        if (strpos($nome_grupo, 'Classificação') === false && strpos($nome_grupo, 'Chaves') === false) {
            $grupos_originais_2fase[] = $grupo;
        }
    }
    
    $partidas_inseridas = 0;
    
    // Verificar se a coluna grupo_id existe em torneio_classificacao
    $columnsQuery_class = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
    $tem_grupo_id_classificacao = $columnsQuery_class && $columnsQuery_class->rowCount() > 0;
    
    if (!$tem_grupo_id_classificacao) {
        throw new Exception("A coluna grupo_id não existe em torneio_classificacao.");
    }
    
    // Agrupar grupos por série para criar semi-finais depois
    $series = [
        'Ouro' => ['A' => null, 'B' => null],
        'Prata' => ['A' => null, 'B' => null],
        'Bronze' => ['A' => null, 'B' => null]
    ];
    
    // Para cada grupo original, criar jogos todos contra todos dentro do próprio grupo
    foreach ($grupos_originais_2fase as $grupo) {
        $grupo_id = (int)$grupo['id'];
        $nome_grupo = str_replace('2ª Fase - ', '', $grupo['nome']);
        $quadra_grupo = $grupos_para_quadras[$grupo_id] ?? 1;
        
        // Identificar série e subgrupo
        $serie_nome = null;
        $subgrupo = null;
        foreach ($series as $serie => &$subgrupos) {
            if (strpos($nome_grupo, $serie . ' A') !== false) {
                $serie_nome = $serie;
                $subgrupo = 'A';
                $subgrupos['A'] = $grupo;
                break;
            } elseif (strpos($nome_grupo, $serie . ' B') !== false) {
                $serie_nome = $serie;
                $subgrupo = 'B';
                $subgrupos['B'] = $grupo;
                break;
            }
        }
        
        if (!$serie_nome) {
            continue; // Pular se não identificar a série
        }
        
        // Buscar todos os times do grupo
        $sql_times = "SELECT tt.id AS time_id, tt.nome, tt.cor
                     FROM torneio_times tt
                     JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                     WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                     ORDER BY tt.id ASC";
        $stmt_times = executeQuery($pdo, $sql_times, [$grupo_id, $torneio_id]);
        $times = $stmt_times ? $stmt_times->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Log de debug
        error_log("DEBUG - Grupo: $nome_grupo (ID: $grupo_id) - Times encontrados: " . count($times));
        foreach ($times as $t) {
            error_log("DEBUG - Time no grupo $nome_grupo: " . $t['nome'] . " (ID: " . $t['time_id'] . ")");
        }
        
        if (count($times) < 2) {
            error_log("DEBUG - Grupo $nome_grupo tem menos de 2 times, pulando...");
            continue; // Pular se não tiver pelo menos 2 times
        }
        
        // NÃO zerar a classificação - ela será atualizada automaticamente quando os jogos forem finalizados
        // A classificação original (que mostra a origem) deve ser preservada
        
        // Verificar e criar entradas de classificação apenas se não existirem
        // A classificação será atualizada automaticamente quando os jogos forem finalizados
        foreach ($times as $time) {
            $time_id = (int)$time['time_id'];
            
            // Verificar se já existe classificação para este grupo
            $sql_check_class = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
            $stmt_check_class = executeQuery($pdo, $sql_check_class, [$torneio_id, $time_id, $grupo_id]);
            $existe_class = $stmt_check_class ? $stmt_check_class->fetch() : null;
            
            if (!$existe_class) {
                // Criar classificação zerada apenas se não existir
                // Isso preserva a classificação original que mostra a origem
                $sql_insert_class = "INSERT INTO torneio_classificacao 
                                    (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                     pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                    VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                executeQuery($pdo, $sql_insert_class, [$torneio_id, $time_id, $grupo_id]);
            }
            // Se já existe, não fazer nada - preservar a classificação existente
            
            // Criar registro inicial em partidas_2fase_classificacao para alimentar a tabela de classificação
            // Verificar se já existe registro na tabela partidas_2fase_classificacao
            $sql_check_class_2fase = "SELECT id FROM partidas_2fase_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
            $stmt_check_class_2fase = executeQuery($pdo, $sql_check_class_2fase, [$torneio_id, $time_id, $grupo_id]);
            $existe_class_2fase = $stmt_check_class_2fase ? $stmt_check_class_2fase->fetch() : null;
            
            if (!$existe_class_2fase) {
                // Criar classificação zerada na tabela partidas_2fase_classificacao
                $sql_insert_class_2fase = "INSERT INTO partidas_2fase_classificacao 
                                          (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                           pontos_pro, pontos_contra, saldo_pontos, average, pontos_total, posicao)
                                          VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0, NULL)";
                executeQuery($pdo, $sql_insert_class_2fase, [$torneio_id, $time_id, $grupo_id]);
                error_log("DEBUG - Criado registro inicial em partidas_2fase_classificacao para time $time_id no grupo $grupo_id ($nome_grupo)");
            }
        }
        
        // Atualizar posições iniciais para o grupo (todos com dados zerados terão posição NULL ou ordenados por ID)
        $sql_update_posicoes = "UPDATE partidas_2fase_classificacao tc1
                                SET posicao = (
                                    SELECT COUNT(*) + 1
                                    FROM partidas_2fase_classificacao tc2
                                    WHERE tc2.torneio_id = tc1.torneio_id 
                                    AND tc2.grupo_id = tc1.grupo_id
                                    AND (
                                        tc2.pontos_total > tc1.pontos_total
                                        OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias > tc1.vitorias)
                                        OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average > tc1.average)
                                        OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos > tc1.saldo_pontos)
                                        OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos = tc1.saldo_pontos AND tc2.time_id < tc1.time_id)
                                    )
                                )
                                WHERE tc1.torneio_id = ? AND tc1.grupo_id = ?";
        executeQuery($pdo, $sql_update_posicoes, [$torneio_id, $grupo_id]);
        
        // Gerar jogos todos contra todos dentro do grupo
        $total_times = count($times);
        $partidas_grupo = 0;
        
        // Primeiro, gerar todas as partidas possíveis em um array
        $todas_partidas = [];
        for ($i = 0; $i < $total_times; $i++) {
            for ($j = $i + 1; $j < $total_times; $j++) {
                $todas_partidas[] = [
                    'time1_id' => (int)$times[$i]['time_id'],
                    'time2_id' => (int)$times[$j]['time_id'],
                    'time1_nome' => $times[$i]['nome'],
                    'time2_nome' => $times[$j]['nome']
                ];
            }
        }
        
        // Embaralhar aleatoriamente as partidas para distribuição aleatória
        shuffle($todas_partidas);
        
        // Distribuir partidas em rodadas evitando que o mesmo time jogue consecutivamente
        $partidas_por_rodada = [];
        $rodada_atual = 1;
        
        foreach ($todas_partidas as $partida) {
            $time1_id = $partida['time1_id'];
            $time2_id = $partida['time2_id'];
            $encontrou_rodada = false;
            
            // Tentar encontrar uma rodada onde nenhum dos times já esteja jogando
            foreach ($partidas_por_rodada as $rodada => $partidas_rodada) {
                $times_na_rodada = [];
                foreach ($partidas_rodada as $p) {
                    $times_na_rodada[] = $p['time1_id'];
                    $times_na_rodada[] = $p['time2_id'];
                }
                
                // Se nenhum dos times está nesta rodada, adicionar aqui
                if (!in_array($time1_id, $times_na_rodada) && !in_array($time2_id, $times_na_rodada)) {
                    $partidas_por_rodada[$rodada][] = $partida;
                    $encontrou_rodada = true;
                    break;
                }
            }
            
            // Se não encontrou rodada disponível, criar nova rodada
            if (!$encontrou_rodada) {
                $partidas_por_rodada[$rodada_atual] = [$partida];
                $rodada_atual++;
            }
        }
        
        // Reorganizar partidas para evitar que o mesmo time apareça consecutivamente na ordem final
        $partidas_ordenadas_final = [];
        $ultimos_times = []; // Rastrear os últimos times inseridos
        
        // Para cada rodada, reorganizar as partidas
        foreach ($partidas_por_rodada as $rodada => $partidas_rodada) {
            $partidas_restantes = array_values($partidas_rodada);
            $partidas_rodada_ordenadas = [];
            
            while (!empty($partidas_restantes)) {
                $melhor_idx = null;
                $melhor_score = -1;
                
                // Encontrar a melhor partida que evita repetição
                foreach ($partidas_restantes as $idx => $partida) {
                    $time1_id = $partida['time1_id'];
                    $time2_id = $partida['time2_id'];
                    $score = 0;
                    
                    if (empty($ultimos_times)) {
                        // Primeira partida - qualquer uma serve
                        $score = 100;
                    } else {
                        // Verificar quantas vezes cada time aparece nos últimos
                        $count_time1 = count(array_filter($ultimos_times, function($t) use ($time1_id) { return $t == $time1_id; }));
                        $count_time2 = count(array_filter($ultimos_times, function($t) use ($time2_id) { return $t == $time2_id; }));
                        
                        // Penalizar se algum time apareceu recentemente
                        $score = 100 - ($count_time1 * 50) - ($count_time2 * 50);
                        
                        // Bonus se nenhum time apareceu recentemente
                        if ($count_time1 == 0 && $count_time2 == 0) {
                            $score = 100;
                        }
                    }
                    
                    if ($score > $melhor_score) {
                        $melhor_score = $score;
                        $melhor_idx = $idx;
                    }
                }
                
                // Adicionar a melhor partida encontrada
                if ($melhor_idx !== null) {
                    $partida = $partidas_restantes[$melhor_idx];
                    $partidas_rodada_ordenadas[] = ['partida' => $partida, 'rodada' => $rodada];
                    
                    // Atualizar últimos times (manter apenas os últimos 4 times)
                    $ultimos_times[] = $partida['time1_id'];
                    $ultimos_times[] = $partida['time2_id'];
                    if (count($ultimos_times) > 8) {
                        $ultimos_times = array_slice($ultimos_times, -8);
                    }
                    
                    unset($partidas_restantes[$melhor_idx]);
                    $partidas_restantes = array_values($partidas_restantes);
                } else {
                    // Fallback: pegar a primeira disponível
                    $partida = reset($partidas_restantes);
                    $partidas_rodada_ordenadas[] = ['partida' => $partida, 'rodada' => $rodada];
                    $ultimos_times[] = $partida['time1_id'];
                    $ultimos_times[] = $partida['time2_id'];
                    if (count($ultimos_times) > 8) {
                        $ultimos_times = array_slice($ultimos_times, -8);
                    }
                    unset($partidas_restantes[key($partidas_restantes)]);
                    $partidas_restantes = array_values($partidas_restantes);
                }
            }
            
            $partidas_ordenadas_final = array_merge($partidas_ordenadas_final, $partidas_rodada_ordenadas);
        }
        
        // Inserir partidas no banco de dados na ordem reorganizada
        // Salvar na nova tabela partidas_2fase_torneio
        foreach ($partidas_ordenadas_final as $item) {
            $partida = $item['partida'];
            $rodada = $item['rodada'];
            $time1_id = $partida['time1_id'];
            $time2_id = $partida['time2_id'];
            
            // Log de debug
            error_log("DEBUG - Criando partida no grupo $nome_grupo (Rodada $rodada): " . $partida['time1_nome'] . " vs " . $partida['time2_nome']);
            
            // Inserir na nova tabela partidas_2fase_torneio
            $sql_partida = "INSERT INTO partidas_2fase_torneio (torneio_id, time1_id, time2_id, grupo_id, rodada, quadra, status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'Agendada')";
            $result_insert = executeQuery($pdo, $sql_partida, [$torneio_id, $time1_id, $time2_id, $grupo_id, $rodada, $quadra_grupo]);
            
            if ($result_insert === false) {
                $error_info = $pdo->errorInfo();
                error_log("ERRO ao inserir partida no grupo $nome_grupo (Rodada $rodada): " . ($error_info[2] ?? 'Desconhecido'));
                throw new Exception("Erro ao inserir partida: " . ($error_info[2] ?? 'Desconhecido'));
            }
            
            $partidas_inseridas++;
            $partidas_grupo++;
        }
        
        error_log("DEBUG - Grupo $nome_grupo: $partidas_grupo partidas criadas");
    }
    
    // Criar grupos de classificação por série (Ouro, Prata, Bronze) para alimentar com os jogos da 2ª fase
    foreach ($series as $serie_nome => $subgrupos) {
        $grupo_a = $subgrupos['A'];
        $grupo_b = $subgrupos['B'];
        
        if (!$grupo_a || !$grupo_b) {
            continue; // Pular se não tiver ambos os grupos
        }
        
        // Criar grupo de classificação da série (será alimentado pelos jogos todos contra todos)
        $sql_grupo_class_serie = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
        $stmt_grupo_class_serie = executeQuery($pdo, $sql_grupo_class_serie, [$torneio_id, "2ª Fase - $serie_nome - Classificação"]);
        $grupo_class_serie_existente = $stmt_grupo_class_serie ? $stmt_grupo_class_serie->fetch() : null;
        
        if ($grupo_class_serie_existente) {
            $grupo_class_serie_id = (int)$grupo_class_serie_existente['id'];
        } else {
            $sql_insert_grupo_class = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
            executeQuery($pdo, $sql_insert_grupo_class, [$torneio_id, "2ª Fase - $serie_nome - Classificação", 150]);
            $grupo_class_serie_id = (int)$pdo->lastInsertId();
        }
        
        // Buscar todos os times dos grupos A e B da série
        $grupo_a_id = (int)$grupo_a['id'];
        $grupo_b_id = (int)$grupo_b['id'];
        
        $sql_times_serie = "SELECT DISTINCT tt.id AS time_id, tt.nome, tt.cor
                           FROM torneio_times tt
                           JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                           WHERE tgt.grupo_id IN (?, ?) AND tt.torneio_id = ?
                           ORDER BY tt.id ASC";
        $stmt_times_serie = executeQuery($pdo, $sql_times_serie, [$grupo_a_id, $grupo_b_id, $torneio_id]);
        $times_serie = $stmt_times_serie ? $stmt_times_serie->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Criar entradas de classificação zeradas para todos os times da série
        foreach ($times_serie as $time_serie) {
            $time_id_serie = (int)$time_serie['time_id'];
            
            // Verificar se já existe classificação para este grupo de classificação da série
            $sql_check_class_serie = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
            $stmt_check_class_serie = executeQuery($pdo, $sql_check_class_serie, [$torneio_id, $time_id_serie, $grupo_class_serie_id]);
            $existe_class_serie = $stmt_check_class_serie ? $stmt_check_class_serie->fetch() : null;
            
            if (!$existe_class_serie) {
                // Criar classificação zerada para o grupo de classificação da série
                $sql_insert_class_serie = "INSERT INTO torneio_classificacao 
                                          (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                           pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                          VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                executeQuery($pdo, $sql_insert_class_serie, [$torneio_id, $time_id_serie, $grupo_class_serie_id]);
            } else {
                // Zerar classificação existente (apenas a do grupo de classificação da série)
                $sql_zerar_class_serie = "UPDATE torneio_classificacao 
                                         SET vitorias = 0, derrotas = 0, empates = 0, pontos_pro = 0, 
                                             pontos_contra = 0, saldo_pontos = 0, average = 0.00, pontos_total = 0
                                         WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                executeQuery($pdo, $sql_zerar_class_serie, [$torneio_id, $time_id_serie, $grupo_class_serie_id]);
            }
        }
        
        // Criar grupo para as chaves eliminatórias da série (semi-finais e finais)
        $sql_grupo_chaves = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
        $stmt_grupo_chaves = executeQuery($pdo, $sql_grupo_chaves, [$torneio_id, "2ª Fase - $serie_nome - Chaves"]);
        $grupo_chaves_existente = $stmt_grupo_chaves ? $stmt_grupo_chaves->fetch() : null;
        
        if ($grupo_chaves_existente) {
            $grupo_chaves_id = (int)$grupo_chaves_existente['id'];
        } else {
            $sql_insert_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
            executeQuery($pdo, $sql_insert_grupo, [$torneio_id, "2ª Fase - $serie_nome - Chaves", 200]);
            $grupo_chaves_id = (int)$pdo->lastInsertId();
        }
        
        // As semi-finais serão criadas depois, quando os jogos todos contra todos estiverem completos
        // Por enquanto, apenas criamos os grupos de chaves
    }
    
    if ($partidas_inseridas === 0) {
        throw new Exception("Nenhuma partida da 2ª fase foi inserida.");
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Jogos da 2ª fase gerados com sucesso! Total de " . $partidas_inseridas . " partidas criadas.",
        'total_partidas' => $partidas_inseridas
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerar jogos da 2ª fase: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao gerar jogos da 2ª fase: ' . $e->getMessage()
    ]);
}
?>

