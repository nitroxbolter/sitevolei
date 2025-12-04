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

// Aprovar membro
$sql = "UPDATE grupo_membros SET ativo = 1 WHERE grupo_id = ? AND usuario_id = ?";
$result = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);

if ($result) {
    // Notificar usuário aprovado
    try {
        $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($st && $st->fetch()) {
            $titulo = 'Solicitação de grupo aprovada';
            $msg = 'Sua entrada no grupo #'.(int)$grupo_id.' foi aprovada.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$usuario_id, $titulo, $msg]);
        }
    } catch (Exception $e) {}
}

echo json_encode(['success' => (bool)$result, 'message' => $result ? 'Solicitação aprovada.' : 'Erro ao aprovar.']);
?>


