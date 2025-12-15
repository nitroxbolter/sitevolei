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
$grupo_id = (int)($_GET['grupo_id'] ?? 0);

if ($jogo_id <= 0 || $grupo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id 
        FROM grupos g
        WHERE g.id = ?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;

if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo não encontrado.']);
    exit();
}

$sou_admin = ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode gerenciar participantes.']);
    exit();
}

// Buscar participantes já cadastrados no jogo
$sql = "SELECT usuario_id FROM sistema_pontuacao_participantes WHERE jogo_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$participantes_ids = [];
if ($stmt) {
    $participantes = $stmt->fetchAll();
    $participantes_ids = array_column($participantes, 'usuario_id');
}

// Buscar membros do grupo que não são participantes
$sql = "SELECT u.id, u.nome, u.foto_perfil
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1";
        
if (!empty($participantes_ids)) {
    $placeholders = implode(',', array_fill(0, count($participantes_ids), '?'));
    $sql .= " AND u.id NOT IN ($placeholders)";
}

$sql .= " ORDER BY u.nome";

$params = [$grupo_id];
if (!empty($participantes_ids)) {
    $params = array_merge($params, $participantes_ids);
}

$stmt = executeQuery($pdo, $sql, $params);
$membros = $stmt ? $stmt->fetchAll() : [];

echo json_encode([
    'success' => true,
    'membros' => $membros
]);
?>

