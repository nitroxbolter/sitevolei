<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'ok' => false,
    'error' => 'endpoint antigo desativado; recarregue o jogo',
], JSON_UNESCAPED_UNICODE);
