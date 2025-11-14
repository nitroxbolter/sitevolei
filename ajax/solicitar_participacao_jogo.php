<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
if ($jogo_id <= 0) { echo json_encode(['success'=>false,'message'=>'Jogo inválido']); exit(); }

// Não permitir o criador solicitar
$stmt = executeQuery($pdo, "SELECT criado_por FROM jogos WHERE id = ?", [$jogo_id]);
$row = $stmt ? $stmt->fetch() : null;
if (!$row) { echo json_encode(['success'=>false,'message'=>'Jogo não encontrado']); exit(); }
if ((int)$row['criado_por'] === (int)$_SESSION['user_id']) {
    echo json_encode(['success'=>false,'message'=>'Você é o criador do jogo']);
    exit();
}

// Inserir/atualizar como Pendente
$sql = "INSERT INTO confirmacoes_presenca (jogo_id, usuario_id, status) VALUES (?, ?, 'Pendente')
        ON DUPLICATE KEY UPDATE status = 'Pendente'";
$ok = executeQuery($pdo, $sql, [$jogo_id, $_SESSION['user_id']]);

// Notificar criador do jogo
if ($ok) {
    try {
        $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($st && $st->fetch()) {
            $titulo = 'Solicitação de participação no jogo';
            $msg = 'Você recebeu uma solicitação de entrada no seu jogo #'.(int)$jogo_id.'.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$row['criado_por'], $titulo, $msg]);
        }
    } catch (Exception $e) {}
}

echo json_encode(['success'=>(bool)$ok]);
exit();
?>


