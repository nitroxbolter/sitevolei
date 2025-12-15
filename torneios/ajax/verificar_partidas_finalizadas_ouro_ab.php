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

// Buscar grupos Ouro A e Ouro B
$sql_grupo_ouro_a = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND (nome = '2ª Fase - Ouro A' OR nome LIKE '%Ouro A%') LIMIT 1";
$stmt_grupo_a = executeQuery($pdo, $sql_grupo_ouro_a, [$torneio_id]);
$grupo_ouro_a = $stmt_grupo_a ? $stmt_grupo_a->fetch() : null;

$sql_grupo_ouro_b = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND (nome = '2ª Fase - Ouro B' OR nome LIKE '%Ouro B%') LIMIT 1";
$stmt_grupo_b = executeQuery($pdo, $sql_grupo_ouro_b, [$torneio_id]);
$grupo_ouro_b = $stmt_grupo_b ? $stmt_grupo_b->fetch() : null;

// Debug: listar todos os grupos da 2ª fase
$sql_debug_grupos = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '%2ª Fase%'";
$stmt_debug_grupos = executeQuery($pdo, $sql_debug_grupos, [$torneio_id]);
$grupos_debug = $stmt_debug_grupos ? $stmt_debug_grupos->fetchAll(PDO::FETCH_ASSOC) : [];
error_log("DEBUG VERIFICAR - Grupos da 2ª fase encontrados: " . json_encode($grupos_debug));

if (!$grupo_ouro_a || !$grupo_ouro_b) {
    echo json_encode([
        'success' => false, 
        'message' => 'Grupos Ouro A ou Ouro B não encontrados.',
        'ouro_a' => ['total' => 0, 'finalizadas' => 0, 'todas_finalizadas' => false],
        'ouro_b' => ['total' => 0, 'finalizadas' => 0, 'todas_finalizadas' => false]
    ]);
    exit();
}

$grupo_ouro_a_id = (int)$grupo_ouro_a['id'];
$grupo_ouro_b_id = (int)$grupo_ouro_b['id'];

error_log("DEBUG VERIFICAR - Grupo Ouro A ID: $grupo_ouro_a_id, Nome: " . $grupo_ouro_a['nome']);
error_log("DEBUG VERIFICAR - Grupo Ouro B ID: $grupo_ouro_b_id, Nome: " . $grupo_ouro_b['nome']);

// Debug: verificar TODAS as partidas da 2ª fase primeiro
$sql_debug_todas = "SELECT id, grupo_id, status, tipo_fase, fase FROM torneio_partidas WHERE torneio_id = ? AND fase = '2ª Fase' LIMIT 20";
$stmt_debug_todas = executeQuery($pdo, $sql_debug_todas, [$torneio_id]);
$partidas_debug_todas = $stmt_debug_todas ? $stmt_debug_todas->fetchAll(PDO::FETCH_ASSOC) : [];
error_log("DEBUG VERIFICAR - Total partidas 2ª fase no torneio: " . count($partidas_debug_todas));
if (count($partidas_debug_todas) > 0) {
    error_log("DEBUG VERIFICAR - Primeira partida: " . json_encode($partidas_debug_todas[0]));
}

// Verificar partidas do Ouro A (sem filtro de tipo_fase para garantir que encontra todas)
$sql_check_partidas_a = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM torneio_partidas 
                         WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'";
$stmt_check_a = executeQuery($pdo, $sql_check_partidas_a, [$torneio_id, $grupo_ouro_a_id]);
$info_partidas_a = $stmt_check_a ? $stmt_check_a->fetch() : ['total' => 0, 'finalizadas' => 0];

// Debug: verificar todas as partidas do grupo
$sql_debug_a = "SELECT id, status, tipo_fase, fase, grupo_id FROM torneio_partidas WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'";
$stmt_debug_a = executeQuery($pdo, $sql_debug_a, [$torneio_id, $grupo_ouro_a_id]);
$partidas_debug_a = $stmt_debug_a ? $stmt_debug_a->fetchAll(PDO::FETCH_ASSOC) : [];
error_log("DEBUG Ouro A - Total partidas encontradas: " . count($partidas_debug_a) . ", Grupo ID: $grupo_ouro_a_id, Total na query COUNT: " . $info_partidas_a['total'] . ", Finalizadas: " . $info_partidas_a['finalizadas']);

