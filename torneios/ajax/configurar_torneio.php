<?php
// Iniciar output buffering para capturar erros
ob_start();

session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Função para retornar erro JSON
function returnError($message, $errorCode = null, $debug = null) {
    ob_clean();
    $response = ['success' => false, 'message' => $message];
    if ($errorCode) {
        $response['error_code'] = $errorCode;
    }
    if ($debug) {
        $response['debug'] = $debug;
    }
    echo json_encode($response);
    exit();
}

if (!isLoggedIn()) {
    returnError('Você precisa estar logado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnError('Método não permitido');
}

// Debug: Logar tudo que está sendo recebido
error_log("=== DEBUG CONFIGURAR TORNEIO ===");
error_log("POST completo: " . print_r($_POST, true));
error_log("GET completo: " . print_r($_GET, true));
error_log("REQUEST completo: " . print_r($_REQUEST, true));
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput);

// Capturar torneio_id de múltiplas fontes para garantir que seja encontrado
$torneio_id = 0;
if (isset($_POST['torneio_id'])) {
    $torneio_id_raw = $_POST['torneio_id'];
    error_log("torneio_id do POST (raw): " . var_export($torneio_id_raw, true));
    $torneio_id = (int)$torneio_id_raw;
    error_log("torneio_id do POST (int): " . $torneio_id);
    if ($torneio_id <= 0 && $torneio_id_raw !== '' && $torneio_id_raw !== null) {
        // Tentar como string se for um número válido
        if (is_numeric($torneio_id_raw)) {
            $torneio_id = (int)$torneio_id_raw;
        }
    }
} elseif (isset($_GET['torneio_id']) && $_GET['torneio_id'] > 0) {
    $torneio_id = (int)$_GET['torneio_id'];
    error_log("torneio_id do GET: " . $torneio_id);
} elseif (isset($_REQUEST['torneio_id']) && $_REQUEST['torneio_id'] > 0) {
    $torneio_id = (int)$_REQUEST['torneio_id'];
    error_log("torneio_id do REQUEST: " . $torneio_id);
} else {
    // Tentar pegar do raw input (JSON)
    if ($rawInput) {
        $parsed = json_decode($rawInput, true);
        error_log("Parsed JSON: " . print_r($parsed, true));
        if (isset($parsed['torneio_id']) && $parsed['torneio_id'] > 0) {
            $torneio_id = (int)$parsed['torneio_id'];
            error_log("torneio_id do JSON: " . $torneio_id);
        }
    }
}

error_log("torneio_id final capturado: " . $torneio_id);

$max_participantes = isset($_POST['max_participantes']) ? (int)$_POST['max_participantes'] : null;
$quantidade_times = isset($_POST['quantidade_times']) ? (int)$_POST['quantidade_times'] : null;
$integrantes_por_time = isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' ? (int)$_POST['integrantes_por_time'] : null;

if ($torneio_id <= 0) {
    error_log("ERRO: torneio_id inválido ou não encontrado. Valor: " . $torneio_id);
    error_log("POST keys: " . implode(', ', array_keys($_POST)));
    error_log("POST values: " . print_r(array_values($_POST), true));
    
    returnError(
        'Torneio inválido. ID não foi encontrado ou é inválido. Verifique os logs do servidor.',
        'TORNEIO_ID_INVALIDO',
        [
            'post_completo' => $_POST,
            'get_completo' => $_GET,
            'request_completo' => $_REQUEST,
            'torneio_id_recebido' => $torneio_id,
            'post_keys' => array_keys($_POST),
            'raw_input' => $rawInput
        ]
    );
}

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    returnError('Torneio não encontrado.', 'TORNEIO_NAO_ENCONTRADO', ['torneio_id_buscado' => $torneio_id]);
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    returnError('Sem permissão.');
}

// Verificar se há participantes cadastrados
$sql = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$totalParticipantes = $stmt ? (int)$stmt->fetch()['total'] : 0;

// Validar quantidade máxima de participantes
if ($max_participantes !== null && $max_participantes > 0) {
    if ($totalParticipantes > $max_participantes) {
        returnError('Não é possível reduzir a quantidade máxima de participantes. Existem ' . $totalParticipantes . ' participantes cadastrados.');
    }
}

try {
    // Verificar quais colunas existem na tabela
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    
    $updates = [];
    
    // Atualizar max_participantes ou quantidade_participantes
    if ($max_participantes !== null) {
        if (in_array('max_participantes', $columns)) {
            $updates[] = "max_participantes = " . (int)$max_participantes;
        }
        if (in_array('quantidade_participantes', $columns)) {
            $updates[] = "quantidade_participantes = " . (int)$max_participantes;
        }
    }
    
    // Atualizar quantidade_times (sempre atualizar se foi enviado, mesmo que seja 0)
    if (isset($_POST['quantidade_times']) && in_array('quantidade_times', $columns)) {
        $updates[] = "quantidade_times = " . (int)$quantidade_times;
    }
    
    // Atualizar integrantes_por_time (sempre atualizar se foi enviado, mesmo que seja 0)
    if (isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' && in_array('integrantes_por_time', $columns)) {
        $updates[] = "integrantes_por_time = " . (int)$integrantes_por_time;
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE torneios SET " . implode(', ', $updates) . " WHERE id = ?";
        executeQuery($pdo, $sql, [$torneio_id]);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
    } else {
        returnError('Nenhuma configuração para atualizar.');
    }
} catch (Exception $e) {
    error_log("Erro ao atualizar configurações do torneio: " . $e->getMessage());
    returnError('Erro ao salvar configurações: ' . $e->getMessage());
} catch (Error $e) {
    error_log("Erro fatal ao atualizar configurações do torneio: " . $e->getMessage());
    returnError('Erro fatal ao salvar configurações: ' . $e->getMessage());
}
?>

