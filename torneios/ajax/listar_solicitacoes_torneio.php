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

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar permissão (criador ou admin)
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

// Buscar solicitações pendentes
$sql_solicitacoes = "SELECT ts.*, u.nome AS usuario_nome, u.foto_perfil, u.email, u.telefone, u.id AS usuario_id
                     FROM torneio_solicitacoes ts
                     JOIN usuarios u ON u.id = ts.usuario_id
                     WHERE ts.torneio_id = ? AND ts.status = 'Pendente'
                     ORDER BY ts.data_solicitacao ASC";
$stmt_solicitacoes = executeQuery($pdo, $sql_solicitacoes, [$torneio_id]);
$solicitacoes = $stmt_solicitacoes ? $stmt_solicitacoes->fetchAll(PDO::FETCH_ASSOC) : [];

echo json_encode(['success' => true, 'solicitacoes' => $solicitacoes]);
?>

