<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$status = sanitizar($_POST['status'] ?? '');
$usuario_id = (int)$_SESSION['user_id'];

if ($jogo_id <= 0 || !in_array($status, ['Confirmado', 'Ausente'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = executeQuery($pdo, "SELECT * FROM jogos WHERE id = ? AND status = 'Aberto' AND data_jogo > NOW() FOR UPDATE", [$jogo_id]);
    $jogo = $stmt ? $stmt->fetch() : false;

    if (!$jogo) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Jogo não encontrado ou não disponível']);
        exit();
    }

    if (!empty($jogo['grupo_id'])) {
        $sql = "SELECT gm.id FROM grupo_membros gm WHERE gm.usuario_id = ? AND gm.grupo_id = ? AND gm.ativo = 1";
        $stmt = executeQuery($pdo, $sql, [$usuario_id, (int)$jogo['grupo_id']]);
        $membro = $stmt ? $stmt->fetch() : false;

        if (!$membro) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Você não é membro deste grupo']);
            exit();
        }
    }

    $stmtAtual = executeQuery($pdo, "SELECT status FROM confirmacoes_presenca WHERE jogo_id = ? AND usuario_id = ? FOR UPDATE", [$jogo_id, $usuario_id]);
    $presencaAtual = $stmtAtual ? $stmtAtual->fetch() : false;

    if ($status === 'Confirmado' && (!$presencaAtual || $presencaAtual['status'] !== 'Confirmado')) {
        $stmtCount = executeQuery($pdo, "SELECT COUNT(*) AS total FROM confirmacoes_presenca WHERE jogo_id = ? AND status = 'Confirmado'", [$jogo_id]);
        $confirmados = $stmtCount ? (int)($stmtCount->fetch()['total'] ?? 0) : 0;
        if ($confirmados >= (int)$jogo['max_jogadores']) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Sem vagas disponíveis']);
            exit();
        }
    }

    $sql = "INSERT INTO confirmacoes_presenca (jogo_id, usuario_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = ?";
    $result = executeQuery($pdo, $sql, [$jogo_id, $usuario_id, $status, $status]);

    if (!$result) {
        throw new Exception('Erro ao processar presença.');
    }

    recalcularVagasJogo($pdo, $jogo_id);
    $pdo->commit();

    $mensagem = $status === 'Confirmado' ? 'Presença confirmada com sucesso!' : 'Presença cancelada com sucesso!';
    echo json_encode(['success' => true, 'message' => $mensagem]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao confirmar presença: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}
?>
