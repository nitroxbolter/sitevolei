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
$pontos = isset($_POST['pontos']) ? (float)$_POST['pontos'] : null;

if ($jogo_id <= 0 || $usuario_id <= 0 || $pontos === null) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

if ($pontos < 0) {
    echo json_encode(['success' => false, 'message' => 'Pontos não podem ser negativos.']);
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
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode editar pontos.']);
    exit();
}

// Verificar se o usuário é participante do jogo
$sql = "SELECT id FROM sistema_pontuacao_participantes WHERE jogo_id = ? AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
if (!$stmt || !$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não é participante deste jogo.']);
    exit();
}

try {
    // Verificar se já existe registro de pontos
    $sql = "SELECT id FROM sistema_pontuacao_pontos WHERE jogo_id = ? AND usuario_id = ?";
    $stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
    $existe = $stmt && $stmt->fetch();
    
    if ($existe) {
        // Atualizar pontos existentes
        $sql = "UPDATE sistema_pontuacao_pontos SET pontos = ? WHERE jogo_id = ? AND usuario_id = ?";
        $result = executeQuery($pdo, $sql, [$pontos, $jogo_id, $usuario_id]);
    } else {
        // Inserir novos pontos
        $sql = "INSERT INTO sistema_pontuacao_pontos (jogo_id, usuario_id, pontos) VALUES (?, ?, ?)";
        $result = executeQuery($pdo, $sql, [$jogo_id, $usuario_id, $pontos]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Pontos atualizados com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar pontos.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar pontos: ' . $e->getMessage()]);
}
?>

