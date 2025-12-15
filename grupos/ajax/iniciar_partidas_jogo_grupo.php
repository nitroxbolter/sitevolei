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
if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido.']);
    exit();
}

// Verificar se o jogo existe e se o usuário é admin do grupo
$sql = "SELECT gj.*, g.administrador_id 
        FROM grupo_jogos gj
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gj.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado.']);
    exit();
}

$sou_admin = ((int)$jogo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Buscar times
$sql = "SELECT * FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$times = $stmt ? $stmt->fetchAll() : [];

if (count($times) < 2) {
    echo json_encode(['success' => false, 'message' => 'É necessário pelo menos 2 times para gerar partidas.']);
    exit();
}

$pdo->beginTransaction();
try {
    // Limpar partidas existentes
    $sql = "DELETE FROM grupo_jogo_partidas WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Criar partidas (todos contra todos)
    $rodada = 1;
    $partidas_por_rodada = [];
    
    for ($i = 0; $i < count($times); $i++) {
        for ($j = $i + 1; $j < count($times); $j++) {
            $time1_id = $times[$i]['id'];
            $time2_id = $times[$j]['id'];
            
            // Distribuir em rodadas
            $rodada_atual = ($i + $j) % (count($times) - 1) + 1;
            if ($rodada_atual == 0) $rodada_atual = count($times) - 1;
            
            $sql = "INSERT INTO grupo_jogo_partidas (jogo_id, time1_id, time2_id, rodada, status) 
                    VALUES (?, ?, ?, ?, 'Agendada')";
            executeQuery($pdo, $sql, [$jogo_id, $time1_id, $time2_id, $rodada_atual]);
        }
    }
    
    // Inicializar classificação
    $sql = "DELETE FROM grupo_jogo_classificacao WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    foreach ($times as $time) {
        $sql = "INSERT INTO grupo_jogo_classificacao (jogo_id, time_id) VALUES (?, ?)";
        executeQuery($pdo, $sql, [$jogo_id, $time['id']]);
    }
    
    // Atualizar status
    $sql = "UPDATE grupo_jogos SET status = 'Em Andamento' WHERE id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Partidas criadas com sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao criar partidas: ' . $e->getMessage()]);
}
?>

