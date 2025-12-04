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
$usuario_id = (int)$_SESSION['user_id'];

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Verificar se o torneio existe e está aberto para inscrições
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

// Verificar se está aberto para inscrições
$inscricoes_abertas = isset($torneio['inscricoes_abertas']) ? (int)$torneio['inscricoes_abertas'] : 0;
if ($inscricoes_abertas != 1) {
    echo json_encode(['success' => false, 'message' => 'Este torneio não está aberto para inscrições.']);
    exit();
}

// Verificar se o usuário já está participando do torneio
$sql_check = "SELECT id FROM torneio_participantes WHERE torneio_id = ? AND usuario_id = ?";
$stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $usuario_id]);
if ($stmt_check && $stmt_check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Você já está participando deste torneio.']);
    exit();
}

// Verificar se já existe uma solicitação
$sql_solicitacao = "SELECT id, status FROM torneio_solicitacoes WHERE torneio_id = ? AND usuario_id = ?";
$stmt_solicitacao = executeQuery($pdo, $sql_solicitacao, [$torneio_id, $usuario_id]);
$solicitacao_existente = $stmt_solicitacao ? $stmt_solicitacao->fetch() : false;

if ($solicitacao_existente) {
    $status_existente = $solicitacao_existente['status'] ?? '';
    $solicitacao_id = $solicitacao_existente['id'] ?? 0;
    
    if ($status_existente === 'Pendente') {
        echo json_encode(['success' => false, 'message' => 'Você já possui uma solicitação pendente para este torneio.']);
        exit();
    } elseif ($status_existente === 'Aprovada' || $status_existente === 'Aceita') {
        // Se foi aprovada, verificar se o usuário ainda está participando
        // Se não estiver mais (foi removido), permitir criar nova solicitação
        $sql_check_participante = "SELECT id FROM torneio_participantes WHERE torneio_id = ? AND usuario_id = ?";
        $stmt_check_participante = executeQuery($pdo, $sql_check_participante, [$torneio_id, $usuario_id]);
        $ainda_participa = $stmt_check_participante && $stmt_check_participante->fetch();
        
        if ($ainda_participa) {
            echo json_encode(['success' => false, 'message' => 'Sua solicitação já foi aprovada e você está participando do torneio.']);
            exit();
        } else {
            // Foi aprovada mas foi removido, permitir criar nova (remover a antiga)
            $sql_delete = "DELETE FROM torneio_solicitacoes WHERE id = ?";
            executeQuery($pdo, $sql_delete, [$solicitacao_id]);
        }
    } elseif ($status_existente === 'Rejeitada') {
        // Se foi rejeitada, permitir criar uma nova solicitação (deletar a antiga primeiro)
        $sql_delete = "DELETE FROM torneio_solicitacoes WHERE id = ?";
        executeQuery($pdo, $sql_delete, [$solicitacao_id]);
    } else {
        // Para qualquer outro status desconhecido, remover e permitir criar nova
        $sql_delete = "DELETE FROM torneio_solicitacoes WHERE id = ?";
        executeQuery($pdo, $sql_delete, [$solicitacao_id]);
    }
}

// Verificar limite de participantes
$maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
if ($maxParticipantes > 0) {
    $sql_total = "SELECT COUNT(*) AS total FROM torneio_participantes WHERE torneio_id = ?";
    $stmt_total = executeQuery($pdo, $sql_total, [$torneio_id]);
    $totalAtual = $stmt_total ? (int)$stmt_total->fetch()['total'] : 0;
    
    if ($totalAtual >= $maxParticipantes) {
        echo json_encode(['success' => false, 'message' => 'O torneio já atingiu o limite de participantes.']);
        exit();
    }
}

// Criar solicitação
try {
    // Verificar se a tabela existe
    try {
        $check_table = $pdo->query("SHOW TABLES LIKE 'torneio_solicitacoes'");
        if (!$check_table || $check_table->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Tabela de solicitações não encontrada. Entre em contato com o administrador.']);
            exit();
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar tabela: ' . $e->getMessage()]);
        exit();
    }
    
    $sql_insert = "INSERT INTO torneio_solicitacoes (torneio_id, usuario_id, status) VALUES (?, ?, 'Pendente')";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    if ($stmt_insert) {
        $result = $stmt_insert->execute([$torneio_id, $usuario_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Solicitação de participação enviada com sucesso! Aguarde a aprovação do administrador.']);
        } else {
            $error_info = $stmt_insert->errorInfo();
            $error_msg = $error_info[2] ?? 'Erro desconhecido';
            error_log("Erro ao inserir solicitação: " . $error_msg);
            echo json_encode(['success' => false, 'message' => 'Erro ao enviar solicitação: ' . $error_msg]);
        }
    } else {
        $error_info = $pdo->errorInfo();
        $error_msg = $error_info[2] ?? 'Erro desconhecido';
        error_log("Erro ao preparar query: " . $error_msg);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar query: ' . $error_msg]);
    }
} catch (PDOException $e) {
    error_log("PDOException ao enviar solicitação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar solicitação: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Exception ao enviar solicitação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar solicitação: ' . $e->getMessage()]);
}
?>

