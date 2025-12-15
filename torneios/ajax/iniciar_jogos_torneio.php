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

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se modalidade está configurada
if (empty($torneio['modalidade'])) {
    echo json_encode(['success' => false, 'message' => 'Configure o formato do campeonato primeiro.']);
    exit();
}

// Buscar todos os times do torneio - usar SELECT explícito para garantir ordem
$sql = "SELECT id, nome, cor, ordem, torneio_id FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar times do torneio.']);
    exit();
}
$times_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($times_raw) < 2) {
    echo json_encode(['success' => false, 'message' => 'É necessário pelo menos 2 times para gerar jogos.']);
    exit();
}

// Validar que todos os times têm IDs válidos e pertencem ao torneio correto
$times_ids = [];
$times = [];
foreach ($times_raw as $time) {
    if (!isset($time['id']) || empty($time['id']) || (int)$time['id'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Erro: Time sem ID válido encontrado. Nome: ' . ($time['nome'] ?? 'Desconhecido')]);
        exit();
    }
    
    // Verificar se o time realmente pertence ao torneio
    if ((int)$time['torneio_id'] !== (int)$torneio_id) {
        error_log("AVISO: Time ID {$time['id']} pertence ao torneio {$time['torneio_id']}, mas estamos processando torneio $torneio_id");
        continue; // Pular times que não pertencem ao torneio
    }
    
    $time_id = (int)$time['id'];
    $times_ids[] = $time_id;
    $times[] = $time; // Adicionar ao array validado
}

// Reindexar array para garantir índices sequenciais (0, 1, 2, ...)
$times = array_values($times);

if (count($times) < 2) {
    echo json_encode(['success' => false, 'message' => 'Menos de 2 times válidos encontrados após validação.']);
    exit();
}

// Log para debug
error_log("Torneio ID: $torneio_id - Total de times válidos: " . count($times) . " - IDs: " . implode(', ', $times_ids));

$modalidade = $torneio['modalidade'];
$quantidade_grupos = $torneio['quantidade_grupos'] ?? null;

// Se for modalidade todos_chaves ou torneio_pro, validar quantidade de grupos
if ($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') {
    if (!$quantidade_grupos || $quantidade_grupos < 2) {
        echo json_encode(['success' => false, 'message' => 'Configure a quantidade de chaves na modalidade.']);
        exit();
    }
    
    if ($quantidade_grupos > count($times)) {
        echo json_encode(['success' => false, 'message' => 'A quantidade de chaves não pode ser maior que a quantidade de times.']);
        exit();
    }
    
    // Verificar se a quantidade total de times é divisível pela quantidade de chaves
    // Se não for, sempre ficará uma chave com 1 time a menos
    $total_times = count($times);
    if ($total_times % $quantidade_grupos !== 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'A quantidade total de times (' . $total_times . ') não é divisível pela quantidade de chaves (' . $quantidade_grupos . '). ' .
                        'Para criar chaves, a quantidade de times deve ser divisível pela quantidade de chaves. ' .
                        'Exemplo: Para ' . $quantidade_grupos . ' chaves, você precisa de ' . (ceil($total_times / $quantidade_grupos) * $quantidade_grupos) . ' ou ' . (floor($total_times / $quantidade_grupos) * $quantidade_grupos) . ' times.'
        ]);
        exit();
    }
}

// Verificar se já existem partidas válidas
$sql_check = "SELECT COUNT(*) as total FROM torneio_partidas tp
              INNER JOIN torneio_times t1 ON t1.id = tp.time1_id
              INNER JOIN torneio_times t2 ON t2.id = tp.time2_id
              WHERE tp.torneio_id = ? AND t1.torneio_id = ? AND t2.torneio_id = ?";
$stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $torneio_id, $torneio_id]);
$total_partidas_validas = $stmt_check ? (int)$stmt_check->fetch()['total'] : 0;

if ($total_partidas_validas > 0) {
    echo json_encode(['success' => false, 'message' => 'Os jogos já foram gerados. Clique em "Limpar Jogos" no cabeçalho da seção "Jogos de Enfrentamento" se deseja gerar novamente.']);
    exit();
}

// Limpar partidas órfãs ou inválidas (caso existam)
// Buscar IDs dos times válidos primeiro
$sql_times_validos = "SELECT id FROM torneio_times WHERE torneio_id = ?";
$stmt_times = executeQuery($pdo, $sql_times_validos, [$torneio_id]);
$times_validos = $stmt_times ? $stmt_times->fetchAll(PDO::FETCH_COLUMN) : [];

