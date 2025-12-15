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

$participante1_id = (int)($_POST['participante1_id'] ?? 0);
$participante2_id = (int)($_POST['participante2_id'] ?? 0);
$time1_id = (int)($_POST['time1_id'] ?? 0);
$time2_id = (int)($_POST['time2_id'] ?? 0);

if ($participante1_id <= 0 || $participante2_id <= 0 || $time1_id <= 0 || $time2_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se os times existem e se o usuário é admin do grupo
$sql = "SELECT gjt.*, g.administrador_id 
        FROM grupo_jogo_times gjt
        JOIN grupo_jogos gj ON gj.id = gjt.jogo_id
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gjt.id IN (?, ?)";
$stmt = executeQuery($pdo, $sql, [$time1_id, $time2_id]);
$times = $stmt ? $stmt->fetchAll() : [];

if (count($times) !== 2) {
    echo json_encode(['success' => false, 'message' => 'Times não encontrados.']);
    exit();
}

$sou_admin = ((int)$times[0]['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Verificar se os participantes estão nos times corretos antes de trocar
    $sql = "SELECT time_id FROM grupo_jogo_time_integrantes WHERE participante_id = ?";
    $stmt = executeQuery($pdo, $sql, [$participante1_id]);
    $time_p1 = $stmt ? $stmt->fetch() : false;
    
    $stmt = executeQuery($pdo, $sql, [$participante2_id]);
    $time_p2 = $stmt ? $stmt->fetch() : false;
    
    // Remover ambos dos times atuais (se existirem)
    $sql = "DELETE FROM grupo_jogo_time_integrantes WHERE participante_id IN (?, ?)";
    executeQuery($pdo, $sql, [$participante1_id, $participante2_id]);
    
    // Verificar se já existem antes de inserir
    $sql = "SELECT id FROM grupo_jogo_time_integrantes WHERE (participante_id = ? AND time_id = ?) OR (participante_id = ? AND time_id = ?)";
    $stmt = executeQuery($pdo, $sql, [$participante1_id, $time2_id, $participante2_id, $time1_id]);
    $existem = $stmt ? $stmt->fetchAll() : [];
    
    if (empty($existem)) {
        // Adicionar participante1 ao time2
        $sql = "INSERT INTO grupo_jogo_time_integrantes (time_id, participante_id) VALUES (?, ?)";
        executeQuery($pdo, $sql, [$time2_id, $participante1_id]);
        
        // Adicionar participante2 ao time1
        executeQuery($pdo, $sql, [$time1_id, $participante2_id]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Participantes trocados com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao trocar participantes: ' . $e->getMessage()]);
}
?>

