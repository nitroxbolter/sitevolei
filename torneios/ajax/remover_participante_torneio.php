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
if ($participante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Participante inválido.']);
    exit();
}

// Verificar permissão e buscar dados do participante
$sql = "SELECT tp.*, tp.usuario_id, t.*, g.administrador_id 
        FROM torneio_participantes tp
        JOIN torneios t ON t.id = tp.torneio_id
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE tp.id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id]);
$dados = $stmt ? $stmt->fetch() : false;
if (!$dados) {
    echo json_encode(['success' => false, 'message' => 'Participante não encontrado.']);
    exit();
}

$torneio = $dados;
$usuario_id = (int)($dados['usuario_id'] ?? 0);

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Remover participante (os integrantes dos times serão removidos por CASCADE)
$sql = "DELETE FROM torneio_participantes WHERE id = ?";
$result = executeQuery($pdo, $sql, [$participante_id]);

if ($result) {
    // Enviar notificação para o usuário removido
    if ($usuario_id > 0) {
        try {
            $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
            if ($st && $st->fetch()) {
                $titulo = 'Você foi removido do torneio';
                $msg = 'Você foi removido do torneio "' . htmlspecialchars($torneio['nome']) . '" pelo administrador.';
                executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$usuario_id, $titulo, $msg]);
            }
        } catch (Exception $e) {
            // Erro ao criar notificação não deve impedir a remoção
            error_log("Erro ao criar notificação de remoção: " . $e->getMessage());
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Participante removido com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao remover participante.']);
}
?>