if (!empty($times_validos)) {
    $placeholders = implode(',', array_fill(0, count($times_validos), '?'));
    $sql_limpar_orfas = "DELETE FROM torneio_partidas 
                         WHERE torneio_id = ? 
                         AND (time1_id NOT IN ($placeholders) OR time2_id NOT IN ($placeholders))";
    $params = array_merge([$torneio_id], $times_validos, $times_validos);
    executeQuery($pdo, $sql_limpar_orfas, $params);
} else {
    // Se não há times válidos, limpar todas as partidas do torneio
    $sql_limpar_todas = "DELETE FROM torneio_partidas WHERE torneio_id = ?";
    executeQuery($pdo, $sql_limpar_todas, [$torneio_id]);
}

// Verificar se a tabela torneio_partidas existe
$columnsQuery = $pdo->query("SHOW TABLES LIKE 'torneio_partidas'");
$tabela_existe = $columnsQuery && $columnsQuery->rowCount() > 0;

if (!$tabela_existe) {
    echo json_encode(['success' => false, 'message' => 'Estrutura de banco de dados não configurada. Execute o script SQL primeiro.']);
    exit();
}

// Verificar se a coluna quadra existe na tabela torneio_partidas
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'quadra'");
$tem_coluna_quadra = $columnsQuery && $columnsQuery->rowCount() > 0;

if (!$tem_coluna_quadra) {
    // Adicionar coluna quadra
    try {
        $pdo->exec("ALTER TABLE torneio_partidas ADD COLUMN quadra INT(11) DEFAULT NULL AFTER grupo_id");
    } catch (Exception $e) {
        error_log("Erro ao adicionar coluna quadra: " . $e->getMessage());
    }
}

// Buscar quantidade de quadras do torneio
$quantidade_quadras = (int)($torneio['quantidade_quadras'] ?? 1);
if ($quantidade_quadras < 1) {
    $quantidade_quadras = 1;
}

$pdo->beginTransaction();

