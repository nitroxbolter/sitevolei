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

$partida_id = (int)($_POST['partida_id'] ?? 0);
$pontos_time1 = (int)($_POST['pontos_time1'] ?? 0);
$pontos_time2 = (int)($_POST['pontos_time2'] ?? 0);

if ($partida_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Partida inválida.']);
    exit();
}

// Buscar partida e verificar permissão
$sql = "SELECT gjp.*, gj.grupo_id, g.administrador_id
        FROM grupo_jogo_partidas gjp
        JOIN grupo_jogos gj ON gj.id = gjp.jogo_id
        JOIN grupos g ON g.id = gj.grupo_id
        WHERE gjp.id = ?";
$stmt = executeQuery($pdo, $sql, [$partida_id]);
$partida = $stmt ? $stmt->fetch() : false;

if (!$partida) {
    echo json_encode(['success' => false, 'message' => 'Partida não encontrada.']);
    exit();
}

$sou_admin = ((int)$partida['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Determinar vencedor
$vencedor_id = null;
if ($pontos_time1 > $pontos_time2) {
    $vencedor_id = $partida['time1_id'];
} elseif ($pontos_time2 > $pontos_time1) {
    $vencedor_id = $partida['time2_id'];
}

$pdo->beginTransaction();
try {
    // Atualizar partida
    $sql = "UPDATE grupo_jogo_partidas 
            SET pontos_time1 = ?, pontos_time2 = ?, vencedor_id = ?, status = 'Finalizada'
            WHERE id = ?";
    executeQuery($pdo, $sql, [$pontos_time1, $pontos_time2, $vencedor_id, $partida_id]);
    
    // Atualizar classificação
    $jogo_id = $partida['jogo_id'];
    
    // Buscar todas as partidas finalizadas do jogo
    $sql = "SELECT * FROM grupo_jogo_partidas WHERE jogo_id = ? AND status = 'Finalizada'";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $partidas_finalizadas = $stmt ? $stmt->fetchAll() : [];
    
    // Limpar classificação
    $sql = "UPDATE grupo_jogo_classificacao SET 
            vitorias = 0, derrotas = 0, pontos_pro = 0, pontos_contra = 0, 
            saldo_pontos = 0, average = 0.00, pontos_total = 0
            WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Recalcular classificação
    foreach ($partidas_finalizadas as $p) {
        $time1_id = $p['time1_id'];
        $time2_id = $p['time2_id'];
        $pts1 = (int)$p['pontos_time1'];
        $pts2 = (int)$p['pontos_time2'];
        
        // Atualizar time 1
        $sql = "UPDATE grupo_jogo_classificacao SET 
                pontos_pro = pontos_pro + ?,
                pontos_contra = pontos_contra + ?,
                vitorias = vitorias + ?,
                derrotas = derrotas + ?
                WHERE jogo_id = ? AND time_id = ?";
        executeQuery($pdo, $sql, [
            $pts1, $pts2, 
            ($pts1 > $pts2 ? 1 : 0), 
            ($pts1 < $pts2 ? 1 : 0),
            $jogo_id, $time1_id
        ]);
        
        // Atualizar time 2
        executeQuery($pdo, $sql, [
            $pts2, $pts1, 
            ($pts2 > $pts1 ? 1 : 0), 
            ($pts2 < $pts1 ? 1 : 0),
            $jogo_id, $time2_id
        ]);
    }
    
    // Calcular saldo, average e pontos totais
    $sql = "UPDATE grupo_jogo_classificacao SET 
            saldo_pontos = pontos_pro - pontos_contra,
            average = CASE WHEN pontos_contra > 0 THEN pontos_pro / pontos_contra ELSE pontos_pro END,
            pontos_total = (vitorias * 3) + (derrotas * 0)
            WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Resultado salvo e classificação atualizada!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar resultado: ' . $e->getMessage()]);
}
?>

