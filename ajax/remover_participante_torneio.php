<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

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

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneio_participantes tp
        JOIN torneios t ON t.id = tp.torneio_id
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE tp.id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Participante não encontrado.']);
    exit();
}

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
    echo json_encode(['success' => true, 'message' => 'Participante removido com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao remover participante.']);
}
?>

