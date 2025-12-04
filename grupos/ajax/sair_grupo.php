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
$usuario_id = (int)$_SESSION['user_id'];

// Validar grupo
$sql = "SELECT id, administrador_id FROM grupos WHERE id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo não encontrado.']);
    exit();
}

// Verificar se é o administrador do grupo
if ((int)$grupo['administrador_id'] === $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'O administrador do grupo não pode sair. Transfira a administração primeiro.']);
    exit();
}

// Verificar se é membro ativo
$sql = "SELECT id, ativo FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
$membro = $stmt ? $stmt->fetch() : false;

if (!$membro || (int)$membro['ativo'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Você não é membro ativo deste grupo.']);
    exit();
}

// Remover membro (deletar registro)
$sql = "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?";
$result = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Você saiu do grupo com sucesso.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao sair do grupo.']);
}
?>
