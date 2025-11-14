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

$sistema_id = (int)($_POST['sistema_id'] ?? 0);
if ($sistema_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sistema inválido.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id 
        FROM sistemas_pontuacao sp
        JOIN grupos g ON g.id = sp.grupo_id
        WHERE sp.id = ?";
$stmt = executeQuery($pdo, $sql, [$sistema_id]);
$sistema = $stmt ? $stmt->fetch() : false;
if (!$sistema) {
    echo json_encode(['success' => false, 'message' => 'Sistema não encontrado.']);
    exit();
}

$sou_admin = ((int)$sistema['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode desativar sistemas.']);
    exit();
}

// Desativar sistema
$sql = "UPDATE sistemas_pontuacao SET ativo = 0 WHERE id = ?";
$result = executeQuery($pdo, $sql, [$sistema_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Sistema desativado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao desativar sistema.']);
}
?>

