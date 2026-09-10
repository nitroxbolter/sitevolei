<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$jogo_id = (int)($_GET['jogo_id'] ?? 0);
$time_id = (int)($_GET['time_id'] ?? 0);

if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido.']);
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

// Buscar somente participantes da lista principal; suplentes ficam fora dos times até o admin remover/promover alguém
$max_participantes = (int)($jogo['max_participantes'] ?? 0);
$participantes = [];

$sql = "SELECT gjp.id AS participante_id, gjp.usuario_id, u.nome, u.foto_perfil
        FROM grupo_jogo_participantes gjp
        LEFT JOIN usuarios u ON u.id = gjp.usuario_id
        WHERE gjp.jogo_id = ?
        ORDER BY gjp.data_inscricao ASC";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$todos_participantes = $stmt ? $stmt->fetchAll() : [];

if ($max_participantes > 0) {
    $todos_participantes = array_slice($todos_participantes, 0, $max_participantes);
}

foreach ($todos_participantes as $participante) {
    $sql = "SELECT id FROM grupo_jogo_time_integrantes WHERE participante_id = ? AND time_id = ?";
    $stmt = executeQuery($pdo, $sql, [$participante['participante_id'], $time_id]);
    if (!$stmt || !$stmt->fetch()) {
        $participantes[] = $participante;
    }
}

usort($participantes, function($a, $b) {
    return strcasecmp((string)($a['nome'] ?? ''), (string)($b['nome'] ?? ''));
});

echo json_encode([
    'success' => true,
    'participantes' => $participantes
]);
?>

