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

// Verificar se todas as partidas todos contra todos estão finalizadas
$sql_partidas_todos_contra_todos = "SELECT COUNT(*) as total, 
                                   SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                   FROM torneio_partidas 
                                   WHERE torneio_id = ? AND fase = '2ª Fase' 
                                   AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
$stmt_partidas_todos_contra_todos = executeQuery($pdo, $sql_partidas_todos_contra_todos, [$torneio_id]);
$info_partidas_todos_contra_todos = $stmt_partidas_todos_contra_todos ? $stmt_partidas_todos_contra_todos->fetch() : ['total' => 0, 'finalizadas' => 0];
$todas_finalizadas = $info_partidas_todos_contra_todos['total'] > 0 && 
                   $info_partidas_todos_contra_todos['finalizadas'] == $info_partidas_todos_contra_todos['total'];

// Verificar se já existem semi-finais
$sql_check_semifinais = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ? AND fase = '2ª Fase' AND tipo_fase = 'Semi-Final'";
$stmt_check_semifinais = executeQuery($pdo, $sql_check_semifinais, [$torneio_id]);
$tem_semifinais = $stmt_check_semifinais ? (int)$stmt_check_semifinais->fetch()['total'] > 0 : false;

echo json_encode([
    'success' => true,
    'todas_finalizadas' => $todas_finalizadas,
    'tem_semifinais' => $tem_semifinais,
    'total_partidas' => (int)$info_partidas_todos_contra_todos['total'],
    'partidas_finalizadas' => (int)$info_partidas_todos_contra_todos['finalizadas']
]);
?>

