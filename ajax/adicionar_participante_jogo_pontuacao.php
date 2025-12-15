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

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);

if ($jogo_id <= 0 || $usuario_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id 
        FROM sistema_pontuacao_jogos spj
        JOIN sistemas_pontuacao sp ON sp.id = spj.sistema_id
        JOIN grupos g ON g.id = sp.grupo_id
        WHERE spj.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado.']);
    exit();
}

$sou_admin = ((int)$jogo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode adicionar participantes.']);
    exit();
}

// Verificar se já é participante
$sql = "SELECT id FROM sistema_pontuacao_participantes WHERE jogo_id = ? AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Este membro já é participante do jogo.']);
    exit();
}

// Adicionar participante
$sql = "INSERT INTO sistema_pontuacao_participantes (jogo_id, usuario_id) VALUES (?, ?)";
$result = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Participante adicionado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar participante.']);
}
?>