try {
    $total_times = count($times);
    $grupos_criados = [];
    
    // Se for modalidade todos_chaves ou torneio_pro, verificar se grupos já foram definidos
    if ($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') {
        // Verificar se tabelas de grupos existem
        $columnsQuery = $pdo->query("SHOW TABLES LIKE 'torneio_grupos'");
        $tabela_grupos_existe = $columnsQuery && $columnsQuery->rowCount() > 0;
        
        if (!$tabela_grupos_existe) {
            throw new Exception('Estrutura de grupos não configurada. Execute o script SQL primeiro.');
        }
        
        // Verificar se grupos já foram definidos manualmente
        $sql_grupos_existentes = "SELECT tg.id, tg.nome, tg.ordem, COUNT(tgt.time_id) as total_times
                                   FROM torneio_grupos tg
                                   LEFT JOIN torneio_grupo_times tgt ON tgt.grupo_id = tg.id
                                   WHERE tg.torneio_id = ?
                                   GROUP BY tg.id, tg.nome, tg.ordem
                                   ORDER BY tg.ordem ASC";
        $stmt_grupos = executeQuery($pdo, $sql_grupos_existentes, [$torneio_id]);
        $grupos_existentes = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Verificar se há grupos com times já definidos
        $grupos_com_times = array_filter($grupos_existentes, function($g) {
            return (int)$g['total_times'] > 0;
        });
        
        if (!empty($grupos_com_times)) {
            // Grupos já foram definidos manualmente, usar os existentes
            error_log("Grupos já definidos manualmente. Usando grupos existentes.");
            foreach ($grupos_existentes as $grupo) {
                $grupos_criados[] = [
                    'id' => (int)$grupo['id'],
                    'nome' => $grupo['nome'],
                    'ordem' => (int)$grupo['ordem']
                ];
            }
        } else {
            // Grupos não foram definidos, criar automaticamente
            error_log("Grupos não definidos. Criando automaticamente.");
            
            // Limpar grupos existentes vazios (se houver)
            $sql_limpar_grupos = "DELETE FROM torneio_grupo_times WHERE grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ?)";
            executeQuery($pdo, $sql_limpar_grupos, [$torneio_id]);
            $sql_limpar = "DELETE FROM torneio_grupos WHERE torneio_id = ?";
            executeQuery($pdo, $sql_limpar, [$torneio_id]);
            
            // Função para converter número em letra (1 -> A, 2 -> B, etc.)
            $numeroParaLetra = function($numero) {
                return chr(64 + $numero); // 65 = 'A', 66 = 'B', etc.
            };
            
            // Criar grupos
            for ($g = 1; $g <= $quantidade_grupos; $g++) {
                $letra_grupo = $numeroParaLetra($g);
                $sql_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
                executeQuery($pdo, $sql_grupo, [$torneio_id, "Grupo " . $letra_grupo, $g]);
                $grupo_id = $pdo->lastInsertId();
                $grupos_criados[] = ['id' => $grupo_id, 'nome' => "Grupo " . $letra_grupo, 'ordem' => $g];
            }
            
            // Dividir times igualmente entre os grupos
            $times_por_grupo = floor($total_times / $quantidade_grupos);
            $times_restantes = $total_times % $quantidade_grupos;
            
            $indice_time = 0;
            foreach ($grupos_criados as $grupo) {
                // Calcular quantos times vão para este grupo
                $qtd_times_grupo = $times_por_grupo;
                if ($times_restantes > 0) {
                    $qtd_times_grupo++;
                    $times_restantes--;
                }
                
                // Adicionar times ao grupo
                for ($t = 0; $t < $qtd_times_grupo && $indice_time < $total_times; $t++) {
                    $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
                    executeQuery($pdo, $sql_add_time, [$grupo['id'], $times[$indice_time]['id']]);
                    $indice_time++;
                }
            }
        }
    }
    
    // Gerar jogos
    $partidas = [];
    
    if ($modalidade === 'todos_contra_todos') {
        // Todos contra todos - todos os times se enfrentam
        for ($i = 0; $i < $total_times; $i++) {
            if (!isset($times[$i]['id']) || empty($times[$i]['id'])) {
                throw new Exception("Time no índice $i não possui ID válido");
            }
            for ($j = $i + 1; $j < $total_times; $j++) {
                if (!isset($times[$j]['id']) || empty($times[$j]['id'])) {
                    throw new Exception("Time no índice $j não possui ID válido");
                }
                $time1_id = (int)$times[$i]['id'];
                $time2_id = (int)$times[$j]['id'];
                
                // Validar que os IDs estão na lista de IDs válidos
                if (!in_array($time1_id, $times_ids) || !in_array($time2_id, $times_ids)) {
                    throw new Exception("IDs inválidos detectados: time1_id=$time1_id, time2_id=$time2_id. IDs válidos: " . implode(', ', $times_ids));
                }
                
                $partidas[] = [
                    'time1_id' => $time1_id,
                    'time2_id' => $time2_id,
                    'grupo_id' => null
                ];
            }
        }
    } else if ($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') {
        // Todos contra todos dentro de cada grupo
        foreach ($grupos_criados as $grupo) {
            // Buscar times do grupo
            $sql_times_grupo = "SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?";
            $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo['id']]);
            $times_grupo_ids = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_COLUMN) : [];
            
            // Gerar jogos dentro do grupo
            $total_times_grupo = count($times_grupo_ids);
            for ($i = 0; $i < $total_times_grupo; $i++) {
                for ($j = $i + 1; $j < $total_times_grupo; $j++) {
                    $partidas[] = [
                        'time1_id' => (int)$times_grupo_ids[$i],
                        'time2_id' => (int)$times_grupo_ids[$j],
                        'grupo_id' => (int)$grupo['id']
                    ];
                }
            }
        }
    }

    // Algoritmo para distribuir jogos evitando que um time jogue consecutivamente
    // Agrupar por grupo_id se houver
    $partidas_por_grupo = [];
    foreach ($partidas as $partida) {
        $grupo_key = $partida['grupo_id'] ?? 'geral';
        if (!isset($partidas_por_grupo[$grupo_key])) {
            $partidas_por_grupo[$grupo_key] = [];
        }
        $partidas_por_grupo[$grupo_key][] = $partida;
    }
    
        // Para cada grupo (ou geral), distribuir em rodadas
    $partidas_inseridas = 0;
    
    // Mapear grupos para quadras (se houver grupos)
    $grupos_para_quadras = [];
    if (($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') && !empty($grupos_criados)) {
        // Distribuir quadras entre os grupos/chaves
        foreach ($grupos_criados as $index => $grupo) {
            $quadra_atribuida = ($index % $quantidade_quadras) + 1;
            $grupos_para_quadras[$grupo['id']] = $quadra_atribuida;
        }
    }
    
    foreach ($partidas_por_grupo as $grupo_key => $partidas_grupo) {
        // Calcular quantos times estão neste grupo
        $times_grupo = [];
        foreach ($partidas_grupo as $p) {
            if (!in_array($p['time1_id'], $times_grupo)) {
                $times_grupo[] = $p['time1_id'];
            }
            if (!in_array($p['time2_id'], $times_grupo)) {
                $times_grupo[] = $p['time2_id'];
            }
        }
        $total_times_grupo = count($times_grupo);
        
        $rodadas = [];
        $times_por_rodada = ceil($total_times_grupo / 2);
        $total_rodadas = ceil(count($partidas_grupo) / $times_por_rodada);
        
        // Inicializar rodadas
        for ($r = 1; $r <= $total_rodadas; $r++) {
            $rodadas[$r] = [];
        }
        
        // Distribuir partidas nas rodadas evitando conflitos
        foreach ($partidas_grupo as $partida) {
            $rodada_atribuida = false;
            
            // Tentar encontrar uma rodada onde nenhum dos times já está jogando
            for ($r = 1; $r <= $total_rodadas; $r++) {
                $time1_ocupado = false;
                $time2_ocupado = false;
                
                // Verificar se algum dos times já está na rodada
                foreach ($rodadas[$r] as $p) {
                    if ($p['time1_id'] == $partida['time1_id'] || $p['time2_id'] == $partida['time1_id']) {
                        $time1_ocupado = true;
                    }
                    if ($p['time1_id'] == $partida['time2_id'] || $p['time2_id'] == $partida['time2_id']) {
                        $time2_ocupado = true;
                    }
                }
                
                // Se nenhum dos times está ocupado nesta rodada, adicionar
                if (!$time1_ocupado && !$time2_ocupado) {
                    $rodadas[$r][] = $partida;
                    $rodada_atribuida = true;
                    break;
                }
            }
            
            // Se não encontrou rodada ideal, colocar na primeira disponível
            if (!$rodada_atribuida) {
                for ($r = 1; $r <= $total_rodadas; $r++) {
                    if (count($rodadas[$r]) < $times_por_rodada) {
                        $rodadas[$r][] = $partida;
                        break;
                    }
                }
            }
        }
        
        // Inserir partidas nas rodadas
        foreach ($rodadas as $rodada_num => $partidas_rodada) {
            foreach ($partidas_rodada as $partida) {
                // Atribuir quadra baseada no grupo/chave
                $quadra_atribuida = null;
                if ($quantidade_quadras > 1) {
                    if (($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') && isset($partida['grupo_id']) && isset($grupos_para_quadras[$partida['grupo_id']])) {
                        // Se for modalidade com chaves, usar a quadra atribuída ao grupo
                        $quadra_atribuida = $grupos_para_quadras[$partida['grupo_id']];
                    } else {
                        // Se for todos contra todos sem chaves, distribuir sequencialmente
                        // Mas como não há grupos, vamos usar um contador geral
                        static $contador_quadra_geral = 0;
                        $quadra_atribuida = ($contador_quadra_geral % $quantidade_quadras) + 1;
                        $contador_quadra_geral++;
                    }
                }
                // Validar IDs antes de inserir
                $time1_id = (int)($partida['time1_id'] ?? 0);
                $time2_id = (int)($partida['time2_id'] ?? 0);
                
                if ($time1_id <= 0 || $time2_id <= 0) {
                    throw new Exception("IDs inválidos na partida: time1_id=$time1_id, time2_id=$time2_id");
                }
                
                // Verificar se os times existem e pertencem ao torneio
                $sql_check_time1 = "SELECT id FROM torneio_times WHERE id = ? AND torneio_id = ?";
                $stmt_check1 = executeQuery($pdo, $sql_check_time1, [$time1_id, $torneio_id]);
                if (!$stmt_check1 || !$stmt_check1->fetch()) {
                    throw new Exception("Time 1 (ID: $time1_id) não existe ou não pertence ao torneio $torneio_id");
                }
                
                $sql_check_time2 = "SELECT id FROM torneio_times WHERE id = ? AND torneio_id = ?";
                $stmt_check2 = executeQuery($pdo, $sql_check_time2, [$time2_id, $torneio_id]);
                if (!$stmt_check2 || !$stmt_check2->fetch()) {
                    throw new Exception("Time 2 (ID: $time2_id) não existe ou não pertence ao torneio $torneio_id");
                }
                
                // Verificar se a coluna grupo_id existe na tabela
                static $grupo_id_column_exists = null;
                if ($grupo_id_column_exists === null) {
                    try {
                        $check_column = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
                        $grupo_id_column_exists = $check_column && $check_column->rowCount() > 0;
                    } catch (Exception $e) {
                        $grupo_id_column_exists = false;
                    }
                }
                
                // Usar grupo_id da partida (pode ser null para todos_contra_todos)
                $grupo_id_inserir = isset($partida['grupo_id']) && $partida['grupo_id'] !== null ? $partida['grupo_id'] : null;
                
                // Verificar se a coluna quadra existe
                static $quadra_column_exists = null;
                if ($quadra_column_exists === null) {
                    try {
                        $check_quadra = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'quadra'");
                        $quadra_column_exists = $check_quadra && $check_quadra->rowCount() > 0;
                    } catch (Exception $e) {
                        $quadra_column_exists = false;
                    }
                }
                
                if ($grupo_id_column_exists && $quadra_column_exists) {
                    $sql_insert = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, rodada, grupo_id, quadra, status) 
                                  VALUES (?, ?, ?, 'Grupos', ?, ?, ?, 'Agendada')";
                    $params_insert = [
                        $torneio_id, 
                        $time1_id, 
                        $time2_id, 
                        $rodada_num,
                        $grupo_id_inserir,
                        $quadra_atribuida
                    ];
                } elseif ($grupo_id_column_exists) {
                    $sql_insert = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, rodada, grupo_id, status) 
                                  VALUES (?, ?, ?, 'Grupos', ?, ?, 'Agendada')";
                    $params_insert = [
                        $torneio_id, 
                        $time1_id, 
                        $time2_id, 
                        $rodada_num,
                        $grupo_id_inserir
                    ];
                } elseif ($quadra_column_exists) {
                    $sql_insert = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, rodada, quadra, status) 
                                  VALUES (?, ?, ?, 'Grupos', ?, ?, 'Agendada')";
                    $params_insert = [
                        $torneio_id, 
                        $time1_id, 
                        $time2_id, 
                        $rodada_num,
                        $quadra_atribuida
                    ];
                } else {
                    // Se nenhuma das colunas existe, inserir sem grupo_id e sem quadra
                    $sql_insert = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, rodada, status) 
                                  VALUES (?, ?, ?, 'Grupos', ?, 'Agendada')";
                    $params_insert = [
                        $torneio_id, 
                        $time1_id, 
                        $time2_id, 
                        $rodada_num
                    ];
                }
                
                $stmt_insert = executeQuery($pdo, $sql_insert, $params_insert);
                
                if ($stmt_insert === false) {
                    // Obter último erro do PDO
                    $error_info = $pdo->errorInfo();
                    throw new Exception("Erro ao inserir partida entre time $time1_id e time $time2_id. Erro SQL: " . ($error_info[2] ?? 'Desconhecido'));
                }
                $partidas_inseridas++;
            }
        }
    }
    
    if ($partidas_inseridas === 0) {
        throw new Exception("Nenhuma partida foi inserida. Verifique se há times configurados.");
    }
    
    // Inicializar classificação para todos os times
    // Verificar se a coluna grupo_id existe na tabela torneio_classificacao
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
    $tem_grupo_id_classificacao = $columnsQuery && $columnsQuery->rowCount() > 0;
    
    if (!$tem_grupo_id_classificacao) {
        try {
            $pdo->exec("ALTER TABLE torneio_classificacao ADD COLUMN grupo_id INT(11) DEFAULT NULL AFTER time_id");
        } catch (Exception $e) {
            error_log("Erro ao adicionar coluna grupo_id em torneio_classificacao: " . $e->getMessage());
        }
    }
    
    // Buscar grupo_id de cada time se for modalidade com chaves
    $times_por_grupo = [];
    if ($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') {
        foreach ($grupos_criados as $grupo) {
            $sql_times_grupo = "SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?";
            $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo['id']]);
            $times_grupo_ids = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($times_grupo_ids as $time_id_grupo) {
                $times_por_grupo[$time_id_grupo] = $grupo['id'];
            }
        }
    }
    
    foreach ($times as $time) {
        $grupo_id_time = $times_por_grupo[$time['id']] ?? null;
        
        if ($tem_grupo_id_classificacao && $grupo_id_time) {
            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                         VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)
                         ON DUPLICATE KEY UPDATE time_id = time_id, grupo_id = ?";
            executeQuery($pdo, $sql_class, [$torneio_id, $time['id'], $grupo_id_time, $grupo_id_time]);
        } else {
            $sql_class = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                         VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)
                         ON DUPLICATE KEY UPDATE time_id = time_id";
            executeQuery($pdo, $sql_class, [$torneio_id, $time['id']]);
        }
    }
    
    $pdo->commit();
    
    // Usar o número real de partidas inseridas
    $total_partidas = isset($partidas_inseridas) ? $partidas_inseridas : count($partidas);
    $mensagem = "Jogos gerados com sucesso! Total de " . $total_partidas . " partidas criadas.";
    if ($modalidade === 'todos_chaves') {
        $mensagem .= " Times divididos em " . $quantidade_grupos . " chave(s).";
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $mensagem,
        'total_partidas' => $total_partidas
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerar jogos: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao gerar jogos: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}
?>

