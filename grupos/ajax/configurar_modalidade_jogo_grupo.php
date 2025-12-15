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
$modalidade = sanitizar($_POST['modalidade'] ?? '');
$quantidade_times = (int)($_POST['quantidade_times'] ?? 0);
$integrantes_por_time = (int)($_POST['integrantes_por_time'] ?? 0);

if ($jogo_id <= 0 || empty($modalidade) || $quantidade_times <= 0 || $integrantes_por_time <= 0) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
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

// Verificar quantidade de participantes
$sql = "SELECT COUNT(*) AS total FROM grupo_jogo_participantes WHERE jogo_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$total_participantes = $stmt ? (int)$stmt->fetch()['total'] : 0;

$total_necessario = $quantidade_times * $integrantes_por_time;
$sobra = $total_participantes - $total_necessario;

// Validar mínimo de 2 times
if ($quantidade_times < 2) {
    echo json_encode(['success' => false, 'message' => "É necessário pelo menos 2 times. Com {$total_participantes} participantes e {$integrantes_por_time} por time, seria possível formar apenas " . floor($total_participantes / $integrantes_por_time) . " time(s)."]);
    exit();
}

// Validar se há participantes suficientes (pelo menos o necessário)
if ($total_participantes < $total_necessario) {
    echo json_encode(['success' => false, 'message' => "Você precisa de pelo menos {$total_necessario} participantes. Atualmente há {$total_participantes}."]);
    exit();
}

// Validar se todos os participantes serão utilizados (sem sobras)
if ($sobra > 0) {
    echo json_encode(['success' => false, 'message' => "Esta modalidade não é viável. Com {$total_participantes} participantes, formando {$quantidade_times} times de {$integrantes_por_time}, sobrariam {$sobra} participante" . ($sobra > 1 ? 's' : '') . " de fora. Escolha outra modalidade ou ajuste a quantidade de participantes."]);
    exit();
}

// Atualizar configuração
$sql = "UPDATE grupo_jogos SET modalidade = ?, quantidade_times = ?, integrantes_por_time = ? WHERE id = ?";
$result = executeQuery($pdo, $sql, [$modalidade, $quantidade_times, $integrantes_por_time, $jogo_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Modalidade configurada com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao configurar modalidade.']);
}
?>

