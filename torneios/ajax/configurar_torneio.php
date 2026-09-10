<?php
ob_start();
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

function responderConfiguracaoTorneio($success, $message, $extra = [], $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit();
}

if (!isLoggedIn()) {
    responderConfiguracaoTorneio(false, 'Você precisa estar logado.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderConfiguracaoTorneio(false, 'Método não permitido.', [], 405);
}

$torneio_id = isset($_POST['torneio_id']) ? (int)$_POST['torneio_id'] : 0;
if ($torneio_id <= 0) {
    responderConfiguracaoTorneio(false, 'Torneio inválido.', ['error_code' => 'TORNEIO_ID_INVALIDO'], 422);
}

$max_participantes = isset($_POST['max_participantes']) && $_POST['max_participantes'] !== '' ? (int)$_POST['max_participantes'] : null;
$quantidade_times = isset($_POST['quantidade_times']) && $_POST['quantidade_times'] !== '' ? (int)$_POST['quantidade_times'] : null;
$integrantes_por_time = isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' ? (int)$_POST['integrantes_por_time'] : null;

try {
    $stmt = executeQuery(
        $pdo,
        "SELECT t.*, g.administrador_id
         FROM torneios t
         LEFT JOIN grupos g ON g.id = t.grupo_id
         WHERE t.id = ?",
        [$torneio_id]
    );
    $torneio = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$torneio) {
        responderConfiguracaoTorneio(false, 'Torneio não encontrado.', ['error_code' => 'TORNEIO_NAO_ENCONTRADO'], 404);
    }

    if (!podeGerenciarTorneio($pdo, $torneio_id, $_SESSION['user_id'])) {
        responderConfiguracaoTorneio(false, 'Sem permissão.', [], 403);
    }

    $config = [
        'max_participantes' => $max_participantes,
        'quantidade_times' => $quantidade_times,
        'integrantes_por_time' => $integrantes_por_time
    ];
    $erroValidacao = validarConfiguracaoTorneio($pdo, $torneio, $config);
    if ($erroValidacao !== null) {
        responderConfiguracaoTorneio(false, $erroValidacao, [], 422);
    }

    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery ? $columnsQuery->fetchAll(PDO::FETCH_COLUMN) : [];

    $sets = [];
    $params = [];

    if ($max_participantes !== null) {
        if (in_array('max_participantes', $columns, true)) {
            $sets[] = 'max_participantes = ?';
            $params[] = $max_participantes;
        }
        if (in_array('quantidade_participantes', $columns, true)) {
            $sets[] = 'quantidade_participantes = ?';
            $params[] = $max_participantes;
        }
    }

    if ($quantidade_times !== null && in_array('quantidade_times', $columns, true)) {
        $sets[] = 'quantidade_times = ?';
        $params[] = $quantidade_times;
    }

    if ($integrantes_por_time !== null && in_array('integrantes_por_time', $columns, true)) {
        $sets[] = 'integrantes_por_time = ?';
        $params[] = $integrantes_por_time;
    }

    if (empty($sets)) {
        responderConfiguracaoTorneio(false, 'Nenhuma configuração para atualizar.', [], 422);
    }

    $params[] = $torneio_id;
    executeQuery($pdo, 'UPDATE torneios SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

    responderConfiguracaoTorneio(true, 'Configurações salvas com sucesso!');
} catch (Exception $e) {
    error_log('Erro ao atualizar configurações do torneio: ' . $e->getMessage());
    responderConfiguracaoTorneio(false, 'Não foi possível salvar as configurações agora.', [], 500);
}
?>
