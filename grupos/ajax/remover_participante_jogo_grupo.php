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

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$participante_id = (int)($_POST['participante_id'] ?? 0);

if ($jogo_id <= 0 || $participante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se o jogo existe e se o usuário é admin do grupo
$sql = "SELECT gj.*, g.administrador_id 
        FROM grupo_jogos gj
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gj.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado.']);
    exit();
}

$sou_admin = ((int)$jogo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se o participante existe
$sql = "SELECT usuario_id FROM grupo_jogo_participantes WHERE id = ? AND jogo_id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id, $jogo_id]);
$participante = $stmt ? $stmt->fetch() : false;

if (!$participante) {
    echo json_encode(['success' => false, 'message' => 'Participante não encontrado.']);
    exit();
}

// Remover participante (e também de times se estiver em algum)
$pdo->beginTransaction();
try {
    // Remover de times primeiro
    $sql = "DELETE FROM grupo_jogo_time_integrantes WHERE participante_id = ?";
    executeQuery($pdo, $sql, [$participante_id]);
    
    // Remover participante
    $sql = "DELETE FROM grupo_jogo_participantes WHERE id = ?";
    executeQuery($pdo, $sql, [$participante_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Participante removido com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao remover participante: ' . $e->getMessage()]);
}
?>

