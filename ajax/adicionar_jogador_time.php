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

$time_id = (int)($_POST['time_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);

if ($time_id <= 0 || $usuario_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id 
        FROM sistema_pontuacao_times st
        JOIN sistema_pontuacao_jogos spj ON spj.id = st.jogo_id
        JOIN sistemas_pontuacao sp ON sp.id = spj.sistema_id
        JOIN grupos g ON g.id = sp.grupo_id
        WHERE st.id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id]);
$time = $stmt ? $stmt->fetch() : false;
if (!$time) {
    echo json_encode(['success' => false, 'message' => 'Time não encontrado.']);
    exit();
}

$sou_admin = ((int)$time['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode adicionar jogadores.']);
    exit();
}

// Verificar se o jogador é participante do jogo
$sql = "SELECT id FROM sistema_pontuacao_participantes 
        WHERE jogo_id = (SELECT jogo_id FROM sistema_pontuacao_times WHERE id = ?) 
        AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$time_id, $usuario_id]);
if (!$stmt || !$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Jogador não é participante deste jogo.']);
    exit();
}

// Adicionar jogador ao time
$sql = "INSERT INTO sistema_pontuacao_time_jogadores (time_id, usuario_id) VALUES (?, ?)";
$result = executeQuery($pdo, $sql, [$time_id, $usuario_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Jogador adicionado ao time!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar jogador. Jogador já está no time?']);
}
?>

