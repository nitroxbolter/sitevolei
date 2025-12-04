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

