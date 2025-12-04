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

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$pontos = $_POST['pontos'] ?? [];

if ($jogo_id <= 0 || empty($pontos)) {
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
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador pode salvar pontos.']);
    exit();
}

// Iniciar transação
$pdo->beginTransaction();

try {
    // Remover pontos existentes
    $sql = "DELETE FROM sistema_pontuacao_pontos WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Inserir novos pontos
    $sql = "INSERT INTO sistema_pontuacao_pontos (jogo_id, usuario_id, pontos) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($pontos as $usuario_id => $pontos_valor) {
        $usuario_id = (int)$usuario_id;
        $pontos_valor = (float)$pontos_valor;
        
        if ($usuario_id > 0 && $pontos_valor >= 0) {
            $stmt->execute([$jogo_id, $usuario_id, $pontos_valor]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pontos salvos com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar pontos: ' . $e->getMessage()]);
}
?>

