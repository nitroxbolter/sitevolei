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

$usuario = getUserById($pdo, (int) $_SESSION['user_id']);
if (!$usuario || strcasecmp((string) ($usuario['email'] ?? ''), 'admin@gmail.com') !== 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'debug permitido somente para admin']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['text'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'debug vazio ou invalido']);
    exit;
}

$text = trim((string) $data['text']);
if ($text === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'debug vazio']);
    exit;
}

if (strlen($text) > 500000) {
    $text = substr($text, 0, 500000) . "\n\n[debug cortado: limite de 500 KB]\n";
}

$filename = isset($data['filename']) ? basename((string) $data['filename']) : 'volei-debug.log';
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
$filename = trim($filename, '.-');
if ($filename === '') $filename = 'volei-debug.log';
if (!preg_match('/\.(txt|log)$/i', $filename)) $filename .= '.log';

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'debug-logs';
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'nao foi possivel criar pasta de debug']);
    exit;
}

$path = $dir . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . $filename;
$entry = $text . "\n";

$written = file_put_contents($path, $entry, LOCK_EX);
if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'nao foi possivel gravar debug']);
    exit;
}

chmod($path, 0640);
echo json_encode(['ok' => true, 'file' => 'debug-logs/' . basename($path)]);
