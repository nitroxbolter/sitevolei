<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

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

$quantidade_times = $torneio['quantidade_times'] ?? null;
$integrantes_por_time = $torneio['integrantes_por_time'] ?? null;

if (!$quantidade_times || !$integrantes_por_time) {
    echo json_encode(['success' => false, 'message' => 'Configure quantidade de times e integrantes por time primeiro.']);
    exit();
}

// Verificar se já existem times e excluir antes de criar novos
$sql = "DELETE FROM torneio_times WHERE torneio_id = ?";
executeQuery($pdo, $sql, [$torneio_id]);

$pdo->beginTransaction();
try {
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
    
    // Criar cada time com nome único e cor diferente
    for ($i = 1; $i <= (int)$quantidade_times; $i++) {
        $cor = $cores[($i - 1) % count($cores)];
        $nome = 'Time ' . $i;
        
        // Verificar se já existe um time com esse nome e ordem (evitar duplicação)
        $sql_check = "SELECT id FROM torneio_times WHERE torneio_id = ? AND ordem = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $i]);
        $existe = $stmt_check ? $stmt_check->fetch() : false;
        
        if (!$existe) {
            $sql = "INSERT INTO torneio_times (torneio_id, nome, cor, ordem) VALUES (?, ?, ?, ?)";
            executeQuery($pdo, $sql, [$torneio_id, $nome, $cor, $i]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Times criados com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao criar times: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao criar times: ' . $e->getMessage()]);
}
?>

