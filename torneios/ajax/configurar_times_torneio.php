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
$quantidade_times = (int)($_POST['quantidade_times'] ?? 0);
$integrantes_por_time = (int)($_POST['integrantes_por_time'] ?? 0);

if ($torneio_id <= 0 || $quantidade_times < 2 || $integrantes_por_time < 1) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
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

// Verificar se as colunas existem
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
$columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
$tem_quantidade_times = in_array('quantidade_times', $columns);
$tem_integrantes_por_time = in_array('integrantes_por_time', $columns);

if (!$tem_quantidade_times || !$tem_integrantes_por_time) {
    echo json_encode([
        'success' => false, 
        'message' => 'Colunas de configuração de times não existem. Execute o script SQL: sql/adicionar_colunas_torneios.sql'
    ]);
    exit();
}

// Atualizar configuração
$sql = "UPDATE torneios SET quantidade_times = ?, integrantes_por_time = ? WHERE id = ?";
$result = executeQuery($pdo, $sql, [$quantidade_times, $integrantes_por_time, $torneio_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Configuração salva com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração.']);
}
?>

