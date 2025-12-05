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

$time_id = (int)($_POST['time_id'] ?? 0);
$participante_id = (int)($_POST['participante_id'] ?? 0);
$torneio_id = (int)($_POST['torneio_id'] ?? 0);
$time_numero = (int)($_POST['time_numero'] ?? 0);

// Se não tem time_id mas tem torneio_id e time_numero, criar o time primeiro
if ($time_id <= 0 && $torneio_id > 0 && $time_numero > 0) {
    // Verificar permissão
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

    $sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
    $sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
    if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
        exit();
    }

    // Verificar se já existe um time com essa ordem
    $sql = "SELECT id FROM torneio_times WHERE torneio_id = ? AND ordem = ? LIMIT 1";
    $stmt = executeQuery($pdo, $sql, [$torneio_id, $time_numero]);
    $time_existente = $stmt ? $stmt->fetch() : false;
    
    if ($time_existente) {
        $time_id = (int)$time_existente['id'];
    } else {
        // Criar novo time
        $cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6610f2'];
        $cor = $cores[($time_numero - 1) % count($cores)];
        $sql = "INSERT INTO torneio_times (torneio_id, nome, cor, ordem) VALUES (?, ?, ?, ?)";
        executeQuery($pdo, $sql, [$torneio_id, 'Time ' . $time_numero, $cor, $time_numero]);
        $time_id = (int)$pdo->lastInsertId();
    }
}

if ($time_id <= 0 || $participante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar permissão
$sql = "SELECT tor.*, tor.quantidade_times, tor.integrantes_por_time, tor.criado_por, g.administrador_id
        FROM torneio_times tt
        JOIN torneios tor ON tor.id = tt.torneio_id
        LEFT JOIN grupos g ON g.id = tor.grupo_id
        WHERE tt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
$time = $stmt ? $stmt->fetch() : false;
if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

$sou_criador = ((int)$time['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $time['administrador_id'] && ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar limite de integrantes por time
$integrantesPorTime = $time['integrantes_por_time'] ?? null;
if ($integrantesPorTime) {
    $sql = "SELECT COUNT(*) AS total FROM torneio_time_integrantes WHERE time_id = ?";
    $stmt = executeQuery($pdo, $sql, [$time_id]);
    $totalIntegrantes = $stmt ? (int)$stmt->fetch()['total'] : 0;
    
    if ($totalIntegrantes >= (int)$integrantesPorTime) {
        echo json_encode(['success' => false, 'message' => 'Limite de integrantes por time atingido.']);
        exit();
    }
}

// Verificar se participante já está em algum time
$sql = "SELECT id FROM torneio_time_integrantes WHERE participante_id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id]);
if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Participante já está em um time.']);
    exit();
}

// Adicionar integrante
$sql = "INSERT INTO torneio_time_integrantes (time_id, participante_id) VALUES (?, ?)";
$result = executeQuery($pdo, $sql, [$time_id, $participante_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Integrante adicionado ao time!', 'time_id' => $time_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar integrante.']);
}
?>

