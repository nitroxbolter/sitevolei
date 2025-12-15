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
$modalidade = $_POST['modalidade'] ?? '';
$quantidade_grupos = isset($_POST['quantidade_grupos']) ? (int)$_POST['quantidade_grupos'] : null;
$quantidade_quadras = isset($_POST['quantidade_quadras']) ? (int)$_POST['quantidade_quadras'] : 1;

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

if (!in_array($modalidade, ['todos_contra_todos', 'todos_chaves', 'torneio_pro'])) {
    echo json_encode(['success' => false, 'message' => 'Modalidade inválida.']);
    exit();
}

// Validar quantidade de grupos se for modalidade todos_chaves
if ($modalidade === 'todos_chaves') {
    if (!$quantidade_grupos || $quantidade_grupos < 2) {
        echo json_encode(['success' => false, 'message' => 'A quantidade de chaves deve ser no mínimo 2.']);
        exit();
    }
    
    // Buscar quantidade de times do torneio
    $sql_times = "SELECT COUNT(*) as total FROM torneio_times WHERE torneio_id = ?";
    $stmt_times = executeQuery($pdo, $sql_times, [$torneio_id]);
    $total_times = $stmt_times ? (int)$stmt_times->fetch()['total'] : 0;
    
    // Se não há times salvos, verificar quantidade configurada
    if ($total_times === 0) {
        $sql_config = "SELECT quantidade_times FROM torneios WHERE id = ?";
        $stmt_config = executeQuery($pdo, $sql_config, [$torneio_id]);
        $config = $stmt_config ? $stmt_config->fetch() : false;
        $total_times = $config ? (int)($config['quantidade_times'] ?? 0) : 0;
    }
    
    // Verificar se a quantidade de times é divisível pela quantidade de chaves
    if ($total_times > 0 && $total_times % $quantidade_grupos !== 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'A quantidade total de times (' . $total_times . ') não é divisível pela quantidade de chaves (' . $quantidade_grupos . '). Para criar chaves, a quantidade de times deve ser divisível pela quantidade de chaves.'
        ]);
        exit();
    }
    
    // Verificar se o número de times é par (mínimo 4)
    if ($total_times > 0 && ($total_times < 4 || $total_times % 2 !== 0)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Não é possível criar chaves com ' . $total_times . ' time(s). É necessário um número par de times (mínimo 4) para criar chaves.'
        ]);
        exit();
    }
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

// Verificar se a coluna modalidade existe
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'modalidade'");
$tem_modalidade = $columnsQuery && $columnsQuery->rowCount() > 0;

if (!$tem_modalidade) {
    // Adicionar coluna modalidade
    try {
        $pdo->exec("ALTER TABLE torneios ADD COLUMN modalidade ENUM('todos_contra_todos', 'todos_chaves', 'torneio_pro') DEFAULT NULL AFTER integrantes_por_time");
    } catch (Exception $e) {
        error_log("Erro ao adicionar coluna modalidade: " . $e->getMessage());
    }
} else {
    // Verificar se o enum já inclui 'torneio_pro'
    $column_info = $columnsQuery->fetch(PDO::FETCH_ASSOC);
    if (isset($column_info['Type']) && strpos($column_info['Type'], 'torneio_pro') === false) {
        try {
            $pdo->exec("ALTER TABLE torneios MODIFY COLUMN modalidade ENUM('todos_contra_todos', 'todos_chaves', 'torneio_pro') DEFAULT NULL");
        } catch (Exception $e) {
            error_log("Erro ao atualizar enum modalidade: " . $e->getMessage());
        }
    }
}

// Verificar se a coluna quantidade_grupos existe
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'quantidade_grupos'");
$tem_quantidade_grupos = $columnsQuery && $columnsQuery->rowCount() > 0;

if (!$tem_quantidade_grupos) {
    // Adicionar coluna quantidade_grupos
    try {
        $pdo->exec("ALTER TABLE torneios ADD COLUMN quantidade_grupos INT(11) DEFAULT NULL AFTER modalidade");
    } catch (Exception $e) {
        error_log("Erro ao adicionar coluna quantidade_grupos: " . $e->getMessage());
    }
}

// Verificar se a coluna quantidade_quadras existe
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'quantidade_quadras'");
$tem_quantidade_quadras = $columnsQuery && $columnsQuery->rowCount() > 0;

if (!$tem_quantidade_quadras) {
    // Adicionar coluna quantidade_quadras
    try {
        $pdo->exec("ALTER TABLE torneios ADD COLUMN quantidade_quadras INT(11) DEFAULT 1 AFTER quantidade_grupos");
    } catch (Exception $e) {
        error_log("Erro ao adicionar coluna quantidade_quadras: " . $e->getMessage());
    }
}

// Se a modalidade mudou, limpar jogos existentes
$sql_modalidade_atual = "SELECT modalidade FROM torneios WHERE id = ?";
$stmt_modalidade = executeQuery($pdo, $sql_modalidade_atual, [$torneio_id]);
$modalidade_atual = $stmt_modalidade ? $stmt_modalidade->fetch()['modalidade'] : null;

// Atualizar modalidade, quantidade_grupos e quantidade_quadras
$sql = "UPDATE torneios SET modalidade = ?, quantidade_grupos = ?, quantidade_quadras = ? WHERE id = ?";
$result = executeQuery($pdo, $sql, [$modalidade, $quantidade_grupos, $quantidade_quadras, $torneio_id]);

// Se a modalidade mudou, limpar jogos, grupos e classificação
if ($modalidade_atual && $modalidade_atual !== $modalidade) {
    try {
        $pdo->beginTransaction();
        
        // Limpar chaves eliminatórias
        $sql_chaves = "DELETE FROM torneio_chaves_times WHERE torneio_id = ?";
        executeQuery($pdo, $sql_chaves, [$torneio_id]);
        
        // Limpar partidas
        $sql_partidas = "DELETE FROM torneio_partidas WHERE torneio_id = ?";
        executeQuery($pdo, $sql_partidas, [$torneio_id]);
        
        // Limpar times dos grupos
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
        
        // Limpar classificação
        $sql_classificacao = "DELETE FROM torneio_classificacao WHERE torneio_id = ?";
        executeQuery($pdo, $sql_classificacao, [$torneio_id]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao limpar jogos ao mudar modalidade: " . $e->getMessage());
    }
}

if ($result) {
    echo json_encode([
        'success' => true, 
        'message' => 'Modalidade salva com sucesso!'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar modalidade.']);
}
?>

