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
$nome = trim($_POST['nome'] ?? '');
$data_torneio = $_POST['data_torneio'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

if ($nome === '' || $data_torneio === '') {
    echo json_encode(['success' => false, 'message' => 'Nome e data são obrigatórios.']);
    exit();
}

// Verificar permissão (criador ou admin)
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

// Verificar se já existe um torneio com o mesmo nome em status aberto (exceto o atual)
try {
    $sql_check = "SELECT id, nome, status FROM torneios WHERE nome = ? AND id != ? AND status NOT IN ('Finalizado', 'Cancelado')";
    $stmt_check = executeQuery($pdo, $sql_check, [$nome, $torneio_id]);
    if ($stmt_check) {
        $torneio_existente = $stmt_check->fetch();
        if ($torneio_existente) {
            echo json_encode([
                'success' => false, 
                'message' => "Já existe um torneio com o nome '$nome' em andamento (Status: {$torneio_existente['status']}). Escolha outro nome."
            ]);
            exit();
        }
    }
} catch (Exception $e) {
    // Continuar mesmo se houver erro na verificação
}

// Verificar qual coluna de data usar
try {
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    $campo_data = in_array('data_torneio', $columns) ? 'data_torneio' : 'data_inicio';
} catch (Exception $e) {
    $campo_data = 'data_inicio';
}

// Atualizar torneio
try {
    $sql_update = "UPDATE torneios SET nome = ?, $campo_data = ?";
    $valores = [$nome, $data_torneio];
    
    // Adicionar descrição se a coluna existir
    if (in_array('descricao', $columns)) {
        $sql_update .= ", descricao = ?";
        $valores[] = $descricao;
    }
    
    $sql_update .= " WHERE id = ?";
    $valores[] = $torneio_id;
    
    $stmt_update = executeQuery($pdo, $sql_update, $valores);
    
    if ($stmt_update) {
        echo json_encode(['success' => true, 'message' => 'Torneio atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar torneio.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>

