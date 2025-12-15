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
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode gerenciar participantes.']);
    exit();
}

// Buscar participantes do jogo
$sql = "SELECT spp.usuario_id, u.id, u.nome, u.foto_perfil
        FROM sistema_pontuacao_participantes spp
        JOIN usuarios u ON u.id = spp.usuario_id
        WHERE spp.jogo_id = ?
        ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

echo json_encode([
    'success' => true,
    'participantes' => $participantes
]);
?>

