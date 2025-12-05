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
$max_participantes = isset($_POST['max_participantes']) ? (int)$_POST['max_participantes'] : null;
$quantidade_times = isset($_POST['quantidade_times']) ? (int)$_POST['quantidade_times'] : null;
$integrantes_por_time = isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' ? (int)$_POST['integrantes_por_time'] : null;

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

// Verificar se há participantes cadastrados
$sql = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$totalParticipantes = $stmt ? (int)$stmt->fetch()['total'] : 0;

// Validar quantidade máxima de participantes
if ($max_participantes !== null && $max_participantes > 0) {
    if ($totalParticipantes > $max_participantes) {
        echo json_encode(['success' => false, 'message' => 'Não é possível reduzir a quantidade máxima de participantes. Existem ' . $totalParticipantes . ' participantes cadastrados.']);
        exit();
    }
}

try {
    // Verificar quais colunas existem na tabela
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    
    $updates = [];
    
    // Atualizar max_participantes ou quantidade_participantes
    if ($max_participantes !== null) {
        if (in_array('max_participantes', $columns)) {
            $updates[] = "max_participantes = " . (int)$max_participantes;
        }
        if (in_array('quantidade_participantes', $columns)) {
            $updates[] = "quantidade_participantes = " . (int)$max_participantes;
        }
    }
    
    // Atualizar quantidade_times (sempre atualizar se foi enviado, mesmo que seja 0)
    if (isset($_POST['quantidade_times']) && in_array('quantidade_times', $columns)) {
        $updates[] = "quantidade_times = " . (int)$quantidade_times;
    }
    
    // Atualizar integrantes_por_time (sempre atualizar se foi enviado, mesmo que seja 0)
    if (isset($_POST['integrantes_por_time']) && $_POST['integrantes_por_time'] !== '' && in_array('integrantes_por_time', $columns)) {
        $updates[] = "integrantes_por_time = " . (int)$integrantes_por_time;
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE torneios SET " . implode(', ', $updates) . " WHERE id = ?";
        executeQuery($pdo, $sql, [$torneio_id]);
        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma configuração para atualizar.']);
    }
} catch (Exception $e) {
    error_log("Erro ao atualizar configurações do torneio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>

