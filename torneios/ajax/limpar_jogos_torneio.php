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
    // Contar quantos jogos serão deletados
    $sql_count = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
    $stmt_count = executeQuery($pdo, $sql_count, [$torneio_id]);
    $total_partidas = $stmt_count ? (int)$stmt_count->fetch()['total'] : 0;
    
    // Deletar chaves eliminatórias primeiro (se existir)
    $sql_chaves = "DELETE FROM torneio_chaves_times WHERE torneio_id = ?";
    executeQuery($pdo, $sql_chaves, [$torneio_id]);
    
    // Deletar partidas
    $sql_partidas = "DELETE FROM torneio_partidas WHERE torneio_id = ?";
    executeQuery($pdo, $sql_partidas, [$torneio_id]);
    
    // Deletar times dos grupos (se existir)
    // Primeiro buscar IDs dos grupos
    $sql_buscar_grupos = "SELECT id FROM torneio_grupos WHERE torneio_id = ?";
    $stmt_grupos = executeQuery($pdo, $sql_buscar_grupos, [$torneio_id]);
    $grupos_ids = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_COLUMN) : [];
    
    if (!empty($grupos_ids)) {
        $placeholders = implode(',', array_fill(0, count($grupos_ids), '?'));
        $sql_grupo_times = "DELETE FROM torneio_grupo_times WHERE grupo_id IN ($placeholders)";
        executeQuery($pdo, $sql_grupo_times, $grupos_ids);
        
        // Deletar grupos
        $sql_grupos = "DELETE FROM torneio_grupos WHERE torneio_id = ?";
        executeQuery($pdo, $sql_grupos, [$torneio_id]);
    }
    
    // Deletar classificação
    $sql_classificacao = "DELETE FROM torneio_classificacao WHERE torneio_id = ?";
    executeQuery($pdo, $sql_classificacao, [$torneio_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Jogos limpos com sucesso! " . $total_partidas . " partida(s) removida(s)."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao limpar jogos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao limpar jogos: ' . $e->getMessage()]);
}
?>

