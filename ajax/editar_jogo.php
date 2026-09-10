<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

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

if (!podeGerenciarJogo($pdo, $jogo_id, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit();
}

$titulo = sanitizar($_POST['titulo'] ?? '');
$data_jogo = normalizarDataHoraInput($_POST['data_jogo'] ?? '');
$data_fim = normalizarDataHoraInput($_POST['data_fim_jogo'] ?? '');
$local = sanitizar($_POST['local'] ?? '');
$max_jogadores = (int)($_POST['max_jogadores'] ?? 0);
$descricao = sanitizar($_POST['descricao'] ?? '');
$modalidade = normalizarModalidadeJogo($_POST['modalidade'] ?? '');
$contato = sanitizar($_POST['contato'] ?? '');

if ($titulo === '' || $data_jogo === '' || $local === '') {
    echo json_encode(['success' => false, 'message' => 'Preencha os campos obrigatórios']);
    exit();
}

if ($max_jogadores < 1 || $max_jogadores > 200) {
    echo json_encode(['success' => false, 'message' => 'O número máximo de jogadores deve ficar entre 1 e 200']);
    exit();
}

if ($data_jogo === '') {
    echo json_encode(['success' => false, 'message' => 'Informe uma data/hora válida para o jogo']);
    exit();
}

if ($data_fim !== '' && strtotime($data_fim) <= strtotime($data_jogo)) {
    echo json_encode(['success' => false, 'message' => 'A data de fim deve ser posterior ao início do jogo']);
    exit();
}

if (mb_strlen($titulo) > 100 || mb_strlen($local) > 200 || mb_strlen($descricao) > 5000 || mb_strlen($contato) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Algum campo ultrapassou o tamanho permitido.']);
    exit();
}

$stmtConfirmados = executeQuery($pdo, "SELECT COUNT(*) AS total FROM confirmacoes_presenca WHERE jogo_id = ? AND status = 'Confirmado'", [$jogo_id]);
$confirmados = $stmtConfirmados ? (int)($stmtConfirmados->fetch()['total'] ?? 0) : 0;
if ($max_jogadores < $confirmados) {
    echo json_encode(['success' => false, 'message' => 'O limite não pode ser menor que os jogadores já confirmados.']);
    exit();
}
$vagas_disponiveis = max(0, $max_jogadores - $confirmados);

$hasModalidade = false; $hasContato = false; $hasDataFim = false;
$stmtCols = executeQuery($pdo, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jogos' AND COLUMN_NAME IN ('modalidade','contato','data_fim')");
if ($stmtCols) {
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasModalidade = in_array('modalidade', $cols, true);
    $hasContato = in_array('contato', $cols, true);
    $hasDataFim = in_array('data_fim', $cols, true);
}

$set = ['titulo = ?', 'data_jogo = ?', 'local = ?', 'max_jogadores = ?', 'vagas_disponiveis = ?', 'descricao = ?'];
$params = [$titulo, $data_jogo, $local, $max_jogadores, $vagas_disponiveis, $descricao, $jogo_id];
if ($hasModalidade) { array_splice($set, count($set), 0, 'modalidade = ?'); array_splice($params, count($params)-1, 0, ($modalidade ?: null)); }
if ($hasContato) { array_splice($set, count($set), 0, 'contato = ?'); array_splice($params, count($params)-1, 0, ($contato ?: null)); }
if ($hasDataFim) { array_splice($set, count($set), 0, 'data_fim = ?'); array_splice($params, count($params)-1, 0, ($data_fim ?: null)); }

$sql = "UPDATE jogos SET ".implode(', ', $set)." WHERE id = ?";
$ok = executeQuery($pdo, $sql, $params);

echo json_encode(['success' => (bool)$ok]);
exit();
?>


