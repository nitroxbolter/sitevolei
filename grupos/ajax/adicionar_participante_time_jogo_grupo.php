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

$time_id = (int)($_POST['time_id'] ?? 0);
$participante_id = (int)($_POST['participante_id'] ?? 0);

if ($time_id <= 0 || $participante_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se o time existe e se o usuário é admin do grupo
$sql = "SELECT gjt.*, g.administrador_id 
        FROM grupo_jogo_times gjt
        JOIN grupo_jogos gj ON gj.id = gjt.jogo_id
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gjt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
$time = $stmt ? $stmt->fetch() : false;

if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

$sou_admin = ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se o participante já está neste time
$sql = "SELECT id FROM grupo_jogo_time_integrantes WHERE participante_id = ? AND time_id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id, $time_id]);
$ja_nesse_time = $stmt ? $stmt->fetch() : false;

if ($ja_nesse_time) {
    echo json_encode(['success' => false, 'message' => 'Este participante já está neste time.']);
    exit();
}

// Verificar se o participante já está em outro time
$sql = "SELECT time_id FROM grupo_jogo_time_integrantes WHERE participante_id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id]);
$ja_em_time = $stmt ? $stmt->fetch() : false;

if ($ja_em_time) {
    // Remover do time anterior
    $sql = "DELETE FROM grupo_jogo_time_integrantes WHERE participante_id = ?";
    executeQuery($pdo, $sql, [$participante_id]);
}

// Verificar novamente antes de inserir (double check)
$sql = "SELECT id FROM grupo_jogo_time_integrantes WHERE participante_id = ? AND time_id = ?";
$stmt = executeQuery($pdo, $sql, [$participante_id, $time_id]);
$existe = $stmt ? $stmt->fetch() : false;

if ($existe) {
    echo json_encode(['success' => false, 'message' => 'Este participante já está neste time.']);
    exit();
}

// Remover qualquer duplicata que possa existir (mesmo participante em outros times ou no mesmo time)
$sql = "DELETE FROM grupo_jogo_time_integrantes WHERE participante_id = ?";
executeQuery($pdo, $sql, [$participante_id]);

// Adicionar ao novo time
$sql = "INSERT INTO grupo_jogo_time_integrantes (time_id, participante_id) VALUES (?, ?)";
$result = executeQuery($pdo, $sql, [$time_id, $participante_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Participante adicionado ao time com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar participante.']);
}
?>

