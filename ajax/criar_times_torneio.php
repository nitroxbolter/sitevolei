<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

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

if (!podeGerenciarTorneio($pdo, $torneio_id, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

if (!torneioPodeEditarEstrutura($torneio['status'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Não é possível criar ou recriar times depois que o torneio começou ou foi encerrado.']);
    exit();
}

$quantidade_times = $torneio['quantidade_times'] ?? null;
$integrantes_por_time = $torneio['integrantes_por_time'] ?? null;

if (!$quantidade_times || !$integrantes_por_time) {
    echo json_encode(['success' => false, 'message' => 'Configure quantidade de times e integrantes por time primeiro.']);
    exit();
}

if (torneioTemJogosGerados($pdo, $torneio_id)) {
    echo json_encode(['success' => false, 'message' => 'Não é possível recriar times com jogos já gerados. Limpe os jogos antes de alterar a estrutura.']);
    exit();
}

$stmt_times = executeQuery($pdo, "SELECT COUNT(*) AS total FROM torneio_times WHERE torneio_id = ?", [$torneio_id]);
$times_existentes = $stmt_times ? (int)$stmt_times->fetch()['total'] : 0;
if ($times_existentes > 0) {
    $sql_integrantes = "SELECT COUNT(*) AS total
                        FROM torneio_time_integrantes tti
                        INNER JOIN torneio_times tt ON tt.id = tti.time_id
                        WHERE tt.torneio_id = ?";
    $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$torneio_id]);
    $integrantes_vinculados = $stmt_integrantes ? (int)$stmt_integrantes->fetch()['total'] : 0;

    if ($integrantes_vinculados > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível recriar times com integrantes vinculados. Remova os integrantes ou limpe os times antes de gerar novamente.']);
        exit();
    }
}

$pdo->beginTransaction();
try {
    executeQuery($pdo, "SELECT id FROM torneios WHERE id = ? FOR UPDATE", [$torneio_id]);

    // Verificar se já existem times e excluir antes de criar novos
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
    echo json_encode(['success' => false, 'message' => 'Não foi possível criar os times agora.']);
}
?>
