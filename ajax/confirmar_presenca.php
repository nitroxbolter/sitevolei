<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$jogo_id = (int)$_POST['jogo_id'];
$status = sanitizar($_POST['status']);
$usuario_id = $_SESSION['user_id'];

// Validar dados
if (empty($jogo_id) || !in_array($status, ['Confirmado', 'Ausente'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

// Verificar se o jogo existe e está aberto
$sql = "SELECT * FROM jogos WHERE id = ? AND status = 'Aberto' AND data_jogo > NOW()";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado ou não disponível']);
    exit();
}

// Verificar se o usuário pode participar (membro do grupo)
$sql = "SELECT gm.id FROM grupo_membros gm 
        JOIN grupos g ON gm.grupo_id = g.id 
        WHERE gm.usuario_id = ? AND g.id = ? AND gm.ativo = 1";
$stmt = executeQuery($pdo, $sql, [$usuario_id, $jogo['grupo_id']]);
$membro = $stmt ? $stmt->fetch() : false;

if (!$membro) {
    echo json_encode(['success' => false, 'message' => 'Você não é membro deste grupo']);
    exit();
}

// Confirmar ou cancelar presença
$sql = "INSERT INTO confirmacoes_presenca (jogo_id, usuario_id, status) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE status = ?";
$result = executeQuery($pdo, $sql, [$jogo_id, $usuario_id, $status, $status]);

if ($result) {
    // Atualizar vagas disponíveis
    $sql = "UPDATE jogos SET vagas_disponiveis = max_jogadores - (
                SELECT COUNT(*) FROM confirmacoes_presenca 
                WHERE jogo_id = ? AND status = 'Confirmado'
            ) WHERE id = ?";
    executeQuery($pdo, $sql, [$jogo_id, $jogo_id]);
    
    $mensagem = $status === 'Confirmado' ? 'Presença confirmada com sucesso!' : 'Presença cancelada com sucesso!';
    echo json_encode(['success' => true, 'message' => $mensagem]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}
?>
