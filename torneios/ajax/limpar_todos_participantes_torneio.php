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

// Contar quantos participantes serão removidos
$sql = "SELECT COUNT(*) AS total FROM torneio_participantes WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$total_participantes = $stmt ? (int)$stmt->fetch()['total'] : 0;

if ($total_participantes == 0) {
    echo json_encode(['success' => false, 'message' => 'Não há participantes para remover.']);
    exit();
}

// Iniciar transação
$pdo->beginTransaction();

try {
    // Primeiro, remover todos os integrantes dos times (relacionados aos participantes)
    $sql = "DELETE tti FROM torneio_time_integrantes tti
            INNER JOIN torneio_participantes tp ON tp.id = tti.participante_id
            WHERE tp.torneio_id = ?";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Depois, remover todos os participantes
    $sql = "DELETE FROM torneio_participantes WHERE torneio_id = ?";
    $result = executeQuery($pdo, $sql, [$torneio_id]);
    
    $pdo->commit();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => "Todos os participantes ({$total_participantes}) foram removidos com sucesso!"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover participantes.']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao limpar participantes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao remover participantes: ' . $e->getMessage()]);
}
?>

