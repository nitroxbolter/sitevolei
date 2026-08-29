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

// Se a modalidade mudou, limpar jogos existentes
$sql_modalidade_atual = "SELECT modalidade FROM torneios WHERE id = ?";
$stmt_modalidade = executeQuery($pdo, $sql_modalidade_atual, [$torneio_id]);
$modalidade_atual = $stmt_modalidade ? $stmt_modalidade->fetch()['modalidade'] : null;

$pdo->beginTransaction();
try {
    executeQuery($pdo, "SELECT id FROM torneios WHERE id = ? FOR UPDATE", [$torneio_id]);

    // Atualizar modalidade, quantidade_grupos e quantidade_quadras
    $sql = "UPDATE torneios SET modalidade = ?, quantidade_grupos = ?, quantidade_quadras = ? WHERE id = ?";
    $result = executeQuery($pdo, $sql, [$modalidade, $quantidade_grupos, $quantidade_quadras, $torneio_id]);
    if (!$result) {
        throw new Exception('Erro ao salvar modalidade.');
    }

    // Se a modalidade mudou, limpar jogos, grupos e classificação na mesma transação.
    if ($modalidade_atual && $modalidade_atual !== $modalidade) {
        $stmt_finalizadas = executeQuery($pdo, "SELECT COUNT(*) AS total FROM torneio_partidas WHERE torneio_id = ? AND status = 'Finalizada'", [$torneio_id]);
        $finalizadas = $stmt_finalizadas ? (int)$stmt_finalizadas->fetch()['total'] : 0;
        if ($finalizadas > 0) {
            throw new Exception('Não é possível alterar a modalidade depois que existem partidas finalizadas.');
        }

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
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Modalidade salva com sucesso!'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao salvar modalidade: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

