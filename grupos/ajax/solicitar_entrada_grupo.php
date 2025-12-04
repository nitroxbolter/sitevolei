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

// Já é membro ativo?
$sql = "SELECT id, ativo FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
$membro = $stmt ? $stmt->fetch() : false;

if ($membro && (int)$membro['ativo'] === 1) {
    echo json_encode(['success' => false, 'message' => 'Você já é membro deste grupo.']);
    exit();
}

if ($membro && (int)$membro['ativo'] === 0) {
    echo json_encode(['success' => true, 'message' => 'Sua solicitação já está pendente. Aguarde aprovação.']);
    exit();
}

// Criar solicitação (ativo = 0)
$sql = "INSERT INTO grupo_membros (grupo_id, usuario_id, ativo) VALUES (?, ?, 0)";
$result = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);

if ($result) {
    // Notificar admin do grupo, se notificações existirem
    try {
        $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($st && $st->fetch()) {
            $titulo = 'Nova solicitação no grupo';
            $msg = 'Você recebeu uma solicitação de entrada no grupo #' . (int)$grupo_id . '.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$grupo['administrador_id'], $titulo, $msg]);
        }
    } catch (Exception $e) {}
    echo json_encode(['success' => true, 'message' => 'Solicitação enviada ao administrador do grupo.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar solicitação.']);
}
?>


