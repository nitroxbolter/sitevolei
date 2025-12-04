<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

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

// Buscar participantes disponíveis (não estão em nenhum time ou não estão no time especificado)
$sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id";
        
if ($time_id > 0) {
    $sql .= " LEFT JOIN torneio_time_integrantes tti ON tti.participante_id = tp.id AND tti.time_id = ?
             WHERE tp.torneio_id = ? AND tti.id IS NULL";
    $params = [$time_id, $torneio_id];
} else {
    $sql .= " LEFT JOIN torneio_time_integrantes tti ON tti.participante_id = tp.id
             WHERE tp.torneio_id = ? AND tti.id IS NULL";
    $params = [$torneio_id];
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

