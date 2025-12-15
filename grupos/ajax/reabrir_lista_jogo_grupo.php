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
if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido.']);
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

// Verificar se o jogo já tem times criados ou partidas iniciadas
if (!empty($jogo['modalidade']) && ($jogo['status'] === 'Times Criados' || $jogo['status'] === 'Em Andamento')) {
    echo json_encode(['success' => false, 'message' => 'Não é possível reabrir a lista. O jogo já tem times criados ou partidas em andamento.']);
    exit();
}

// Reabrir lista
$sql = "UPDATE grupo_jogos SET lista_aberta = 1, status = 'Lista Aberta' WHERE id = ?";
$result = executeQuery($pdo, $sql, [$jogo_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Lista reaberta com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao reabrir lista.']);
}
?>

