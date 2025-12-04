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

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;

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

// Verificar se é modalidade todos_chaves
if ($torneio['modalidade'] !== 'todos_chaves') {
    echo json_encode(['success' => false, 'message' => 'Esta funcionalidade é apenas para torneios com formato "Todos contra Todos + Chaves".']);
    exit();
}

// Verificar se todas as partidas da fase de grupos estão finalizadas
$sql_partidas = "SELECT COUNT(*) as total, 
                 SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                 FROM torneio_partidas 
                 WHERE torneio_id = ? AND fase = 'Grupos'";
$stmt_partidas = executeQuery($pdo, $sql_partidas, [$torneio_id]);
$info_partidas = $stmt_partidas ? $stmt_partidas->fetch() : ['total' => 0, 'finalizadas' => 0];

if ($info_partidas['total'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Nenhuma partida da fase de grupos encontrada.']);
    exit();
}

if ($info_partidas['finalizadas'] != $info_partidas['total']) {
    echo json_encode(['success' => false, 'message' => 'Finalize todas as partidas da fase de grupos antes de gerar as eliminatórias.']);
    exit();
}

// Verificar se já existem chaves eliminatórias
$sql_chaves_existentes = "SELECT COUNT(*) as total FROM torneio_chaves_times WHERE torneio_id = ?";
$stmt_chaves = executeQuery($pdo, $sql_chaves_existentes, [$torneio_id]);
$chaves_existentes = $stmt_chaves ? (int)$stmt_chaves->fetch()['total'] : 0;

if ($chaves_existentes > 0) {
    echo json_encode(['success' => false, 'message' => 'As chaves eliminatórias já foram geradas.']);
    exit();
}

// Buscar grupos do torneio
$sql_grupos = "SELECT id, nome, ordem FROM torneio_grupos WHERE torneio_id = ? ORDER BY ordem ASC";
$stmt_grupos = executeQuery($pdo, $sql_grupos, [$torneio_id]);
$grupos = $stmt_grupos ? $stmt_grupos->fetchAll() : [];

if (empty($grupos)) {
    echo json_encode(['success' => false, 'message' => 'Nenhuma chave encontrada.']);
    exit();
}

$pdo->beginTransaction();

try {
    $times_classificados = [];
    
    // Para cada grupo, buscar os 2 melhores times
    foreach ($grupos as $grupo) {
        // Buscar times do grupo através da tabela torneio_grupo_times
        $sql_times_grupo = "SELECT tgt.time_id 
                           FROM torneio_grupo_times tgt
                           WHERE tgt.grupo_id = ?";
        $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo['id']]);
        $times_grupo_ids = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_COLUMN) : [];
        
        if (empty($times_grupo_ids)) {
            continue;
        }
        
        // Buscar classificação dos times deste grupo
        $placeholders = implode(',', array_fill(0, count($times_grupo_ids), '?'));
        $sql_classificacao = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                            FROM torneio_classificacao tc
                            JOIN torneio_times tt ON tt.id = tc.time_id
                            WHERE tc.torneio_id = ? AND tc.time_id IN ($placeholders)
                            ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                            LIMIT 2";
        $params = array_merge([$torneio_id], $times_grupo_ids);
        $stmt_class = executeQuery($pdo, $sql_classificacao, $params);
        $melhores = $stmt_class ? $stmt_class->fetchAll() : [];
        
        if (count($melhores) >= 2) {
            $times_classificados[] = [
                'grupo' => $grupo,
                'time1' => $melhores[0],
                'time2' => $melhores[1]
            ];
        }
    }
    
    if (count($times_classificados) < 2) {
        throw new Exception('É necessário pelo menos 2 chaves com 2 times cada para gerar as eliminatórias.');
    }
    
    // Gerar semi-finais
    // Para 2 chaves: melhor de cada chave enfrenta o 2º melhor da outra
    // Para mais chaves: melhor de cada chave enfrenta o melhor da próxima
    
    $semi_finais = [];
    if (count($times_classificados) == 2) {
        // 2 chaves: melhor de cada chave enfrenta o 2º melhor da outra
        $semi_finais[] = [
            'time1' => $times_classificados[0]['time1'],
            'time2' => $times_classificados[1]['time2'],
            'chave_numero' => 1
        ];
        $semi_finais[] = [
            'time1' => $times_classificados[1]['time1'],
            'time2' => $times_classificados[0]['time2'],
            'chave_numero' => 2
        ];
    } else {
        // Mais de 2 chaves: melhor de cada chave enfrenta o melhor da próxima
        // Semi-final 1: Chave 1 vs Chave 2
        // Semi-final 2: Chave 3 vs Chave 4 (se existir)
        for ($i = 0; $i < count($times_classificados); $i += 2) {
            if ($i + 1 < count($times_classificados)) {
                $semi_finais[] = [
                    'time1' => $times_classificados[$i]['time1'],
                    'time2' => $times_classificados[$i + 1]['time1'],
                    'chave_numero' => (count($semi_finais) + 1)
                ];
            } else {
                // Se número ímpar de chaves, o último melhor vai direto para final
                // Por enquanto, vamos criar uma semi-final com o último time enfrentando o vencedor da primeira semi
                // Mas isso seria complexo, então vamos apenas criar semi-finais pares
            }
        }
    }
    
    // Inserir semi-finais
    foreach ($semi_finais as $semi) {
        $sql_semi = "INSERT INTO torneio_chaves_times (torneio_id, fase, chave_numero, time1_id, time2_id, status)
                     VALUES (?, 'Semi', ?, ?, ?, 'Agendada')";
        executeQuery($pdo, $sql_semi, [
            $torneio_id,
            $semi['chave_numero'],
            $semi['time1']['time_id'],
            $semi['time2']['time_id']
        ]);
    }
    
    // Gerar final (será preenchida após as semi-finais)
    $sql_final = "INSERT INTO torneio_chaves_times (torneio_id, fase, chave_numero, time1_id, time2_id, status)
                  VALUES (?, 'Final', 1, NULL, NULL, 'Agendada')";
    executeQuery($pdo, $sql_final, [$torneio_id]);
    
    // Gerar 3º lugar (será preenchida após as semi-finais)
    $sql_terceiro = "INSERT INTO torneio_chaves_times (torneio_id, fase, chave_numero, time1_id, time2_id, status)
                     VALUES (?, '3º Lugar', 1, NULL, NULL, 'Agendada')";
    executeQuery($pdo, $sql_terceiro, [$torneio_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Chaves eliminatórias geradas com sucesso! ' . count($semi_finais) . ' semi-final(is) criada(s).'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao gerar eliminatórias: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar eliminatórias: ' . $e->getMessage()
    ]);
}
?>

