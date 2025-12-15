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

if ($torneio['modalidade'] !== 'torneio_pro') {
    echo json_encode(['success' => false, 'message' => 'Esta função é apenas para torneios do tipo Torneio Pro.']);
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
    // Buscar grupos da 2ª fase
    $sql_grupos_2fase = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%'";
    $stmt_grupos = executeQuery($pdo, $sql_grupos_2fase, [$torneio_id]);
    $grupos_2fase = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_COLUMN) : [];
    
    $partidas_removidas = 0;
    $classificacoes_removidas = 0;
    $grupos_removidos = 0;
    $partidas_eliminatorias_removidas = 0;
    $partidas_todos_contra_todos_removidas = 0;
    $classificacoes_2fase_removidas = 0;
    
    // Remover partidas eliminatórias da 2ª fase (semi-finais, finais, etc.) da tabela partidas_2fase_eliminatorias
    $sql_remover_eliminatorias = "DELETE FROM partidas_2fase_eliminatorias WHERE torneio_id = ?";
    $stmt_remover_eliminatorias = executeQuery($pdo, $sql_remover_eliminatorias, [$torneio_id]);
    $partidas_eliminatorias_removidas = $stmt_remover_eliminatorias ? $stmt_remover_eliminatorias->rowCount() : 0;
    
    // Remover partidas todos contra todos da 2ª fase da tabela partidas_2fase_torneio
    $sql_remover_todos_contra_todos = "DELETE FROM partidas_2fase_torneio WHERE torneio_id = ?";
    $stmt_remover_todos_contra_todos = executeQuery($pdo, $sql_remover_todos_contra_todos, [$torneio_id]);
    $partidas_todos_contra_todos_removidas = $stmt_remover_todos_contra_todos ? $stmt_remover_todos_contra_todos->rowCount() : 0;
    
    // Remover classificações da 2ª fase da tabela partidas_2fase_classificacao
    $sql_remover_classificacao_2fase = "DELETE FROM partidas_2fase_classificacao WHERE torneio_id = ?";
    $stmt_remover_class_2fase = executeQuery($pdo, $sql_remover_classificacao_2fase, [$torneio_id]);
    $classificacoes_2fase_removidas = $stmt_remover_class_2fase ? $stmt_remover_class_2fase->rowCount() : 0;
    
    if (!empty($grupos_2fase)) {
        $placeholders = implode(',', array_fill(0, count($grupos_2fase), '?'));
        $params_remover = array_merge([$torneio_id], $grupos_2fase);
        
        // Remover TODAS as partidas da 2ª fase, incluindo semi-finais e outros tipos
        // Remover todas as partidas da 2ª fase de uma vez (incluindo as que estão nos grupos e as que podem estar órfãs)
        $sql_remover_partidas = "DELETE FROM torneio_partidas WHERE torneio_id = ? AND fase = '2ª Fase'";
        $stmt_remover = executeQuery($pdo, $sql_remover_partidas, [$torneio_id]);
        $partidas_removidas = $stmt_remover ? $stmt_remover->rowCount() : 0;
        
        // Remover classificações da 2ª fase
        $sql_remover_classificacao = "DELETE FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id IN ($placeholders)";
        $stmt_remover_class = executeQuery($pdo, $sql_remover_classificacao, $params_remover);
        $classificacoes_removidas = $stmt_remover_class ? $stmt_remover_class->rowCount() : 0;
        
        // Remover times dos grupos
        $sql_remover_times = "DELETE FROM torneio_grupo_times WHERE grupo_id IN ($placeholders)";
        executeQuery($pdo, $sql_remover_times, $grupos_2fase);
        
        // Remover grupos da 2ª fase
        $sql_remover_grupos = "DELETE FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%'";
        $stmt_remover_grupos = executeQuery($pdo, $sql_remover_grupos, [$torneio_id]);
        $grupos_removidos = $stmt_remover_grupos ? $stmt_remover_grupos->rowCount() : 0;
    } else {
        // Se não há grupos, remover todas as partidas da 2ª fase (incluindo semi-finais)
        $sql_remover_todas = "DELETE FROM torneio_partidas WHERE torneio_id = ? AND fase = '2ª Fase'";
        $stmt_remover_todas = executeQuery($pdo, $sql_remover_todas, [$torneio_id]);
        $partidas_removidas = $stmt_remover_todas ? $stmt_remover_todas->rowCount() : 0;
        
        // Remover classificações da 2ª fase também (se houver grupos órfãos)
        // Primeiro buscar IDs dos grupos que podem ter sido removidos mas ainda ter classificações
        $sql_grupos_2fase_ids = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%'";
        $stmt_grupos_ids = executeQuery($pdo, $sql_grupos_2fase_ids, [$torneio_id]);
        $grupos_ids_restantes = $stmt_grupos_ids ? $stmt_grupos_ids->fetchAll(PDO::FETCH_COLUMN) : [];
        
        if (!empty($grupos_ids_restantes)) {
            $placeholders_class = implode(',', array_fill(0, count($grupos_ids_restantes), '?'));
            $sql_remover_class_todas = "DELETE FROM torneio_classificacao WHERE torneio_id = ? AND grupo_id IN ($placeholders_class)";
            $params_class = array_merge([$torneio_id], $grupos_ids_restantes);
            $stmt_remover_class_todas = executeQuery($pdo, $sql_remover_class_todas, $params_class);
            $classificacoes_removidas = $stmt_remover_class_todas ? $stmt_remover_class_todas->rowCount() : 0;
        } else {
            $classificacoes_removidas = 0;
        }
    }
    
    $pdo->commit();
    
    $mensagem = "2ª fase removida com sucesso! ";
    if ($partidas_removidas > 0) {
        $mensagem .= "$partidas_removidas partida(s) removida(s). ";
    }
    if ($partidas_todos_contra_todos_removidas > 0) {
        $mensagem .= "$partidas_todos_contra_todos_removidas jogo(s) todos contra todos removido(s). ";
    }
    if ($partidas_eliminatorias_removidas > 0) {
        $mensagem .= "$partidas_eliminatorias_removidas jogo(s) eliminatório(s) (semi-finais/finais) removido(s). ";
    }
    if ($classificacoes_removidas > 0) {
        $mensagem .= "$classificacoes_removidas classificação(ões) removida(s). ";
    }
    if ($classificacoes_2fase_removidas > 0) {
        $mensagem .= "$classificacoes_2fase_removidas classificação(ões) da 2ª fase removida(s). ";
    }
    if ($grupos_removidos > 0) {
        $mensagem .= "$grupos_removidos grupo(s) removido(s).";
    }
    
    echo json_encode([
        'success' => true, 
        'message' => trim($mensagem),
        'partidas_removidas' => $partidas_removidas,
        'classificacoes_removidas' => $classificacoes_removidas,
        'grupos_removidos' => $grupos_removidos
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao limpar jogos da 2ª fase: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao limpar jogos da 2ª fase: ' . $e->getMessage()
    ]);
}
?>

