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

// Buscar participantes do jogo que não estão neste time (ou estão em outros times)
$sql = "SELECT gjp.id AS participante_id, gjp.usuario_id, u.nome, u.foto_perfil
        FROM grupo_jogo_participantes gjp
        LEFT JOIN usuarios u ON u.id = gjp.usuario_id
        WHERE gjp.jogo_id = ?
        AND gjp.id NOT IN (
            SELECT participante_id FROM grupo_jogo_time_integrantes 
            WHERE time_id = ?
        )
        ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$jogo_id, $time_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

echo json_encode([
    'success' => true,
    'participantes' => $participantes
]);
?>

