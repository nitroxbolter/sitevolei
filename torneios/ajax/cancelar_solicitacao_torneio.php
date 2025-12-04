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

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
$usuario_id = (int)$_SESSION['user_id'];

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar se existe uma solicitação pendente do usuário
$sql_solicitacao = "SELECT id, status FROM torneio_solicitacoes WHERE torneio_id = ? AND usuario_id = ?";
$stmt_solicitacao = executeQuery($pdo, $sql_solicitacao, [$torneio_id, $usuario_id]);
$solicitacao = $stmt_solicitacao ? $stmt_solicitacao->fetch() : false;

if (!$solicitacao) {
    echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada.']);
    exit();
}

if ($solicitacao['status'] !== 'Pendente') {
    echo json_encode(['success' => false, 'message' => 'Apenas solicitações pendentes podem ser canceladas.']);
    exit();
}

// Cancelar solicitação (marcar como Rejeitada pelo próprio usuário)
try {
    $sql_update = "UPDATE torneio_solicitacoes SET status = 'Rejeitada' WHERE id = ? AND usuario_id = ?";
    $stmt_update = executeQuery($pdo, $sql_update, [$solicitacao['id'], $usuario_id]);
    
    if ($stmt_update && $stmt_update->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Solicitação cancelada com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar solicitação.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cancelar solicitação: ' . $e->getMessage()]);
}
?>

