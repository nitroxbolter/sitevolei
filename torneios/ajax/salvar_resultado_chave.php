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

$chave_id = (int)($_POST['chave_id'] ?? 0);
$pontos_time1 = (int)($_POST['pontos_time1'] ?? 0);
$pontos_time2 = (int)($_POST['pontos_time2'] ?? 0);
$status = $_POST['status'] ?? 'Finalizada';

if ($chave_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chave inválida.']);
    exit();
}

if (!in_array($status, ['Agendada', 'Em Andamento', 'Finalizada'])) {
    echo json_encode(['success' => false, 'message' => 'Status inválido.']);
    exit();
}

// Buscar chave
$sql = "SELECT tc.* 
        FROM torneio_chaves_times tc
        WHERE tc.id = ?";
$stmt = executeQuery($pdo, $sql, [$chave_id]);
$chave = $stmt ? $stmt->fetch() : false;

if (!$chave) {
    error_log("Chave não encontrada. ID: $chave_id");
    echo json_encode(['success' => false, 'message' => 'Chave não encontrada. ID: ' . $chave_id]);
    exit();
}

// Verificar permissão
$sql_torneio = "SELECT t.*, g.administrador_id 
                FROM torneios t
                LEFT JOIN grupos g ON g.id = t.grupo_id
                WHERE t.id = ?";
$stmt_torneio = executeQuery($pdo, $sql_torneio, [$chave['torneio_id']]);
$torneio = $stmt_torneio ? $stmt_torneio->fetch() : false;

if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

if ($torneio['status'] === 'Finalizado') {
    echo json_encode(['success' => false, 'message' => 'Torneio já está finalizado.']);
    exit();
}

$pdo->beginTransaction();

