<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$grupo_id = (int)($_GET['grupo_id'] ?? 0);
$jogo_id = (int)($_GET['jogo_id'] ?? 0);

if ($grupo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Grupo inválido.']);
    exit();
}

// Verificar permissão
$sql = "SELECT administrador_id FROM grupos WHERE id = ?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;

if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo não encontrado.']);
    exit();
}

$sou_admin = ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Buscar membros do grupo
$sql = "SELECT u.id, u.nome, u.foto_perfil
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1
        ORDER BY u.nome";

// Se houver jogo_id, excluir participantes já inscritos
if ($jogo_id > 0) {
    $sql = "SELECT u.id, u.nome, u.foto_perfil
            FROM grupo_membros gm
            JOIN usuarios u ON u.id = gm.usuario_id
            LEFT JOIN grupo_jogo_participantes gjp ON gjp.usuario_id = u.id AND gjp.jogo_id = ?
            WHERE gm.grupo_id = ? AND gm.ativo = 1 AND gjp.id IS NULL
            ORDER BY u.nome";
    $stmt = executeQuery($pdo, $sql, [$jogo_id, $grupo_id]);
} else {
    $stmt = executeQuery($pdo, $sql, [$grupo_id]);
}

$membros = $stmt ? $stmt->fetchAll() : [];

// Formatar para JSON
$resultado = [];
foreach ($membros as $m) {
    $resultado[] = [
        'id' => (int)$m['id'],
        'nome' => $m['nome'],
        'foto_perfil' => $m['foto_perfil']
    ];
}

echo json_encode([
    'success' => true,
    'membros' => $resultado
]);
?>

