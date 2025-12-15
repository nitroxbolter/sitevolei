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

$time_id = (int)($_POST['time_id'] ?? 0);
$nome = sanitizar($_POST['nome'] ?? '');
$cor = sanitizar($_POST['cor'] ?? '#007bff');

if ($time_id <= 0 || empty($nome)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se o time existe e se o usuário é admin do grupo
$sql = "SELECT gjt.*, g.administrador_id 
        FROM grupo_jogo_times gjt
        JOIN grupo_jogos gj ON gj.id = gjt.jogo_id
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gjt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
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

// Atualizar time
$sql = "UPDATE grupo_jogo_times SET nome = ?, cor = ? WHERE id = ?";
$result = executeQuery($pdo, $sql, [$nome, $cor, $time_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Time atualizado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar time.']);
}
?>

