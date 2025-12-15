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

$time_id = (int)($_POST['time_id'] ?? 0);
$novo_nome = trim($_POST['novo_nome'] ?? '');

if ($time_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Time inválido.']);
    exit();
}

if (empty($novo_nome)) {
    echo json_encode(['success' => false, 'message' => 'O nome do time não pode estar vazio.']);
    exit();
}

// Buscar time e verificar permissão
$sql = "SELECT tt.*, t.criado_por, t.modalidade, g.administrador_id
        FROM torneio_times tt
        JOIN torneios t ON t.id = tt.torneio_id
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE tt.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
$time = $stmt ? $stmt->fetch() : false;

if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

// Validação de modalidade removida - agora permite renomear times em qualquer modalidade
// A validação anterior só permitia renomear em 'todos_contra_todos', 'todos_chaves', ou 'torneio_pro'
// Agora permite renomear em qualquer modalidade, desde que o usuário tenha permissão

// Verificar permissão
$sou_criador = ((int)$time['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $time['administrador_id'] && ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Atualizar nome do time
$sql_update = "UPDATE torneio_times SET nome = ? WHERE id = ?";
$stmt_update = executeQuery($pdo, $sql_update, [$novo_nome, $time_id]);

if ($stmt_update) {
    echo json_encode([
        'success' => true,
        'message' => 'Nome do time atualizado com sucesso!',
        'novo_nome' => htmlspecialchars($novo_nome)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar nome do time.']);
}
?>

