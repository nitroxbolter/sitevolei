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
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este torneio.']);
    exit();
}

// Excluir torneio (os participantes e times serão removidos por CASCADE se as constraints estiverem corretas)
try {
    $pdo->beginTransaction();
    
    // Excluir integrantes dos times primeiro
    $sql = "DELETE FROM torneio_time_integrantes WHERE time_id IN (SELECT id FROM torneio_times WHERE torneio_id = ?)";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Excluir times
    $sql = "DELETE FROM torneio_times WHERE torneio_id = ?";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Excluir participantes
    $sql = "DELETE FROM torneio_participantes WHERE torneio_id = ?";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Excluir torneio
    $sql = "DELETE FROM torneios WHERE id = ?";
    $result = executeQuery($pdo, $sql, [$torneio_id]);
    
    $pdo->commit();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Torneio excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir torneio.']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir torneio: ' . $e->getMessage()]);
}
?>

