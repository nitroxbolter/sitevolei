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

$sistema_id = (int)($_POST['sistema_id'] ?? 0);
$numero_jogo = (int)($_POST['numero_jogo'] ?? 0);
$data_jogo = $_POST['data_jogo'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');

// Validar dados
if ($sistema_id <= 0 || $numero_jogo <= 0 || $data_jogo === '') {
    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não preenchidos.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.administrador_id FROM sistemas_pontuacao sp
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
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode criar jogos.']);
    exit();
}

// Verificar se número do jogo já existe
$sql = "SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ? AND numero_jogo = ?";
$stmt = executeQuery($pdo, $sql, [$sistema_id, $numero_jogo]);
if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Já existe um jogo com este número.']);
    exit();
}

// Validar participantes
$participantes = $_POST['participantes'] ?? [];
if (empty($participantes) || !is_array($participantes)) {
    echo json_encode(['success' => false, 'message' => 'Selecione pelo menos um participante.']);
    exit();
}

// Iniciar transação
$pdo->beginTransaction();

try {
    // Criar jogo
    $sql = "INSERT INTO sistema_pontuacao_jogos (sistema_id, numero_jogo, data_jogo, descricao) VALUES (?, ?, ?, ?)";
    $result = executeQuery($pdo, $sql, [$sistema_id, $numero_jogo, $data_jogo, $descricao ?: null]);
    
    if (!$result) {
        throw new Exception('Erro ao criar jogo');
    }
    
    $jogo_id = (int)$pdo->lastInsertId();
    
    // Inserir participantes
    $sql = "INSERT INTO sistema_pontuacao_participantes (jogo_id, usuario_id) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($participantes as $usuario_id) {
        $usuario_id = (int)$usuario_id;
        if ($usuario_id > 0) {
            $stmt->execute([$jogo_id, $usuario_id]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Jogo criado com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao criar jogo: ' . $e->getMessage()]);
}
?>

