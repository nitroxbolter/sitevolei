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

// Verificar se existem times
$sql = "SELECT COUNT(*) AS total FROM torneio_times WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$total_times = $stmt ? (int)$stmt->fetch()['total'] : 0;

if ($total_times === 0) {
    echo json_encode(['success' => false, 'message' => 'Não há times para excluir.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Primeiro, remover todos os integrantes dos times deste torneio
    $sql = "DELETE tti FROM torneio_time_integrantes tti
            INNER JOIN torneio_times tt ON tt.id = tti.time_id
            WHERE tt.torneio_id = ?";
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    
    // Depois, remover todos os times
    $sql = "DELETE FROM torneio_times WHERE torneio_id = ?";
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Todos os times ({$total_times}) e seus integrantes foram excluídos com sucesso!"]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao limpar times: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao limpar times: ' . $e->getMessage()]);
}
?>

