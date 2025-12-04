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

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar permissão e obter configuração
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

$quantidade_times = $torneio['quantidade_times'] ?? null;
$integrantes_por_time = $torneio['integrantes_por_time'] ?? null;

if (!$quantidade_times || !$integrantes_por_time) {
    echo json_encode(['success' => false, 'message' => 'Configure quantidade de times e integrantes por time primeiro.']);
    exit();
}

// Buscar participantes
$sql = "SELECT * FROM torneio_participantes WHERE torneio_id = ? ORDER BY RAND()";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

if (empty($participantes)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum participante cadastrado.']);
    exit();
}

// Buscar times
$sql = "SELECT * FROM torneio_times WHERE torneio_id = ? ORDER BY ordem";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times = $stmt ? $stmt->fetchAll() : [];

if (empty($times)) {
    echo json_encode(['success' => false, 'message' => 'Crie os times primeiro.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Limpar integrantes existentes
    $sql = "DELETE FROM torneio_time_integrantes WHERE time_id IN (SELECT id FROM torneio_times WHERE torneio_id = ?)";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Sortear participantes nos times
    $integrantesPorTime = (int)$integrantes_por_time;
    $participanteIndex = 0;
    
    foreach ($times as $time) {
        for ($i = 0; $i < $integrantesPorTime && $participanteIndex < count($participantes); $i++) {
            $sql = "INSERT INTO torneio_time_integrantes (time_id, participante_id) VALUES (?, ?)";
            executeQuery($pdo, $sql, [$time['id'], $participantes[$participanteIndex]['id']]);
            $participanteIndex++;
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Times sorteados com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao sortear times: ' . $e->getMessage()]);
}
?>

