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
$inscricoes_abertas = isset($_POST['inscricoes_abertas']) && $_POST['inscricoes_abertas'] == '1' ? 1 : 0;

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar permissão (criador ou admin)
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;

if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);

if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

try {
    if ($inscricoes_abertas && !in_array($torneio['status'], ['Criado', 'Inscrições Abertas'], true)) {
        echo json_encode(['success' => false, 'message' => 'Não é possível abrir inscrições após o início ou encerramento do torneio.']);
        exit();
    }

    $novo_status = $torneio['status'];
    if ($inscricoes_abertas && $torneio['status'] === 'Criado') {
        $novo_status = 'Inscrições Abertas';
    } elseif (!$inscricoes_abertas && $torneio['status'] === 'Inscrições Abertas') {
        $novo_status = 'Criado';
    }

    $sql_update = "UPDATE torneios SET inscricoes_abertas = ?, status = ? WHERE id = ?";
    $stmt_update = executeQuery($pdo, $sql_update, [$inscricoes_abertas, $novo_status, $torneio_id]);
    
    if ($stmt_update) {
        $mensagem = $inscricoes_abertas 
            ? 'Inscrições abertas com sucesso! Os usuários poderão solicitar participação no torneio.' 
            : 'Inscrições fechadas. Os usuários não poderão mais solicitar participação.';
        echo json_encode([
            'success' => true,
            'message' => $mensagem,
            'inscricoes_abertas' => $inscricoes_abertas,
            'status' => $novo_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar inscrições.']);
    }
} catch (Exception $e) {
    error_log('Erro ao atualizar inscrições: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar as inscrições.']);
}
?>

