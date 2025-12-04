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
$times_data = json_decode($_POST['times_data'] ?? '[]', true);

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
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
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Limpar integrantes existentes de todos os times do torneio
    $sql = "DELETE FROM torneio_time_integrantes WHERE time_id IN (SELECT id FROM torneio_times WHERE torneio_id = ?)";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Processar cada time
    foreach ($times_data as $timeData) {
        $time_id = isset($timeData['time_id']) && $timeData['time_id'] ? (int)$timeData['time_id'] : null;
        $time_numero = (int)($timeData['numero'] ?? 0);
        $participantes_ids = $timeData['participantes'] ?? [];
        
        if ($time_numero <= 0) continue;
        
        // Se o time não existe, criar
        if (!$time_id) {
            $cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6610f2'];
            $cor = $cores[($time_numero - 1) % count($cores)];
            $sql = "INSERT INTO torneio_times (torneio_id, nome, cor, ordem) VALUES (?, ?, ?, ?)";
            executeQuery($pdo, $sql, [$torneio_id, 'Time ' . $time_numero, $cor, $time_numero]);
            $time_id = (int)$pdo->lastInsertId();
        }
        
        // Adicionar participantes ao time
        if (!empty($participantes_ids)) {
            $sql = "INSERT INTO torneio_time_integrantes (time_id, participante_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            foreach ($participantes_ids as $participante_id) {
                $participante_id = (int)$participante_id;
                if ($participante_id > 0) {
                    // Verificar se participante pertence ao torneio
                    $check = executeQuery($pdo, "SELECT id FROM torneio_participantes WHERE id = ? AND torneio_id = ?", [$participante_id, $torneio_id]);
                    if ($check && $check->fetch()) {
                        $stmt->execute([$time_id, $participante_id]);
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Times salvos com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar times: ' . $e->getMessage()]);
}
?>

