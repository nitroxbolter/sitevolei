<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$time_id = (int)($_POST['time_id'] ?? 0);
if ($time_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Time inválido.']);
    exit();
}

// Verificar permissão
$sql = "SELECT tor.*, tor.criado_por, g.administrador_id
        FROM torneio_times tt
        JOIN torneios tor ON tor.id = tt.torneio_id
        LEFT JOIN grupos g ON g.id = tor.grupo_id
        WHERE tt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
$time = $stmt ? $stmt->fetch() : false;
if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

$sou_criador = ((int)$time['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $time['administrador_id'] && ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Excluir time (os integrantes serão removidos por CASCADE)
$sql = "DELETE FROM torneio_times WHERE id = ?";
$result = executeQuery($pdo, $sql, [$time_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Time excluído com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir time.']);
}
?>

