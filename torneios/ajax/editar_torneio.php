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
$debug_ids = [
    'post' => $_POST['torneio_id'] ?? null,
    'get' => $_GET['torneio_id'] ?? null,
    'request' => $_REQUEST['torneio_id'] ?? null
];

// Fallback: tentar obter do raw input (JSON ou querystring)
if ($torneio_id <= 0) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && isset($parsed['torneio_id'])) {
            $torneio_id = (int)$parsed['torneio_id'];
            $debug_ids['raw_json'] = $parsed['torneio_id'];
        } else {
            parse_str($raw, $parsed_qs);
            if (isset($parsed_qs['torneio_id'])) {
                $torneio_id = (int)$parsed_qs['torneio_id'];
                $debug_ids['raw_qs'] = $parsed_qs['torneio_id'];
            }
        }
    }
}
$nome = trim($_POST['nome'] ?? '');
$data_torneio = $_POST['data_torneio'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$max_participantes = isset($_POST['max_participantes']) ? (int)$_POST['max_participantes'] : null;

if ($torneio_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Torneio inválido.',
        'debug' => [
            'ids_recebidos' => $debug_ids
        ]
    ]);
    exit();
}

if ($nome === '' || $data_torneio === '') {
    echo json_encode(['success' => false, 'message' => 'Nome e data são obrigatórios.']);
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

// Verificar se já existe um torneio com o mesmo nome em status aberto (exceto o atual)
try {
    $sql_check = "SELECT id, nome, status FROM torneios WHERE nome = ? AND id != ? AND status NOT IN ('Finalizado', 'Cancelado')";
    $stmt_check = executeQuery($pdo, $sql_check, [$nome, $torneio_id]);
    if ($stmt_check) {
        $torneio_existente = $stmt_check->fetch();
        if ($torneio_existente) {
            echo json_encode([
                'success' => false, 
                'message' => "Já existe um torneio com o nome '$nome' em andamento (Status: {$torneio_existente['status']}). Escolha outro nome."
            ]);
            exit();
        }
    }
} catch (Exception $e) {
    // Continuar mesmo se houver erro na verificação
}

// Verificar qual coluna de data usar e quais colunas existem
try {
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    $campo_data = in_array('data_torneio', $columns) ? 'data_torneio' : 'data_inicio';
} catch (Exception $e) {
    $campo_data = 'data_inicio';
    $columns = [];
}

// Verificar se há participantes cadastrados antes de reduzir max_participantes
if ($max_participantes !== null && $max_participantes > 0) {
    $sql_check = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
    $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id]);
    $totalParticipantes = $stmt_check ? (int)$stmt_check->fetch()['total'] : 0;
    
    if ($totalParticipantes > $max_participantes) {
        echo json_encode(['success' => false, 'message' => 'Não é possível reduzir a quantidade máxima de participantes. Existem ' . $totalParticipantes . ' participantes cadastrados.']);
        exit();
    }
}

// Atualizar torneio
try {
    $sql_update = "UPDATE torneios SET nome = ?, $campo_data = ?";
    $valores = [$nome, $data_torneio];
    
    // Adicionar descrição se a coluna existir
    if (in_array('descricao', $columns)) {
        $sql_update .= ", descricao = ?";
        $valores[] = $descricao;
    }
    
    // Adicionar max_participantes se foi enviado
    if ($max_participantes !== null) {
        if (in_array('max_participantes', $columns)) {
            $sql_update .= ", max_participantes = ?";
            $valores[] = $max_participantes;
        }
        if (in_array('quantidade_participantes', $columns)) {
            $sql_update .= ", quantidade_participantes = ?";
            $valores[] = $max_participantes;
        }
    }
    
    $sql_update .= " WHERE id = ?";
    $valores[] = $torneio_id;
    
    $stmt_update = executeQuery($pdo, $sql_update, $valores);
    
    if ($stmt_update) {
        // Verificar se a atualização foi bem-sucedida
        $sql_verificar = "SELECT nome, $campo_data, max_participantes, quantidade_participantes FROM torneios WHERE id = ?";
        $stmt_verificar = executeQuery($pdo, $sql_verificar, [$torneio_id]);
        $torneio_atualizado = $stmt_verificar ? $stmt_verificar->fetch() : false;
        
        if ($torneio_atualizado) {
            error_log("Torneio atualizado com sucesso - ID: $torneio_id, Nome: " . $torneio_atualizado['nome']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Torneio atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar torneio.']);
    }
} catch (Exception $e) {
    error_log("Erro ao atualizar torneio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>

