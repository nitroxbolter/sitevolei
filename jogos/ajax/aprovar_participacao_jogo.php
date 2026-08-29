<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);
if ($jogo_id <= 0 || $usuario_id <= 0) { echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit(); }

$pdo->beginTransaction();
try {
    // Serializa aprovações do mesmo jogo para respeitar o limite de vagas.
    $stmt = executeQuery($pdo, "SELECT criado_por, max_jogadores FROM jogos WHERE id = ? FOR UPDATE", [$jogo_id]);
    $jogo = $stmt ? $stmt->fetch() : null;
    if (!$jogo || (int)$jogo['criado_por'] !== (int)$_SESSION['user_id']) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Sem permissão']);
        exit();
    }

    $stmt = executeQuery($pdo, "SELECT status FROM confirmacoes_presenca WHERE jogo_id = ? AND usuario_id = ? FOR UPDATE", [$jogo_id, $usuario_id]);
    $solicitacao = $stmt ? $stmt->fetch() : null;
    if (!$solicitacao || $solicitacao['status'] !== 'Pendente') {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Solicitação não encontrada ou já respondida']);
        exit();
    }

    // Verificar se ainda há vagas considerando confirmados (exclui criador)
    $stmt = executeQuery($pdo, "SELECT SUM(CASE WHEN status='Confirmado' THEN 1 ELSE 0 END) AS conf FROM confirmacoes_presenca WHERE jogo_id = ?", [$jogo_id]);
    $row = $stmt ? $stmt->fetch() : ['conf'=>0];
    $confirmadosSemCriador = max(0, (int)$row['conf'] - 1);
    if ($confirmadosSemCriador >= (int)$jogo['max_jogadores']) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Sem vagas disponíveis']);
        exit();
    }

    $ok = executeQuery($pdo, "UPDATE confirmacoes_presenca SET status='Confirmado' WHERE jogo_id = ? AND usuario_id = ? AND status = 'Pendente'", [$jogo_id, $usuario_id]);
    if (!$ok || $ok->rowCount() === 0) {
        throw new Exception('Não foi possível aprovar a solicitação.');
    }

    $pdo->commit();

    // Notificar usuário aceito no jogo
    try {
        $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($st && $st->fetch()) {
            $titulo = 'Você foi aceito no jogo';
            $msg = 'Sua solicitação para o jogo #'.(int)$jogo_id.' foi aprovada pelo criador.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$usuario_id, $titulo, $msg]);
        }
    } catch (Exception $e) {}
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit();
}

echo json_encode(['success'=>true]);
exit();
?>


