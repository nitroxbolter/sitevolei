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

// Iniciar transação para garantir que tudo seja excluído
$pdo->beginTransaction();
try {
    // Excluir classificação
    $sql = "DELETE FROM grupo_jogo_classificacao WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Excluir resultados de partidas
    $sql = "DELETE FROM grupo_jogo_partidas WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Excluir integrantes dos times
    $sql = "DELETE gjti FROM grupo_jogo_time_integrantes gjti
            JOIN grupo_jogo_times gjt ON gjt.id = gjti.time_id
            WHERE gjt.jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Excluir times
    $sql = "DELETE FROM grupo_jogo_times WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Excluir participantes
    $sql = "DELETE FROM grupo_jogo_participantes WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Excluir jogo
    $sql = "DELETE FROM grupo_jogos WHERE id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Jogo excluído com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir jogo: ' . $e->getMessage()]);
}
?>

