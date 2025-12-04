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

$partida_id = (int)($_POST['partida_id'] ?? 0);
if ($partida_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Partida inválida.']);
    exit();
}

// Buscar partida
$sql = "SELECT * FROM torneio_partidas WHERE id = ?";
$stmt = executeQuery($pdo, $sql, [$partida_id]);
$partida = $stmt ? $stmt->fetch() : false;

if (!$partida) {
    echo json_encode(['success' => false, 'message' => 'Partida não encontrada.']);
    exit();
}

// Verificar permissão
$sql_torneio = "SELECT t.*, g.administrador_id 
                FROM torneios t
                LEFT JOIN grupos g ON g.id = t.grupo_id
                WHERE t.id = ?";
$stmt_torneio = executeQuery($pdo, $sql_torneio, [$partida['torneio_id']]);
$torneio = $stmt_torneio ? $stmt_torneio->fetch() : false;

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

echo json_encode([
    'success' => true,
    'partida' => $partida
]);
?>

