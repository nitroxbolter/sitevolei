<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

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

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id 
        FROM sistema_pontuacao_jogos spj
        JOIN sistemas_pontuacao sp ON sp.id = spj.sistema_id
        JOIN grupos g ON g.id = sp.grupo_id
        WHERE spj.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;
if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado.']);
    exit();
}

$sou_admin = ((int)$jogo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode ver participantes.']);
    exit();
}

// Buscar participantes do jogo que não estão no time (ou todos se time_id = 0)
$sql = "SELECT u.id, u.nome, u.foto_perfil
        FROM sistema_pontuacao_participantes spp
        JOIN usuarios u ON u.id = spp.usuario_id";
        
if ($time_id > 0) {
    $sql .= " LEFT JOIN sistema_pontuacao_time_jogadores stj ON stj.usuario_id = u.id AND stj.time_id = ?
             WHERE spp.jogo_id = ? AND stj.id IS NULL";
    $params = [$time_id, $jogo_id];
} else {
    $sql .= " WHERE spp.jogo_id = ?";
    $params = [$jogo_id];
}

$sql .= " ORDER BY u.nome";

$stmt = executeQuery($pdo, $sql, $params);
$participantes = $stmt ? $stmt->fetchAll() : [];

echo json_encode([
    'success' => true,
    'participantes' => $participantes
]);
?>

