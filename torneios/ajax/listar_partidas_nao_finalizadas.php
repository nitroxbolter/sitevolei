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

// Buscar partidas não finalizadas
$fase_filtro = $_POST['fase'] ?? null;

$partidas = [];

if ($fase_filtro === '2ª Fase') {
    // Buscar da nova tabela partidas_2fase_torneio (todos contra todos)
    $sql_2fase_todos = "SELECT id, time1_id, time2_id, '2ª Fase' AS fase, grupo_id 
                        FROM partidas_2fase_torneio 
                        WHERE torneio_id = ? AND status != 'Finalizada'
                        ORDER BY grupo_id ASC, rodada ASC, id ASC";
    $stmt_2fase_todos = executeQuery($pdo, $sql_2fase_todos, [$torneio_id]);
    $partidas_todos = $stmt_2fase_todos ? $stmt_2fase_todos->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Buscar da nova tabela partidas_2fase_eliminatorias (semi-finais, finais)
    $sql_2fase_elim = "SELECT id, time1_id, time2_id, '2ª Fase' AS fase, NULL AS grupo_id 
                       FROM partidas_2fase_eliminatorias 
                       WHERE torneio_id = ? AND status != 'Finalizada'
                       ORDER BY rodada ASC, id ASC";
    $stmt_2fase_elim = executeQuery($pdo, $sql_2fase_elim, [$torneio_id]);
    $partidas_elim = $stmt_2fase_elim ? $stmt_2fase_elim->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Combinar resultados
    $partidas = array_merge($partidas_todos, $partidas_elim);
} elseif ($fase_filtro) {
    // Buscar da tabela antiga para outras fases
    if ($fase_filtro === 'Grupos') {
        // Para fase "Grupos", buscar também partidas com fase NULL ou vazia (1ª fase)
        $sql = "SELECT id, time1_id, time2_id, fase, grupo_id 
                FROM torneio_partidas 
                WHERE torneio_id = ? 
                AND (fase = ? OR fase IS NULL OR fase = '')
                AND status != 'Finalizada'
                AND grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%')
                ORDER BY fase ASC, grupo_id ASC, rodada ASC, id ASC";
        $stmt = executeQuery($pdo, $sql, [$torneio_id, $fase_filtro, $torneio_id]);
        $partidas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $sql = "SELECT id, time1_id, time2_id, fase, grupo_id 
                FROM torneio_partidas 
                WHERE torneio_id = ? AND fase = ? AND status != 'Finalizada'
                ORDER BY fase ASC, grupo_id ASC, rodada ASC, id ASC";
        $stmt = executeQuery($pdo, $sql, [$torneio_id, $fase_filtro]);
        $partidas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
} else {
    // Buscar todas as partidas não finalizadas (incluindo 2ª fase)
    // Primeiro buscar da tabela antiga
    $sql = "SELECT id, time1_id, time2_id, fase, grupo_id 
            FROM torneio_partidas 
            WHERE torneio_id = ? AND status != 'Finalizada'
            ORDER BY fase ASC, grupo_id ASC, rodada ASC, id ASC";
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    $partidas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Adicionar partidas da 2ª fase (nova tabela)
    $sql_2fase_todos = "SELECT id, time1_id, time2_id, '2ª Fase' AS fase, grupo_id 
                        FROM partidas_2fase_torneio 
                        WHERE torneio_id = ? AND status != 'Finalizada'
                        ORDER BY grupo_id ASC, rodada ASC, id ASC";
    $stmt_2fase_todos = executeQuery($pdo, $sql_2fase_todos, [$torneio_id]);
    $partidas_todos = $stmt_2fase_todos ? $stmt_2fase_todos->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $sql_2fase_elim = "SELECT id, time1_id, time2_id, '2ª Fase' AS fase, NULL AS grupo_id 
                       FROM partidas_2fase_eliminatorias 
                       WHERE torneio_id = ? AND status != 'Finalizada'
                       ORDER BY rodada ASC, id ASC";
    $stmt_2fase_elim = executeQuery($pdo, $sql_2fase_elim, [$torneio_id]);
    $partidas_elim = $stmt_2fase_elim ? $stmt_2fase_elim->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Combinar todos os resultados
    $partidas = array_merge($partidas, $partidas_todos, $partidas_elim);
}

echo json_encode([
    'success' => true,
    'partidas' => $partidas,
    'total' => count($partidas)
]);
?>

