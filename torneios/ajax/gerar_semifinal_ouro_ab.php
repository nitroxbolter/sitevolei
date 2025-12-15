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

// Função para buscar classificação de um grupo
function buscarClassificacaoGrupo($pdo, $torneio_id, $grupo_id, $grupo_nome) {
    // Buscar todos os times do grupo
    $sql_times = "SELECT tt.id AS time_id, tt.nome AS time_nome, tt.cor AS time_cor
                  FROM torneio_times tt
                  JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                  WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                  ORDER BY tt.id ASC";
    $stmt_times = executeQuery($pdo, $sql_times, [$grupo_id, $torneio_id]);
    $times = $stmt_times ? $stmt_times->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $times_classificacao = [];
    
    foreach ($times as $time) {
        $time_id = (int)$time['time_id'];
        
        // Buscar todos os jogos finalizados deste time neste grupo
        $sql_jogos = "SELECT 
                        pontos_time1, pontos_time2, time1_id, time2_id
                      FROM torneio_partidas
                      WHERE grupo_id = ? 
                        AND status = 'Finalizada'
                        AND fase = '2ª Fase'
                        AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')
                        AND (time1_id = ? OR time2_id = ?)";
        
        $stmt_jogos = executeQuery($pdo, $sql_jogos, [$grupo_id, $time_id, $time_id]);
        $jogos = $stmt_jogos ? $stmt_jogos->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Calcular estatísticas manualmente
        $vitorias = 0;
        $derrotas = 0;
        $empates = 0;
        $pontos_pro = 0;
        $pontos_contra = 0;
        
        foreach ($jogos as $jogo) {
            $pontos1 = (int)$jogo['pontos_time1'];
            $pontos2 = (int)$jogo['pontos_time2'];
            
            if ($jogo['time1_id'] == $time_id) {
                $pontos_pro += $pontos1;
                $pontos_contra += $pontos2;
                if ($pontos1 > $pontos2) {
                    $vitorias++;
                } elseif ($pontos1 < $pontos2) {
                    $derrotas++;
                } else {
                    $empates++;
                }
            } else {
                $pontos_pro += $pontos2;
                $pontos_contra += $pontos1;
                if ($pontos2 > $pontos1) {
                    $vitorias++;
                } elseif ($pontos2 < $pontos1) {
                    $derrotas++;
                } else {
                    $empates++;
                }
            }
        }
        
        $saldo = $pontos_pro - $pontos_contra;
        $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
        $pontos_total = ($vitorias * 3) + ($empates * 1);
        
        $times_classificacao[] = [
            'time_id' => $time_id,
            'nome' => $time['time_nome'],
            'cor' => $time['time_cor'],
            'grupo' => $grupo_nome,
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
    
    // Ordenar por pontos_total, depois vitorias, depois average, depois saldo
    usort($times_classificacao, function($a, $b) {
        if ($a['pontos_total'] != $b['pontos_total']) {
            return $b['pontos_total'] - $a['pontos_total'];
        }
        if ($a['vitorias'] != $b['vitorias']) {
            return $b['vitorias'] - $a['vitorias'];
        }
        if ($a['average'] != $b['average']) {
            return $b['average'] <=> $a['average'];
        }
        return $b['saldo_pontos'] - $a['saldo_pontos'];
    });
    
    return $times_classificacao;
}

// Buscar grupos Ouro A e Ouro B
$sql_grupo_ouro_a = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro A' LIMIT 1";
$stmt_grupo_a = executeQuery($pdo, $sql_grupo_ouro_a, [$torneio_id]);
$grupo_ouro_a = $stmt_grupo_a ? $stmt_grupo_a->fetch() : null;

$sql_grupo_ouro_b = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro B' LIMIT 1";
$stmt_grupo_b = executeQuery($pdo, $sql_grupo_ouro_b, [$torneio_id]);
$grupo_ouro_b = $stmt_grupo_b ? $stmt_grupo_b->fetch() : null;

if (!$grupo_ouro_a || !$grupo_ouro_b) {
    echo json_encode(['success' => false, 'message' => 'Grupos Ouro A ou Ouro B não encontrados.']);
    exit();
}

$grupo_ouro_a_id = (int)$grupo_ouro_a['id'];
$grupo_ouro_b_id = (int)$grupo_ouro_b['id'];

// Buscar classificação de Ouro A e Ouro B
$classificacao_ouro_a = buscarClassificacaoGrupo($pdo, $torneio_id, $grupo_ouro_a_id, 'Ouro A');
$classificacao_ouro_b = buscarClassificacaoGrupo($pdo, $torneio_id, $grupo_ouro_b_id, 'Ouro B');

// Pegar os 2 melhores de cada grupo
$melhores_ouro_a = array_slice($classificacao_ouro_a, 0, 2);
$melhores_ouro_b = array_slice($classificacao_ouro_b, 0, 2);

if (count($melhores_ouro_a) < 2 || count($melhores_ouro_b) < 2) {
    echo json_encode([
        'success' => false, 
        'message' => 'É necessário ter pelo menos 2 times em cada grupo (Ouro A e Ouro B) para gerar a semi-final.',
        'debug' => [
            'ouro_a' => count($melhores_ouro_a),
            'ouro_b' => count($melhores_ouro_b)
        ]
    ]);
    exit();
}

// Verificar se todas as partidas do Ouro A e Ouro B estão finalizadas
$sql_check_partidas_a = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM torneio_partidas 
                         WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'
                         AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
$stmt_check_a = executeQuery($pdo, $sql_check_partidas_a, [$torneio_id, $grupo_ouro_a_id]);
$info_partidas_a = $stmt_check_a ? $stmt_check_a->fetch() : ['total' => 0, 'finalizadas' => 0];

$sql_check_partidas_b = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM torneio_partidas 
                         WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'
                         AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
$stmt_check_b = executeQuery($pdo, $sql_check_partidas_b, [$torneio_id, $grupo_ouro_b_id]);
$info_partidas_b = $stmt_check_b ? $stmt_check_b->fetch() : ['total' => 0, 'finalizadas' => 0];

$todas_finalizadas_a = $info_partidas_a['total'] > 0 && $info_partidas_a['finalizadas'] == $info_partidas_a['total'];
$todas_finalizadas_b = $info_partidas_b['total'] > 0 && $info_partidas_b['finalizadas'] == $info_partidas_b['total'];

if (!$todas_finalizadas_a || !$todas_finalizadas_b) {
    echo json_encode([
        'success' => false, 
        'message' => 'Nem todas as partidas dos grupos Ouro A e Ouro B estão finalizadas.',
        'debug' => [
            'ouro_a' => ['total' => $info_partidas_a['total'], 'finalizadas' => $info_partidas_a['finalizadas']],
            'ouro_b' => ['total' => $info_partidas_b['total'], 'finalizadas' => $info_partidas_b['finalizadas']]
        ]
    ]);
    exit();
}

// Verificar se já existe semi-final
$sql_grupo_chaves_check = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro - Chaves' LIMIT 1";
$stmt_grupo_chaves_check = executeQuery($pdo, $sql_grupo_chaves_check, [$torneio_id]);
$grupo_chaves_check = $stmt_grupo_chaves_check ? $stmt_grupo_chaves_check->fetch() : null;

$tem_semifinal = false;
if ($grupo_chaves_check) {
    $grupo_chaves_check_id = (int)$grupo_chaves_check['id'];
    $sql_check_semifinal = "SELECT COUNT(*) as total FROM torneio_partidas 
                           WHERE torneio_id = ? AND fase = '2ª Fase' 
                           AND tipo_fase = 'Semi-Final' 
                           AND grupo_id = ?";
    $stmt_check_semifinal = executeQuery($pdo, $sql_check_semifinal, [$torneio_id, $grupo_chaves_check_id]);
    $tem_semifinal = $stmt_check_semifinal ? (int)$stmt_check_semifinal->fetch()['total'] > 0 : false;
}

if ($tem_semifinal) {
    echo json_encode(['success' => false, 'message' => 'As semi-finais já foram geradas.']);
    exit();
}

// Verificar se a coluna grupo_id existe em torneio_partidas
$columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
$tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;

// Verificar se a coluna tipo_fase existe em torneio_partidas
$columnsQuery_tipo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
$tem_tipo_fase = $columnsQuery_tipo && $columnsQuery_tipo->rowCount() > 0;

$pdo->beginTransaction();

try {
    // Times para semi-final:
    // 1º Ouro A, 2º Ouro A, 1º Ouro B, 2º Ouro B
    $primeiro_ouro_a = (int)$melhores_ouro_a[0]['time_id'];
    $segundo_ouro_a = (int)$melhores_ouro_a[1]['time_id'];
    $primeiro_ouro_b = (int)$melhores_ouro_b[0]['time_id'];
    $segundo_ouro_b = (int)$melhores_ouro_b[1]['time_id'];
    
    // Buscar ou criar grupo de chaves do Ouro
    $sql_grupo_chaves = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
    $stmt_grupo_chaves = executeQuery($pdo, $sql_grupo_chaves, [$torneio_id, "2ª Fase - Ouro - Chaves"]);
    $grupo_chaves_existente = $stmt_grupo_chaves ? $stmt_grupo_chaves->fetch() : null;
    
    if ($grupo_chaves_existente) {
        $grupo_chaves_id = (int)$grupo_chaves_existente['id'];
    } else {
        $sql_insert_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
        executeQuery($pdo, $sql_insert_grupo, [$torneio_id, "2ª Fase - Ouro - Chaves", 150]);
        $grupo_chaves_id = (int)$pdo->lastInsertId();
    }
    
    $partidas_criadas = [];
    
    // Semi-final 1: 1º Ouro A vs 2º Ouro B (cruzado)
    if ($tem_grupo_id_partidas) {
        if ($tem_tipo_fase) {
            $sql_semi1 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                         VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada', 'Semi-Final')";
            executeQuery($pdo, $sql_semi1, [$torneio_id, $primeiro_ouro_a, $segundo_ouro_b, $grupo_chaves_id]);
        } else {
            $sql_semi1 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status) 
                         VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada')";
            executeQuery($pdo, $sql_semi1, [$torneio_id, $primeiro_ouro_a, $segundo_ouro_b, $grupo_chaves_id]);
        }
        $partidas_criadas[] = [
            'semi' => 1,
            'time1' => $melhores_ouro_a[0],
            'time2' => $melhores_ouro_b[1]
        ];
    }
    
    // Semi-final 2: 1º Ouro B vs 2º Ouro A (cruzado)
    if ($tem_grupo_id_partidas) {
        if ($tem_tipo_fase) {
            $sql_semi2 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                         VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada', 'Semi-Final')";
            executeQuery($pdo, $sql_semi2, [$torneio_id, $primeiro_ouro_b, $segundo_ouro_a, $grupo_chaves_id]);
        } else {
            $sql_semi2 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status) 
                         VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada')";
            executeQuery($pdo, $sql_semi2, [$torneio_id, $primeiro_ouro_b, $segundo_ouro_a, $grupo_chaves_id]);
        }
        $partidas_criadas[] = [
            'semi' => 2,
            'time1' => $melhores_ouro_b[0],
            'time2' => $melhores_ouro_a[1]
        ];
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Semi-finais geradas com sucesso! 2 partidas criadas.",
        'partidas' => $partidas_criadas,
        'classificacao' => [
            'ouro_a' => $melhores_ouro_a,
            'ouro_b' => $melhores_ouro_b
        ]
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerar semi-finais Ouro A e B: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao gerar semi-finais: ' . $e->getMessage()
    ]);
}
?>