// Verificar partidas do Ouro B (sem filtro de tipo_fase para garantir que encontra todas)
$sql_check_partidas_b = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM torneio_partidas 
                         WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'";
$stmt_check_b = executeQuery($pdo, $sql_check_partidas_b, [$torneio_id, $grupo_ouro_b_id]);
$info_partidas_b = $stmt_check_b ? $stmt_check_b->fetch() : ['total' => 0, 'finalizadas' => 0];

// Debug: verificar todas as partidas do grupo
$sql_debug_b = "SELECT id, status, tipo_fase, fase, grupo_id FROM torneio_partidas WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'";
$stmt_debug_b = executeQuery($pdo, $sql_debug_b, [$torneio_id, $grupo_ouro_b_id]);
$partidas_debug_b = $stmt_debug_b ? $stmt_debug_b->fetchAll(PDO::FETCH_ASSOC) : [];
error_log("DEBUG Ouro B - Total partidas encontradas: " . count($partidas_debug_b) . ", Grupo ID: $grupo_ouro_b_id, Total na query COUNT: " . $info_partidas_b['total'] . ", Finalizadas: " . $info_partidas_b['finalizadas']);

// Se não encontrou partidas pelos grupos, tentar buscar por nome do grupo nas partidas
if ($info_partidas_a['total'] == 0 || $info_partidas_b['total'] == 0) {
    error_log("DEBUG VERIFICAR - Não encontrou partidas pelos grupos. Tentando buscar por JOIN com grupos...");
    
    // Tentar buscar partidas fazendo JOIN com grupos
    $sql_alt_a = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN tp.status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                  FROM torneio_partidas tp
                  JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                  WHERE tp.torneio_id = ? AND tp.fase = '2ª Fase' AND tg.nome LIKE '%Ouro A%'";
    $stmt_alt_a = executeQuery($pdo, $sql_alt_a, [$torneio_id]);
    $info_alt_a = $stmt_alt_a ? $stmt_alt_a->fetch() : ['total' => 0, 'finalizadas' => 0];
    
    $sql_alt_b = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN tp.status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                  FROM torneio_partidas tp
                  JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                  WHERE tp.torneio_id = ? AND tp.fase = '2ª Fase' AND tg.nome LIKE '%Ouro B%'";
    $stmt_alt_b = executeQuery($pdo, $sql_alt_b, [$torneio_id]);
    $info_alt_b = $stmt_alt_b ? $stmt_alt_b->fetch() : ['total' => 0, 'finalizadas' => 0];
    
    if ($info_alt_a['total'] > 0 || $info_alt_b['total'] > 0) {
        error_log("DEBUG VERIFICAR - Encontrou partidas usando JOIN: Ouro A={$info_alt_a['total']}, Ouro B={$info_alt_b['total']}");
        $info_partidas_a = $info_alt_a;
        $info_partidas_b = $info_alt_b;
    }
}

$todas_finalizadas_a = $info_partidas_a['total'] > 0 && $info_partidas_a['finalizadas'] == $info_partidas_a['total'];
$todas_finalizadas_b = $info_partidas_b['total'] > 0 && $info_partidas_b['finalizadas'] == $info_partidas_b['total'];

echo json_encode([
    'success' => true,
    'ouro_a' => [
        'total' => (int)$info_partidas_a['total'],
        'finalizadas' => (int)$info_partidas_a['finalizadas'],
        'todas_finalizadas' => $todas_finalizadas_a
    ],
    'ouro_b' => [
        'total' => (int)$info_partidas_b['total'],
        'finalizadas' => (int)$info_partidas_b['finalizadas'],
        'todas_finalizadas' => $todas_finalizadas_b
    ]
]);
?>

