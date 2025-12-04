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

$solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);
$acao = $_POST['acao'] ?? ''; // 'aprovar' ou 'rejeitar'

if ($solicitacao_id <= 0 || !in_array($acao, ['aprovar', 'rejeitar'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Buscar solicitação com status atualizado
$sql_solicitacao = "SELECT ts.*, t.*, g.administrador_id
                    FROM torneio_solicitacoes ts
                    JOIN torneios t ON t.id = ts.torneio_id
                    LEFT JOIN grupos g ON g.id = t.grupo_id
                    WHERE ts.id = ?";
$stmt_solicitacao = executeQuery($pdo, $sql_solicitacao, [$solicitacao_id]);
$solicitacao = $stmt_solicitacao ? $stmt_solicitacao->fetch() : false;

if (!$solicitacao) {
    echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada.']);
    exit();
}

// Verificar permissão
$sou_criador = ((int)$solicitacao['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $solicitacao['administrador_id'] && ((int)$solicitacao['administrador_id'] === (int)$_SESSION['user_id']);

if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar se já foi respondida - buscar status diretamente da tabela para garantir que está atualizado
$sql_check_status = "SELECT status FROM torneio_solicitacoes WHERE id = ?";
$stmt_check_status = executeQuery($pdo, $sql_check_status, [$solicitacao_id]);
$status_atual = $stmt_check_status ? $stmt_check_status->fetch()['status'] : null;

if (empty($status_atual) || trim($status_atual) !== 'Pendente') {
    echo json_encode(['success' => false, 'message' => 'Esta solicitação já foi respondida.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Verificar novamente o status dentro da transação para evitar race condition
    $sql_check_status_trans = "SELECT status FROM torneio_solicitacoes WHERE id = ? FOR UPDATE";
    $stmt_check_status_trans = executeQuery($pdo, $sql_check_status_trans, [$solicitacao_id]);
    $status_trans = $stmt_check_status_trans ? $stmt_check_status_trans->fetch()['status'] : null;
    
    if (empty($status_trans) || trim($status_trans) !== 'Pendente') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Esta solicitação já foi respondida.']);
        exit();
    }
    
    $novo_status = $acao === 'aprovar' ? 'Aprovada' : 'Rejeitada';
    
    // Atualizar solicitação
    $sql_update = "UPDATE torneio_solicitacoes 
                   SET status = ?, data_resposta = NOW(), respondido_por = ?
                   WHERE id = ? AND status = 'Pendente'";
    $stmt_update = executeQuery($pdo, $sql_update, [$novo_status, $_SESSION['user_id'], $solicitacao_id]);
    
    // Verificar se a atualização foi bem-sucedida
    if (!$stmt_update || $stmt_update->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar solicitação. Ela pode ter sido respondida por outro administrador.']);
        exit();
    }
    
    // Se aprovada, adicionar como participante
    if ($acao === 'aprovar') {
        // Verificar limite de participantes
        $maxParticipantes = $solicitacao['quantidade_participantes'] ?? $solicitacao['max_participantes'] ?? 0;
        if ($maxParticipantes > 0) {
            $sql_total = "SELECT COUNT(*) AS total FROM torneio_participantes WHERE torneio_id = ?";
            $stmt_total = executeQuery($pdo, $sql_total, [$solicitacao['torneio_id']]);
            $totalAtual = $stmt_total ? (int)$stmt_total->fetch()['total'] : 0;
            
            if ($totalAtual >= $maxParticipantes) {
                throw new Exception('O torneio já atingiu o limite de participantes.');
            }
        }
        
        // Verificar se já está participando
        $sql_check = "SELECT id FROM torneio_participantes WHERE torneio_id = ? AND usuario_id = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$solicitacao['torneio_id'], $solicitacao['usuario_id']]);
        if (!$stmt_check || !$stmt_check->fetch()) {
            // Verificar se a coluna ordem existe
            $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
            $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
            $tem_ordem = in_array('ordem', $columns);
            
            if ($tem_ordem) {
                $sql_insert = "INSERT INTO torneio_participantes (torneio_id, usuario_id, ordem) VALUES (?, ?, ?)";
                $totalAtual = $totalAtual ?? 0;
                executeQuery($pdo, $sql_insert, [$solicitacao['torneio_id'], $solicitacao['usuario_id'], $totalAtual + 1]);
            } else {
                $sql_insert = "INSERT INTO torneio_participantes (torneio_id, usuario_id) VALUES (?, ?)";
                executeQuery($pdo, $sql_insert, [$solicitacao['torneio_id'], $solicitacao['usuario_id']]);
            }
        }
    }
    
    // Se aprovada, enviar notificação para o usuário
    if ($acao === 'aprovar') {
        try {
            $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
            if ($st && $st->fetch()) {
                $titulo = 'Solicitação de participação aprovada';
                $msg = 'Sua solicitação de participação no torneio "' . htmlspecialchars($solicitacao['nome']) . '" foi aprovada!';
                executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$solicitacao['usuario_id'], $titulo, $msg]);
            }
        } catch (Exception $e) {
            // Erro ao criar notificação não deve impedir a aprovação
            error_log("Erro ao criar notificação: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    $mensagem = $acao === 'aprovar' 
        ? 'Solicitação aprovada e participante adicionado com sucesso!' 
        : 'Solicitação rejeitada.';
    echo json_encode(['success' => true, 'message' => $mensagem]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

