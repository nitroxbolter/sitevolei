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

$grupo_id = (int)($_POST['grupo_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);

// Verificar se usuário atual é admin do grupo
$sql = "SELECT id FROM grupos WHERE id = ? AND administrador_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id, $_SESSION['user_id']]);
if (!$stmt || !$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

// Rejeitar (remover pendência)
$sql = "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 0";
$result = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);

echo json_encode(['success' => (bool)$result, 'message' => $result ? 'Solicitação rejeitada.' : 'Erro ao rejeitar.']);
?>


