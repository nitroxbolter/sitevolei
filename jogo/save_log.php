<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'metodo nao permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['text'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'log vazio ou invalido']);
    exit;
}

$filename = isset($data['filename']) ? basename((string) $data['filename']) : 'volei-log.txt';
$text = (string) $data['text'];
$logPath = __DIR__ . DIRECTORY_SEPARATOR . 'log.txt';

$entry = "==============================\n";
$entry .= "arquivo: {$filename}\n";
$entry .= $text;
$entry .= "\n\n";

$written = file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'nao foi possivel gravar log.txt']);
    exit;
}

echo json_encode(['ok' => true, 'file' => 'log.txt']);