try {
    // Determinar vencedor
    $vencedor_id = null;
    if ($status === 'Finalizada') {
        if ($pontos_time1 > $pontos_time2) {
            $vencedor_id = $chave['time1_id'];
        } elseif ($pontos_time2 > $pontos_time1) {
            $vencedor_id = $chave['time2_id'];
        }
        // Se empate, não definir vencedor (mas no vôlei não há empate)
    }
    
    // Atualizar chave
    $sql_update = "UPDATE torneio_chaves_times 
                   SET pontos_time1 = ?, pontos_time2 = ?, vencedor_id = ?, status = ?
                   WHERE id = ?";
    executeQuery($pdo, $sql_update, [$pontos_time1, $pontos_time2, $vencedor_id, $status, $chave_id]);
    
    // Se a chave foi finalizada, atualizar classificação
    if ($status === 'Finalizada') {
        // Buscar classificação atual dos times
        $sql_class1 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        $stmt_class1 = executeQuery($pdo, $sql_class1, [$chave['torneio_id'], $chave['time1_id']]);
        $class1 = $stmt_class1 ? $stmt_class1->fetch() : false;
        
        $sql_class2 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        $stmt_class2 = executeQuery($pdo, $sql_class2, [$chave['torneio_id'], $chave['time2_id']]);
        $class2 = $stmt_class2 ? $stmt_class2->fetch() : false;
        
        // Se não existir, criar
        if (!$class1 && $chave['time1_id']) {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$chave['torneio_id'], $chave['time1_id']]);
        }
        
        if (!$class2 && $chave['time2_id']) {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$chave['torneio_id'], $chave['time2_id']]);
        }
        
        // Recalcular todas as estatísticas do torneio (incluindo jogos eliminatórios)
        // Usar UNION para combinar dados de torneio_partidas e torneio_chaves_times
        $sql_recalc = "SELECT 
            tc.time_id,
            COUNT(CASE WHEN jogo.vencedor_id = tc.time_id THEN 1 END) as vitorias,
            COUNT(CASE WHEN jogo.vencedor_id IS NOT NULL AND jogo.vencedor_id != tc.time_id AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as derrotas,
            COUNT(CASE WHEN jogo.vencedor_id IS NULL AND jogo.status = 'Finalizada' AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as empates,
            SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END) + 
            SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END) as pontos_pro,
            SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END) + 
            SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END) as pontos_contra
        FROM torneio_classificacao tc
        LEFT JOIN (
            SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id
            FROM torneio_partidas
            WHERE status = 'Finalizada'
            UNION ALL
            SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id
            FROM torneio_chaves_times
            WHERE status = 'Finalizada'
        ) jogo ON (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) 
            AND jogo.torneio_id = tc.torneio_id
        WHERE tc.torneio_id = ?
        GROUP BY tc.time_id";
        
        $stmt_recalc = executeQuery($pdo, $sql_recalc, [$chave['torneio_id']]);
        $recalcs = $stmt_recalc ? $stmt_recalc->fetchAll() : [];
        
        foreach ($recalcs as $recalc) {
            $saldo = (int)$recalc['pontos_pro'] - (int)$recalc['pontos_contra'];
            $average = (int)$recalc['pontos_contra'] > 0 ? (float)$recalc['pontos_pro'] / (float)$recalc['pontos_contra'] : ((int)$recalc['pontos_pro'] > 0 ? 999.99 : 0.00);
            $pontos_total = ((int)$recalc['vitorias'] * 3) + ((int)$recalc['empates'] * 1);
            
            $sql_update_class = "UPDATE torneio_classificacao 
                                SET vitorias = ?, derrotas = ?, empates = ?, 
                                    pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                    average = ?, pontos_total = ?
                                WHERE torneio_id = ? AND time_id = ?";
            executeQuery($pdo, $sql_update_class, [
                (int)$recalc['vitorias'],
                (int)$recalc['derrotas'],
                (int)$recalc['empates'],
                (int)$recalc['pontos_pro'],
                (int)$recalc['pontos_contra'],
                $saldo,
                $average,
                $pontos_total,
                $chave['torneio_id'],
                $recalc['time_id']
            ]);
        }
        
        // Atualizar posições
        $sql_pos = "SELECT time_id FROM torneio_classificacao 
                   WHERE torneio_id = ? 
                   ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
        $stmt_pos = executeQuery($pdo, $sql_pos, [$chave['torneio_id']]);
        $posicoes = $stmt_pos ? $stmt_pos->fetchAll() : [];
        
        $posicao = 1;
        foreach ($posicoes as $pos) {
            $sql_update_pos = "UPDATE torneio_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ?";
            executeQuery($pdo, $sql_update_pos, [$posicao++, $chave['torneio_id'], $pos['time_id']]);
        }
    }
    
    // Se a chave foi finalizada e tem vencedor, atualizar próxima fase
    if ($status === 'Finalizada' && $vencedor_id) {
        // Se for semi-final, atualizar final ou 3º lugar
        if ($chave['fase'] === 'Semi') {
            // Buscar todas as semi-finais para determinar final e 3º lugar
            $sql_semis = "SELECT * FROM torneio_chaves_times 
                         WHERE torneio_id = ? AND fase = 'Semi' 
                         ORDER BY chave_numero ASC";
            $stmt_semis = executeQuery($pdo, $sql_semis, [$chave['torneio_id']]);
            $semis = $stmt_semis ? $stmt_semis->fetchAll() : [];
            
            $todas_semis_finalizadas = true;
            $vencedores_semis = [];
            $perdedores_semis = [];
            
            foreach ($semis as $semi) {
                if ($semi['status'] !== 'Finalizada' || !$semi['vencedor_id']) {
                    $todas_semis_finalizadas = false;
                    break;
                }
                $vencedores_semis[] = $semi['vencedor_id'];
                // Determinar perdedor
                if ($semi['time1_id'] == $semi['vencedor_id']) {
                    $perdedores_semis[] = $semi['time2_id'];
                } else {
                    $perdedores_semis[] = $semi['time1_id'];
                }
            }
            
            if ($todas_semis_finalizadas && count($vencedores_semis) >= 2) {
                // Atualizar final com os vencedores das semi-finais
                $sql_final = "UPDATE torneio_chaves_times 
                             SET time1_id = ?, time2_id = ?, status = 'Agendada'
                             WHERE torneio_id = ? AND fase = 'Final' AND chave_numero = 1";
                executeQuery($pdo, $sql_final, [$vencedores_semis[0], $vencedores_semis[1], $chave['torneio_id']]);
                
                // Atualizar 3º lugar com os perdedores das semi-finais
                if (count($perdedores_semis) >= 2) {
                    $sql_terceiro = "UPDATE torneio_chaves_times 
                                    SET time1_id = ?, time2_id = ?, status = 'Agendada'
                                    WHERE torneio_id = ? AND fase = '3º Lugar' AND chave_numero = 1";
                    executeQuery($pdo, $sql_terceiro, [$perdedores_semis[0], $perdedores_semis[1], $chave['torneio_id']]);
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Resultado salvo com sucesso!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao salvar resultado da chave: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar resultado: ' . $e->getMessage()
    ]);
}
?>

