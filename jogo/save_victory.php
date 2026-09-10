<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'metodo nao permitido']);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'usuario nao logado']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || ($data['winner'] ?? '') !== 'PLAYER') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vitoria invalida']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'sessao invalida']);
    exit;
}

$stmt = executeQuery(
    $pdo,
    'UPDATE usuarios SET pontos_jogo_volei = pontos_jogo_volei + 1 WHERE id = ? AND ativo = 1',
    [$userId]
);

if (!$stmt || $stmt->rowCount() < 1) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'usuario nao encontrado']);
    exit;
}

$stmt = executeQuery($pdo, 'SELECT pontos_jogo_volei FROM usuarios WHERE id = ?', [$userId]);
$row = $stmt ? $stmt->fetch() : false;

if (!$row) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'nao foi possivel ler pontuacao']);
    exit;
}

echo json_encode([
    'ok' => true,
    'pontos_jogo_volei' => (int) $row['pontos_jogo_volei']
]);
