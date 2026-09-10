<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$grupo_id = (int)($_GET['grupo_id'] ?? $_POST['grupo_id'] ?? 0);

// Verificar se usuário atual pode gerenciar o grupo
$sql = "SELECT administrador_id FROM grupos WHERE id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
$pode_gerenciar = $grupo && ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id'] || isAdmin($pdo, $_SESSION['user_id']));
if (!$pode_gerenciar) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

$sql = "SELECT u.id, u.nome, u.email, u.telefone, u.nivel, u.reputacao, gm.usuario_id
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 0
        ORDER BY u.data_cadastro DESC";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$solicitacoes = $stmt ? $stmt->fetchAll() : [];

echo json_encode(['success' => true, 'solicitacoes' => $solicitacoes]);
?>


