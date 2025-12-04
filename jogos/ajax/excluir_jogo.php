<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit();
}

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
if ($jogo_id <= 0) { echo json_encode(['success'=>false,'message'=>'Jogo inválido']); exit(); }

// Verificar se é o criador
$stmt = executeQuery($pdo, "SELECT criado_por FROM jogos WHERE id = ?", [$jogo_id]);
$row = $stmt ? $stmt->fetch() : null;
if (!$row || (int)$row['criado_por'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Remover dependências (confirmações, partidas, times e jogadores)
    executeQuery($pdo, "DELETE FROM confirmacoes_presenca WHERE jogo_id = ?", [$jogo_id]);
    $stmtTimes = executeQuery($pdo, "SELECT id FROM times WHERE jogo_id = ?", [$jogo_id]);
    $times = $stmtTimes ? $stmtTimes->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($times)) {
        $in = implode(',', array_map('intval', $times));
        $pdo->exec("DELETE FROM time_jogadores WHERE time_id IN (".$in.")");
        $pdo->exec("DELETE FROM partidas WHERE time1_id IN (".$in.") OR time2_id IN (".$in.")");
        $pdo->exec("DELETE FROM times WHERE id IN (".$in.")");
    }
    // Remover o jogo
    executeQuery($pdo, "DELETE FROM jogos WHERE id = ?", [$jogo_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir jogo']);
}
exit();
?>


