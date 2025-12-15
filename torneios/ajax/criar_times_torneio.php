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

// Capturar torneio_id de múltiplas fontes para garantir que seja encontrado
$torneio_id = 0;
if (isset($_POST['torneio_id']) && $_POST['torneio_id'] > 0) {
    $torneio_id = (int)$_POST['torneio_id'];
} elseif (isset($_GET['torneio_id']) && $_GET['torneio_id'] > 0) {
    $torneio_id = (int)$_GET['torneio_id'];
} elseif (isset($_REQUEST['torneio_id']) && $_REQUEST['torneio_id'] > 0) {
    $torneio_id = (int)$_REQUEST['torneio_id'];
} else {
    // Tentar pegar do raw input (JSON)
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $parsed = json_decode($rawInput, true);
        if (isset($parsed['torneio_id']) && $parsed['torneio_id'] > 0) {
            $torneio_id = (int)$parsed['torneio_id'];
        }
    }
}

if ($torneio_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Torneio inválido. ID não foi encontrado ou é inválido.',
        'error_code' => 'TORNEIO_ID_INVALIDO'
    ]);
    exit();
}

// Verificar permissão e obter configuração
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

$quantidade_times = (int)($torneio['quantidade_times'] ?? 0);
$integrantes_por_time = (int)($torneio['integrantes_por_time'] ?? 0);

if ($quantidade_times <= 0 || $integrantes_por_time <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Configure quantidade de times e integrantes por time primeiro. Vá em "Configurações do Torneio" e salve as configurações antes de criar os times.'
    ]);
    exit();
}

// Verificar quantos times já existem
$sql = "SELECT COUNT(*) AS total FROM torneio_times WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times_existentes = $stmt ? (int)$stmt->fetch()['total'] : 0;

$pdo->beginTransaction();
try {
    // SEMPRE limpar tudo primeiro (integrantes e times) antes de criar novos
    // Isso garante que não haverá duplicação mesmo se já existirem times
    if ($times_existentes > 0) {
        // Primeiro, remover todos os integrantes dos times deste torneio
        $sql = "DELETE tti FROM torneio_time_integrantes tti
                INNER JOIN torneio_times tt ON tt.id = tti.time_id
                WHERE tt.torneio_id = ?";
        executeQuery($pdo, $sql, [$torneio_id]);
    }
    
    // Sempre remover todos os times (mesmo que não existam, não faz mal)
    $sql = "DELETE FROM torneio_times WHERE torneio_id = ?";
    executeQuery($pdo, $sql, [$torneio_id]);
    
    // Array de cores diferentes para cada time
    $cores = [
        '#007bff',  // Azul
        '#28a745',  // Verde
        '#dc3545',  // Vermelho
        '#ffc107',  // Amarelo
        '#17a2b8',  // Ciano
        '#6f42c1',  // Roxo
        '#e83e8c',  // Rosa
        '#fd7e14',  // Laranja
        '#20c997',  // Verde água
        '#6610f2',  // Índigo
        '#343a40',  // Cinza escuro
        '#6c757d'   // Cinza
    ];
    
    // Criar exatamente a quantidade de times configurada
    $times_criados = 0;
    $ids_criados = [];
    
    for ($i = 1; $i <= $quantidade_times; $i++) {
        $cor = $cores[($i - 1) % count($cores)];
        $nome = 'Time ' . $i;
        
        // Verificar se já existe um time com essa ordem (dupla verificação)
        $sql_check = "SELECT id FROM torneio_times WHERE torneio_id = ? AND ordem = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $i]);
        if ($stmt_check && $stmt_check->fetch()) {
            error_log("AVISO: Time com ordem $i já existe para torneio $torneio_id - pulando criação");
            continue;
        }
        
        $sql = "INSERT INTO torneio_times (torneio_id, nome, cor, ordem) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$torneio_id, $nome, $cor, $i]);
        
        if ($result) {
            $id_criado = (int)$pdo->lastInsertId();
            $ids_criados[] = $id_criado;
            $times_criados++;
        } else {
            $error = $stmt->errorInfo();
            error_log("Erro ao criar time $i para torneio $torneio_id: " . ($error[2] ?? 'Erro desconhecido'));
        }
    }
    
    // Verificar quantos times foram realmente criados e listar todos
    $sql = "SELECT id, nome, ordem FROM torneio_times WHERE torneio_id = ? ORDER BY ordem, id";
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    $times_verificados = $stmt ? $stmt->fetchAll() : [];
    $total_criado = count($times_verificados);
    
    // Verificar duplicatas
    $nomes = array_column($times_verificados, 'nome');
    $ordens = array_column($times_verificados, 'ordem');
    $nomes_unicos = array_unique($nomes);
    $ordens_unicas = array_unique($ordens);
    
    if (count($nomes) !== count($nomes_unicos)) {
        error_log("ERRO: Times com nomes duplicados encontrados: " . json_encode($nomes));
    }
    if (count($ordens) !== count($ordens_unicas)) {
        error_log("ERRO: Times com ordens duplicadas encontradas: " . json_encode($ordens));
    }
    
    error_log("Times verificados no torneio $torneio_id: " . json_encode($times_verificados));
    
    $pdo->commit();
    
    if ($total_criado != $quantidade_times) {
        error_log("AVISO: Esperado criar {$quantidade_times} times, mas foram criados {$total_criado} times no torneio {$torneio_id}");
    }
    
    $mensagem = $times_existentes > 0 
        ? "Times atualizados com sucesso! {$total_criado} time(s) criado(s)." 
        : "Times criados com sucesso! {$total_criado} time(s) criado(s).";
    
    echo json_encode([
        'success' => true, 
        'message' => $mensagem,
        'times_criados' => $total_criado,
        'times_esperados' => $quantidade_times
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao criar times: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar times: ' . $e->getMessage()]);
}
?>

