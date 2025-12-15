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

// Capturar torneio_id de múltiplas fontes para garantir que seja encontrado
$torneio_id = 0;
if (isset($_POST['torneio_id']) && $_POST['torneio_id'] > 0) {
    $torneio_id = (int)$_POST['torneio_id'];
} elseif (isset($_GET['torneio_id']) && $_GET['torneio_id'] > 0) {
    $torneio_id = (int)$_GET['torneio_id'];
} elseif (isset($_REQUEST['torneio_id']) && $_REQUEST['torneio_id'] > 0) {
    $torneio_id = (int)$_REQUEST['torneio_id'];
} else {
    // Tentar pegar do raw input (JSON)
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $parsed = json_decode($rawInput, true);
        if (isset($parsed['torneio_id']) && $parsed['torneio_id'] > 0) {
            $torneio_id = (int)$parsed['torneio_id'];
        }
    }
}

$max_participantes = isset($_POST['max_participantes']) ? (int)$_POST['max_participantes'] : null;
$quantidade_times = isset($_POST['quantidade_times']) ? (int)$_POST['quantidade_times'] : null;
$integrantes_por_time = isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' ? (int)$_POST['integrantes_por_time'] : null;

// Debug sempre (para identificar o problema)
error_log("=== DEBUG CONFIGURAR TORNEIO ===");
error_log("POST completo: " . print_r($_POST, true));
error_log("GET completo: " . print_r($_GET, true));
error_log("REQUEST completo: " . print_r($_REQUEST, true));
error_log("torneio_id capturado: " . $torneio_id);
error_log("max_participantes: " . ($max_participantes ?? 'null'));
error_log("quantidade_times: " . ($quantidade_times ?? 'null'));
error_log("integrantes_por_time: " . ($integrantes_por_time ?? 'null'));

if ($torneio_id <= 0) {
    $rawInput = file_get_contents('php://input');
    error_log("Raw input: " . $rawInput);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Torneio inválido. ID não foi encontrado ou é inválido.',
        'error_code' => 'TORNEIO_ID_INVALIDO',
        'debug' => [
            'post_completo' => $_POST,
            'get_completo' => $_GET,
            'request_completo' => $_REQUEST,
            'torneio_id_recebido' => $torneio_id,
            'raw_input' => $rawInput,
            'sugestao' => 'Verifique se o campo hidden torneio_id está presente no formulário e se está sendo enviado corretamente.'
        ]
    ]);
    exit();
}

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    error_log("Configurar torneio - Torneio não encontrado no banco. ID buscado: " . $torneio_id);
    echo json_encode([
        'success' => false, 
        'message' => 'Torneio não encontrado no banco de dados.',
        'error_code' => 'TORNEIO_NAO_ENCONTRADO',
        'debug' => [
            'torneio_id_buscado' => $torneio_id,
            'sugestao' => 'Verifique se o torneio existe e se você tem permissão para editá-lo.'
        ]
    ]);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se há participantes cadastrados
$sql = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$totalParticipantes = $stmt ? (int)$stmt->fetch()['total'] : 0;

// Validar quantidade máxima de participantes
if ($max_participantes !== null && $max_participantes > 0) {
    if ($totalParticipantes > $max_participantes) {
        echo json_encode(['success' => false, 'message' => 'Não é possível reduzir a quantidade máxima de participantes. Existem ' . $totalParticipantes . ' participantes cadastrados.']);
        exit();
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
    
    // Atualizar quantidade_times
    if ($quantidade_times !== null && in_array('quantidade_times', $columns)) {
        $updates[] = "quantidade_times = " . (int)$quantidade_times;
    }
    
    // Atualizar integrantes_por_time
    if ($integrantes_por_time !== null && in_array('integrantes_por_time', $columns)) {
        $updates[] = "integrantes_por_time = " . (int)$integrantes_por_time;
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE torneios SET " . implode(', ', $updates) . " WHERE id = ?";
        executeQuery($pdo, $sql, [$torneio_id]);
        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma configuração para atualizar.']);
    }
} catch (Exception $e) {
    error_log("Erro ao atualizar configurações do torneio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>

