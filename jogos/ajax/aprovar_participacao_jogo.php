<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);
if ($jogo_id <= 0 || $usuario_id <= 0) { echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit(); }

// Verificar se solicitante é criador
$stmt = executeQuery($pdo, "SELECT criado_por, max_jogadores FROM jogos WHERE id = ?", [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : null;
if (!$jogo || (int)$jogo['criado_por'] !== (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Sem permissão']); exit(); }

// Verificar se ainda há vagas considerando confirmados (exclui criador)
$stmt = executeQuery($pdo, "SELECT SUM(CASE WHEN status='Confirmado' THEN 1 ELSE 0 END) AS conf FROM confirmacoes_presenca WHERE jogo_id = ?", [$jogo_id]);
$row = $stmt ? $stmt->fetch() : ['conf'=>0];
$confirmadosSemCriador = max(0, (int)$row['conf'] - 1);
if ($confirmadosSemCriador >= (int)$jogo['max_jogadores']) {
    echo json_encode(['success'=>false,'message'=>'Sem vagas disponíveis']);
    exit();
}

// Aprovar
$ok = executeQuery($pdo, "UPDATE confirmacoes_presenca SET status='Confirmado' WHERE jogo_id = ? AND usuario_id = ?", [$jogo_id, $usuario_id]);

if ($ok) {
    // Notificar usuário aceito no jogo
    try {
        $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($st && $st->fetch()) {
            $titulo = 'Você foi aceito no jogo';
            $msg = 'Sua solicitação para o jogo #'.(int)$jogo_id.' foi aprovada pelo criador.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$usuario_id, $titulo, $msg]);
        }
    } catch (Exception $e) {}
}

echo json_encode(['success'=>(bool)$ok]);
exit();
?>


