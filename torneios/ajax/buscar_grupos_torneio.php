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

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Buscar grupos e seus times
$sql = "SELECT id, nome, ordem FROM torneio_grupos WHERE torneio_id = ? ORDER BY ordem ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$grupos_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$grupos = [];
foreach ($grupos_raw as $grupo) {
    // Buscar times do grupo
    $sql_times = "SELECT tt.id, tt.nome, tt.cor 
                  FROM torneio_times tt
                  JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                  WHERE tgt.grupo_id = ?
                  ORDER BY tt.ordem ASC, tt.id ASC";
    $stmt_times = executeQuery($pdo, $sql_times, [$grupo['id']]);
    $times = $stmt_times ? $stmt_times->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $grupos[] = [
        'id' => (int)$grupo['id'],
        'nome' => $grupo['nome'],
        'ordem' => (int)$grupo['ordem'],
        'times' => $times
    ];
}

echo json_encode([
    'success' => true,
    'grupos' => $grupos
]);
?>

