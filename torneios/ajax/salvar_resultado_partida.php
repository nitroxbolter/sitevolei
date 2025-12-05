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

$partida_id = (int)($_POST['partida_id'] ?? 0);
$pontos_time1 = (int)($_POST['pontos_time1'] ?? 0);
$pontos_time2 = (int)($_POST['pontos_time2'] ?? 0);
$status = $_POST['status'] ?? 'Agendada';

if ($partida_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Partida inválida.']);
    exit();
}

if (!in_array($status, ['Agendada', 'Em Andamento', 'Finalizada'])) {
    echo json_encode(['success' => false, 'message' => 'Status inválido.']);
    exit();
}

// Buscar partida
$sql = "SELECT * FROM torneio_partidas WHERE id = ?";
$stmt = executeQuery($pdo, $sql, [$partida_id]);
$partida = $stmt ? $stmt->fetch() : false;

if (!$partida) {
    echo json_encode(['success' => false, 'message' => 'Partida não encontrada.']);
    exit();
}

// Verificar permissão
$sql_torneio = "SELECT t.*, g.administrador_id 
                FROM torneios t
                LEFT JOIN grupos g ON g.id = t.grupo_id
                WHERE t.id = ?";
$stmt_torneio = executeQuery($pdo, $sql_torneio, [$partida['torneio_id']]);
$torneio = $stmt_torneio ? $stmt_torneio->fetch() : false;

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

$pdo->beginTransaction();

try {
    // Determinar vencedor se a partida foi finalizada
    $vencedor_id = null;
    if ($status === 'Finalizada') {
        if ($pontos_time1 > $pontos_time2) {
            $vencedor_id = $partida['time1_id'];
        } elseif ($pontos_time2 > $pontos_time1) {
            $vencedor_id = $partida['time2_id'];
        }
        // Se empate, vencedor_id fica null
    }
    
    // Atualizar partida
    $sql_update = "UPDATE torneio_partidas 
                   SET pontos_time1 = ?, pontos_time2 = ?, vencedor_id = ?, status = ?
                   WHERE id = ?";
    executeQuery($pdo, $sql_update, [$pontos_time1, $pontos_time2, $vencedor_id, $status, $partida_id]);
    
    // Se a partida foi finalizada, atualizar classificação
    if ($status === 'Finalizada') {
        // Buscar classificação atual dos times
        $sql_class1 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        $stmt_class1 = executeQuery($pdo, $sql_class1, [$partida['torneio_id'], $partida['time1_id']]);
        $class1 = $stmt_class1 ? $stmt_class1->fetch() : false;
        
        $sql_class2 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        $stmt_class2 = executeQuery($pdo, $sql_class2, [$partida['torneio_id'], $partida['time2_id']]);
        $class2 = $stmt_class2 ? $stmt_class2->fetch() : false;
        
        // Se não existir, criar
        if (!$class1) {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time1_id']]);
            $class1 = ['vitorias' => 0, 'derrotas' => 0, 'empates' => 0, 'pontos_pro' => 0, 'pontos_contra' => 0];
        }
        
        if (!$class2) {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time2_id']]);
            $class2 = ['vitorias' => 0, 'derrotas' => 0, 'empates' => 0, 'pontos_pro' => 0, 'pontos_contra' => 0];
        }
        
        // Recalcular todas as estatísticas do torneio (incluindo jogos eliminatórios)
        // Usar UNION para combinar dados de torneio_partidas e torneio_chaves_times
        $sql_recalc = "SELECT 
            tc.time_id,
            COUNT(CASE WHEN jogo.vencedor_id = tc.time_id THEN 1 END) as vitorias,
            COUNT(CASE WHEN jogo.vencedor_id IS NOT NULL AND jogo.vencedor_id != tc.time_id AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as derrotas,
            COUNT(CASE WHEN jogo.vencedor_id IS NULL AND jogo.status = 'Finalizada' AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as empates,
            SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END) + 
            SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END) as pontos_pro,
            SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END) + 
            SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END) as pontos_contra
        FROM torneio_classificacao tc
        LEFT JOIN (
            SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id
            FROM torneio_partidas
            WHERE status = 'Finalizada'
            UNION ALL
            SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id
            FROM torneio_chaves_times
            WHERE status = 'Finalizada'
        ) jogo ON (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) 
            AND jogo.torneio_id = tc.torneio_id
        WHERE tc.torneio_id = ?
        GROUP BY tc.time_id";
        
        $stmt_recalc = executeQuery($pdo, $sql_recalc, [$partida['torneio_id']]);
        $recalcs = $stmt_recalc ? $stmt_recalc->fetchAll() : [];
        
        foreach ($recalcs as $recalc) {
            $saldo = (int)$recalc['pontos_pro'] - (int)$recalc['pontos_contra'];
            $average = (int)$recalc['pontos_contra'] > 0 ? (float)$recalc['pontos_pro'] / (float)$recalc['pontos_contra'] : ((int)$recalc['pontos_pro'] > 0 ? 999.99 : 0.00);
            $pontos_total = ((int)$recalc['vitorias'] * 3) + ((int)$recalc['empates'] * 1);
            
            $sql_update_class = "UPDATE torneio_classificacao 
                                SET vitorias = ?, derrotas = ?, empates = ?, 
                                    pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                    average = ?, pontos_total = ?
                                WHERE torneio_id = ? AND time_id = ?";
            executeQuery($pdo, $sql_update_class, [
                (int)$recalc['vitorias'],
                (int)$recalc['derrotas'],
                (int)$recalc['empates'],
                (int)$recalc['pontos_pro'],
                (int)$recalc['pontos_contra'],
                $saldo,
                $average,
                $pontos_total,
                $partida['torneio_id'],
                $recalc['time_id']
            ]);
        }
        
        // Atualizar posições
        $sql_pos = "SELECT time_id FROM torneio_classificacao 
                   WHERE torneio_id = ? 
                   ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
        $stmt_pos = executeQuery($pdo, $sql_pos, [$partida['torneio_id']]);
        $posicoes = $stmt_pos ? $stmt_pos->fetchAll() : [];
        
        $posicao = 1;
        foreach ($posicoes as $pos) {
            $sql_update_pos = "UPDATE torneio_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ?";
            executeQuery($pdo, $sql_update_pos, [$posicao++, $partida['torneio_id'], $pos['time_id']]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Resultado salvo com sucesso!'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao salvar resultado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar resultado: ' . $e->getMessage()]);
}
?>

