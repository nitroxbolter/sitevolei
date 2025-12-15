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
$quantidade_grupos = (int)($_POST['quantidade_grupos'] ?? 0);
$distribuicao_json = $_POST['distribuicao'] ?? '{}';

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

if ($quantidade_grupos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantidade de grupos inválida.']);
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

// Decodificar distribuição
$distribuicao = json_decode($distribuicao_json, true);
if (!is_array($distribuicao)) {
    echo json_encode(['success' => false, 'message' => 'Distribuição inválida.']);
    exit();
}

// Buscar todos os times do torneio para validar
$sql = "SELECT id FROM torneio_times WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times_torneio = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Validar que todos os times estão na distribuição
$times_distribuidos = [];
foreach ($distribuicao as $grupo_ordem => $times_ids) {
    foreach ($times_ids as $time_id) {
        $time_id = (int)$time_id;
        if (!in_array($time_id, $times_torneio)) {
            echo json_encode(['success' => false, 'message' => "Time ID $time_id não pertence ao torneio."]);
            exit();
        }
        if (in_array($time_id, $times_distribuidos)) {
            echo json_encode(['success' => false, 'message' => "Time ID $time_id está duplicado na distribuição."]);
            exit();
        }
        $times_distribuidos[] = $time_id;
    }
}

if (count($times_distribuidos) !== count($times_torneio)) {
    echo json_encode(['success' => false, 'message' => 'Nem todos os times foram distribuídos.']);
    exit();
}

$pdo->beginTransaction();

try {
    // Limpar grupos existentes
    $sql_limpar_grupos = "DELETE FROM torneio_grupo_times WHERE grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ?)";
    executeQuery($pdo, $sql_limpar_grupos, [$torneio_id]);
    $sql_limpar = "DELETE FROM torneio_grupos WHERE torneio_id = ?";
    executeQuery($pdo, $sql_limpar, [$torneio_id]);
    
    // Função para converter número em letra
    $numeroParaLetra = function($numero) {
        return chr(64 + $numero);
    };
    
    // Criar grupos
    $grupos_criados = [];
    for ($g = 1; $g <= $quantidade_grupos; $g++) {
        $letra_grupo = $numeroParaLetra($g);
        $sql_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
        executeQuery($pdo, $sql_grupo, [$torneio_id, "Grupo " . $letra_grupo, $g]);
        $grupo_id = $pdo->lastInsertId();
        $grupos_criados[$g] = $grupo_id;
    }
    
    // Adicionar times aos grupos conforme distribuição
    foreach ($distribuicao as $grupo_ordem => $times_ids) {
        $grupo_ordem = (int)$grupo_ordem;
        if (!isset($grupos_criados[$grupo_ordem])) {
            throw new Exception("Grupo ordem $grupo_ordem não encontrado.");
        }
        
        $grupo_id = $grupos_criados[$grupo_ordem];
        
        foreach ($times_ids as $time_id) {
            $time_id = (int)$time_id;
            $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
            executeQuery($pdo, $sql_add_time, [$grupo_id, $time_id]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Distribuição de grupos salva com sucesso!'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao salvar distribuição de grupos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar distribuição: ' . $e->getMessage()
    ]);
}
?>

