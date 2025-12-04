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
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar permissão - apenas o criador pode encerrar
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

// Verificar se é o criador do torneio
$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
if (!$sou_criador) {
    echo json_encode(['success' => false, 'message' => 'Apenas o criador do torneio pode encerrá-lo.']);
    exit();
}

// Verificar se já está finalizado
if ($torneio['status'] === 'Finalizado') {
    echo json_encode(['success' => false, 'message' => 'Este torneio já está finalizado.']);
    exit();
}

// Encerrar torneio
try {
    $sql_update = "UPDATE torneios SET status = 'Finalizado', data_fim = NOW() WHERE id = ?";
    $stmt_update = executeQuery($pdo, $sql_update, [$torneio_id]);
    
    if ($stmt_update) {
        echo json_encode([
            'success' => true,
            'message' => 'Torneio encerrado com sucesso! O torneio agora está apenas para visualização.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao encerrar torneio.']);
    }
} catch (Exception $e) {
    error_log("Erro ao encerrar torneio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao encerrar torneio: ' . $e->getMessage()]);
}
?>

