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

// Verificar se a coluna existe
try {
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'inscricoes_abertas'");
    $coluna_existe = $columnsQuery && $columnsQuery->rowCount() > 0;
    
    if (!$coluna_existe) {
        echo json_encode(['success' => false, 'message' => 'A funcionalidade de inscrições não está disponível. Execute o script SQL primeiro.']);
        exit();
    }
    
    // Atualizar campo
    $sql_update = "UPDATE torneios SET inscricoes_abertas = ? WHERE id = ?";
    $stmt_update = executeQuery($pdo, $sql_update, [$inscricoes_abertas, $torneio_id]);
    
    if ($stmt_update) {
        $mensagem = $inscricoes_abertas 
            ? 'Inscrições abertas com sucesso! Os usuários poderão solicitar participação no torneio.' 
            : 'Inscrições fechadas. Os usuários não poderão mais solicitar participação.';
        echo json_encode(['success' => true, 'message' => $mensagem, 'inscricoes_abertas' => $inscricoes_abertas]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar inscrições.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>

