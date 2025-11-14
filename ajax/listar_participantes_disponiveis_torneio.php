<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$torneio_id = (int)($_GET['torneio_id'] ?? 0);
$time_id = (int)($_GET['time_id'] ?? 0);

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

// Buscar participantes disponíveis (não estão em nenhum time do torneio)
// Primeiro, buscar todos os participantes que já estão em algum time do torneio
$sql_times = "SELECT DISTINCT tti.participante_id 
              FROM torneio_time_integrantes tti
              INNER JOIN torneio_times tt ON tt.id = tti.time_id
              WHERE tt.torneio_id = ?";
$stmt_times = executeQuery($pdo, $sql_times, [$torneio_id]);
$participantes_em_times = [];
if ($stmt_times) {
    $result = $stmt_times->fetchAll();
    $participantes_em_times = array_column($result, 'participante_id');
}

// Buscar todos os participantes do torneio
$sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id
        WHERE tp.torneio_id = ?";
$params = [$torneio_id];

// Se houver participantes em times, excluir da lista
if (!empty($participantes_em_times)) {
    $placeholders = implode(',', array_fill(0, count($participantes_em_times), '?'));
    $sql .= " AND tp.id NOT IN ($placeholders)";
    $params = array_merge($params, $participantes_em_times);
}

// Verificar se a coluna 'ordem' existe
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
$columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
$tem_ordem = in_array('ordem', $columns);

if ($tem_ordem) {
    $sql .= " ORDER BY tp.ordem, tp.nome_avulso, u.nome";
} else {
    $sql .= " ORDER BY tp.nome_avulso, u.nome";
}

$stmt = executeQuery($pdo, $sql, $params);
$participantes = $stmt ? $stmt->fetchAll() : [];

// Formatar para JSON
$resultado = [];
foreach ($participantes as $p) {
    $resultado[] = [
        'id' => (int)$p['id'],
        'usuario_id' => $p['usuario_id'] ? (int)$p['usuario_id'] : null,
        'nome' => $p['usuario_nome'] ?: $p['nome_avulso'],
        'nome_avulso' => $p['nome_avulso'],
        'foto_perfil' => $p['foto_perfil']
    ];
}

echo json_encode([
    'success' => true,
    'participantes' => $resultado
]);
?>

