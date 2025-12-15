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

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido.']);
    exit();
}

// Verificar se o jogo existe e se o usuário é admin do grupo
$sql = "SELECT gj.*, g.administrador_id 
        FROM grupo_jogos gj
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gj.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado.']);
    exit();
}

$sou_admin = ((int)$jogo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Buscar participantes
$sql = "SELECT * FROM grupo_jogo_participantes WHERE jogo_id = ? ORDER BY RAND()";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

if (empty($participantes)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum participante cadastrado.']);
    exit();
}

// Buscar times
$sql = "SELECT * FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$times = $stmt ? $stmt->fetchAll() : [];

if (empty($times)) {
    echo json_encode(['success' => false, 'message' => 'Crie os times primeiro.']);
    exit();
}

$integrantes_por_time = (int)($jogo['integrantes_por_time'] ?? 0);

$pdo->beginTransaction();
try {
    // Limpar integrantes existentes
    $sql = "DELETE FROM grupo_jogo_time_integrantes WHERE time_id IN (SELECT id FROM grupo_jogo_times WHERE jogo_id = ?)";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Sortear participantes nos times
    $participanteIndex = 0;
    
    foreach ($times as $time) {
        for ($i = 0; $i < $integrantes_por_time && $participanteIndex < count($participantes); $i++) {
            $participante_id = $participantes[$participanteIndex]['id'];
            
            // Verificar se já existe antes de inserir
            $sql = "SELECT id FROM grupo_jogo_time_integrantes WHERE participante_id = ? AND time_id = ?";
            $stmt = executeQuery($pdo, $sql, [$participante_id, $time['id']]);
            $existe = $stmt ? $stmt->fetch() : false;
            
            if (!$existe) {
                $sql = "INSERT INTO grupo_jogo_time_integrantes (time_id, participante_id) VALUES (?, ?)";
                executeQuery($pdo, $sql, [$time['id'], $participante_id]);
            }
            
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

