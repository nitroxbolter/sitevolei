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

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Buscar grupo Ouro A
$sql_grupo = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro A' LIMIT 1";
$stmt_grupo = executeQuery($pdo, $sql_grupo, [$torneio_id]);
$grupo = $stmt_grupo ? $stmt_grupo->fetch() : null;

if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo Ouro A não encontrado.']);
    exit();
}

$grupo_id = (int)$grupo['id'];

// Buscar todos os times do grupo Ouro A
$sql_times = "SELECT tt.id AS time_id, tt.nome AS time_nome, tt.cor AS time_cor
              FROM torneio_times tt
              JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
              WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
              ORDER BY tt.id ASC";
$stmt_times = executeQuery($pdo, $sql_times, [$grupo_id, $torneio_id]);
$times = $stmt_times ? $stmt_times->fetchAll(PDO::FETCH_ASSOC) : [];

if (empty($times)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum time encontrado no grupo Ouro A.']);
    exit();
}

$times_classificacao = [];

foreach ($times as $time) {
    $time_id = (int)$time['time_id'];
    
    // Buscar todos os jogos finalizados deste time neste grupo
    $sql_jogos = "SELECT 
                    pontos_time1, pontos_time2, time1_id, time2_id
                  FROM torneio_partidas
                  WHERE grupo_id = ? 
                    AND status = 'Finalizada'
                    AND fase = '2ª Fase'
                    AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')
                    AND (time1_id = ? OR time2_id = ?)";
    
    $stmt_jogos = executeQuery($pdo, $sql_jogos, [$grupo_id, $time_id, $time_id]);
    $jogos = $stmt_jogos ? $stmt_jogos->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Calcular estatísticas manualmente
    $vitorias = 0;
    $derrotas = 0;
    $empates = 0;
    $pontos_pro = 0;
    $pontos_contra = 0;
    
    foreach ($jogos as $jogo) {
        $pontos1 = (int)$jogo['pontos_time1'];
        $pontos2 = (int)$jogo['pontos_time2'];
        
        if ($jogo['time1_id'] == $time_id) {
            $pontos_pro += $pontos1;
            $pontos_contra += $pontos2;
            if ($pontos1 > $pontos2) {
                $vitorias++;
            } elseif ($pontos1 < $pontos2) {
                $derrotas++;
            } else {
                $empates++;
            }
        } else {
            $pontos_pro += $pontos2;
            $pontos_contra += $pontos1;
            if ($pontos2 > $pontos1) {
                $vitorias++;
            } elseif ($pontos2 < $pontos1) {
                $derrotas++;
            } else {
                $empates++;
            }
        }
    }
    
    $saldo = $pontos_pro - $pontos_contra;
    $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
    $pontos_total = ($vitorias * 3) + ($empates * 1);
    
    $times_classificacao[] = [
        'time_id' => $time_id,
        'nome' => $time['time_nome'],
        'cor' => $time['time_cor'],
        'grupo' => 'Ouro A',
        'vitorias' => $vitorias,
        'derrotas' => $derrotas,
        'empates' => $empates,
        'pontos_pro' => $pontos_pro,
        'pontos_contra' => $pontos_contra,
        'saldo_pontos' => $saldo,
        'average' => $average,
        'pontos_total' => $pontos_total
    ];
}

// Ordenar por pontos_total, depois vitorias, depois average, depois saldo
usort($times_classificacao, function($a, $b) {
    if ($a['pontos_total'] != $b['pontos_total']) {
        return $b['pontos_total'] - $a['pontos_total'];
    }
    if ($a['vitorias'] != $b['vitorias']) {
        return $b['vitorias'] - $a['vitorias'];
    }
    if ($a['average'] != $b['average']) {
        return $b['average'] <=> $a['average'];
    }
    return $b['saldo_pontos'] - $a['saldo_pontos'];
});

// Pegar apenas os 2 mais pontuados
$dois_mais_pontuados = array_slice($times_classificacao, 0, 2);

echo json_encode([
    'success' => true,
    'times' => $dois_mais_pontuados,
    'total_times' => count($times_classificacao)
]);
?>

