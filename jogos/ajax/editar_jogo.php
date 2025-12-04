<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit();
}

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido']);
    exit();
}

// Verificar se é o criador
$stmt = executeQuery($pdo, "SELECT criado_por FROM jogos WHERE id = ?", [$jogo_id]);
$row = $stmt ? $stmt->fetch() : null;
if (!$row || (int)$row['criado_por'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit();
}

$titulo = sanitizar($_POST['titulo'] ?? '');
$data_jogo = $_POST['data_jogo'] ?? '';
$data_fim = $_POST['data_fim_jogo'] ?? '';
$local = sanitizar($_POST['local'] ?? '');
$max_jogadores = (int)($_POST['max_jogadores'] ?? 0);
$descricao = sanitizar($_POST['descricao'] ?? '');
$modalidade = sanitizar($_POST['modalidade'] ?? '');
$contato = sanitizar($_POST['contato'] ?? '');

if ($titulo === '' || $data_jogo === '' || $local === '') {
    echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios']);
    exit();
}

$hasModalidade = false; $hasContato = false; $hasDataFim = false;
$stmtCols = executeQuery($pdo, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jogos' AND COLUMN_NAME IN ('modalidade','contato','data_fim')");
if ($stmtCols) {
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasModalidade = in_array('modalidade', $cols, true);
    $hasContato = in_array('contato', $cols, true);
    $hasDataFim = in_array('data_fim', $cols, true);
}

$set = ['titulo = ?', 'data_jogo = ?', 'local = ?', 'max_jogadores = ?', 'descricao = ?'];
$params = [$titulo, $data_jogo, $local, $max_jogadores, $descricao, $jogo_id];
if ($hasModalidade) { array_splice($set, count($set), 0, 'modalidade = ?'); array_splice($params, count($params)-1, 0, ($modalidade ?: null)); }
if ($hasContato) { array_splice($set, count($set), 0, 'contato = ?'); array_splice($params, count($params)-1, 0, ($contato ?: null)); }
if ($hasDataFim) { array_splice($set, count($set), 0, 'data_fim = ?'); array_splice($params, count($params)-1, 0, ($data_fim ?: null)); }

$sql = "UPDATE jogos SET ".implode(', ', $set)." WHERE id = ?";
$ok = executeQuery($pdo, $sql, $params);

echo json_encode(['success' => (bool)$ok]);
exit();
?>


