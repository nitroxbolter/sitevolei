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

$participante_id = (int)($_POST['participante_id'] ?? 0);
$time_origem_id = (int)($_POST['time_origem_id'] ?? 0);
$time_destino_id = (int)($_POST['time_destino_id'] ?? 0);

if ($participante_id <= 0 || $time_origem_id <= 0 || $time_destino_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se os times existem e se o usuário é admin do grupo
$sql = "SELECT gjt.*, g.administrador_id 
        FROM grupo_jogo_times gjt
        JOIN grupo_jogos gj ON gj.id = gjt.jogo_id
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gjt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_origem_id]);
$time = $stmt ? $stmt->fetch() : false;

if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

$sou_admin = ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Remover do time origem
    $sql = "DELETE FROM grupo_jogo_time_integrantes WHERE participante_id = ? AND time_id = ?";
    executeQuery($pdo, $sql, [$participante_id, $time_origem_id]);
    
    // Adicionar ao time destino
    $sql = "INSERT INTO grupo_jogo_time_integrantes (time_id, participante_id) VALUES (?, ?)";
    executeQuery($pdo, $sql, [$time_destino_id, $participante_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Participante movido com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao mover participante: ' . $e->getMessage()]);
}
?>

