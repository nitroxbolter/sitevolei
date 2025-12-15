<?php
/**
 * Script para gerar a final do Ouro automaticamente
 * Busca os 2 vencedores das semi-finais e cria a final
 */

session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$torneio_id = (int)($_POST['torneio_id'] ?? 0);
$serie = trim($_POST['serie'] ?? 'Ouro'); // Aceitar série como parâmetro, padrão é Ouro

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

// Validar série
$series_validas = ['Ouro', 'Prata', 'Bronze'];
if (!in_array($serie, $series_validas)) {
    echo json_encode(['success' => false, 'message' => 'Série inválida. Use: Ouro, Prata ou Bronze.']);
    exit();
}

$debug_messages = [];
function addDebug($msg) {
    global $debug_messages;
    $debug_messages[] = date('H:i:s') . ' - ' . $msg;
    error_log("DEBUG: " . $msg);
}

try {
    addDebug("=== Iniciando geração de final do Ouro ===");
    
    // Verificar se já existe uma final do Ouro na tabela partidas_2fase_eliminatorias
    $sql_check_final = "SELECT id FROM partidas_2fase_eliminatorias 
                       WHERE torneio_id = ? 
                       AND tipo_eliminatoria = 'Final'
                       AND (serie = 'Ouro' OR serie = 'Ouro A' OR serie = 'Ouro B' OR serie IS NULL)";
    $stmt_check_final = executeQuery($pdo, $sql_check_final, [$torneio_id]);
    $final_existente = $stmt_check_final ? $stmt_check_final->fetch() : null;
    
    if ($final_existente) {
        throw new Exception("A final do Ouro já existe (ID: {$final_existente['id']}).");
    }
    
    // Primeiro, verificar todas as eliminatórias do torneio para debug
    $sql_debug_all = "SELECT id, tipo_eliminatoria, serie, status FROM partidas_2fase_eliminatorias WHERE torneio_id = ?";
    $stmt_debug_all = executeQuery($pdo, $sql_debug_all, [$torneio_id]);
    $todas_eliminatorias = $stmt_debug_all ? $stmt_debug_all->fetchAll(PDO::FETCH_ASSOC) : [];
    addDebug("Total de eliminatórias no torneio: " . count($todas_eliminatorias));
    foreach ($todas_eliminatorias as $elim) {
        addDebug("  - ID: {$elim['id']}, Tipo: {$elim['tipo_eliminatoria']}, Serie: " . ($elim['serie'] ?? 'NULL') . ", Status: {$elim['status']}");
    }
    
    // Buscar todas as semi-finais do Ouro da tabela partidas_2fase_eliminatorias
    // Primeiro tentar sem filtro de serie para ver o que existe
    $sql_semifinais = "SELECT id, time1_id, time2_id, pontos_time1, pontos_time2, status, rodada, serie
                      FROM partidas_2fase_eliminatorias 
                      WHERE torneio_id = ? 
                      AND tipo_eliminatoria = 'Semi-Final'
                      ORDER BY rodada ASC, id ASC";
    $stmt_semifinais = executeQuery($pdo, $sql_semifinais, [$torneio_id]);
    $semifinais = $stmt_semifinais ? $stmt_semifinais->fetchAll(PDO::FETCH_ASSOC) : [];
    
    addDebug("Total de semi-finais encontradas (sem filtro de serie): " . count($semifinais));
    
    // Filtrar apenas as do Ouro (pode ter serie NULL, vazia, 'Ouro', 'Ouro A', 'Ouro B')
    // Se não houver filtro de serie ou for vazio/NULL, considerar como Ouro
    $semifinais_ouro = array_filter($semifinais, function($semi) {
        $serie = trim($semi['serie'] ?? '');
        return empty($serie) || $serie === 'Ouro' || $serie === 'Ouro A' || $serie === 'Ouro B';
    });
    $semifinais_ouro = array_values($semifinais_ouro); // Reindexar array
    
    addDebug("Total de semi-finais do Ouro (após filtro): " . count($semifinais_ouro));
    
    if (count($semifinais_ouro) !== 2) {
        // Se não encontrou 2, tentar atualizar registros com serie NULL ou vazia para 'Ouro'
        if (count($semifinais_ouro) > 0) {
            $sql_update_null = "UPDATE partidas_2fase_eliminatorias 
                               SET serie = 'Ouro' 
                               WHERE torneio_id = ? 
                               AND tipo_eliminatoria = 'Semi-Final' 
                               AND (serie IS NULL OR serie = '')";
            $stmt_update_null = executeQuery($pdo, $sql_update_null, [$torneio_id]);
            $rows_updated = $stmt_update_null ? $stmt_update_null->rowCount() : 0;
            addDebug("Registros atualizados (serie NULL/vazia -> 'Ouro'): " . $rows_updated);
            
            // Buscar novamente
            $stmt_semifinais = executeQuery($pdo, $sql_semifinais, [$torneio_id]);
            $semifinais = $stmt_semifinais ? $stmt_semifinais->fetchAll(PDO::FETCH_ASSOC) : [];
            $semifinais_ouro = array_filter($semifinais, function($semi) {
                $serie = trim($semi['serie'] ?? '');
                return empty($serie) || $serie === 'Ouro' || $serie === 'Ouro A' || $serie === 'Ouro B';
            });
            $semifinais_ouro = array_values($semifinais_ouro);
            addDebug("Total de semi-finais do Ouro após atualização: " . count($semifinais_ouro));
        }
        
        if (count($semifinais_ouro) !== 2) {
            throw new Exception("É necessário ter exatamente 2 semi-finais do Ouro. Encontradas: " . count($semifinais_ouro) . ". Verifique se as semi-finais foram geradas corretamente.");
        }
    }
    
    // Usar as semi-finais filtradas
    $semifinais = $semifinais_ouro;
    
    $vencedores = [];
    $todas_finalizadas = true;
    
    foreach ($semifinais as $idx => $semi) {
        addDebug("Semi-final " . ($idx + 1) . " (ID: {$semi['id']}): Status={$semi['status']}, Time1={$semi['time1_id']} ({$semi['pontos_time1']} pts) vs Time2={$semi['time2_id']} ({$semi['pontos_time2']} pts)");
        
        if ($semi['status'] !== 'Finalizada') {
            $todas_finalizadas = false;
            addDebug("⚠ Semi-final ID {$semi['id']} ainda não está finalizada");
            break;
        }
        
        // Determinar vencedor
        if ($semi['pontos_time1'] > $semi['pontos_time2']) {
            $vencedores[] = (int)$semi['time1_id'];
            addDebug("✓ Vencedor: Time1 (ID={$semi['time1_id']})");
        } elseif ($semi['pontos_time2'] > $semi['pontos_time1']) {
            $vencedores[] = (int)$semi['time2_id'];
            addDebug("✓ Vencedor: Time2 (ID={$semi['time2_id']})");
        } else {
            throw new Exception("A semi-final ID {$semi['id']} terminou em empate. Não é possível criar a final.");
        }
    }
    
    if (!$todas_finalizadas) {
        throw new Exception("Todas as semi-finais precisam estar finalizadas para gerar a final.");
    }
    
    if (count($vencedores) !== 2) {
        throw new Exception("É necessário ter 2 vencedores. Encontrados: " . count($vencedores));
    }
    
    // Buscar nomes dos times vencedores
    $sql_time1 = "SELECT nome FROM torneio_times WHERE id = ? AND torneio_id = ?";
    $stmt_time1 = executeQuery($pdo, $sql_time1, [$vencedores[0], $torneio_id]);
    $time1_nome = $stmt_time1 ? $stmt_time1->fetch()['nome'] : 'Time ID ' . $vencedores[0];
    
    $sql_time2 = "SELECT nome FROM torneio_times WHERE id = ? AND torneio_id = ?";
    $stmt_time2 = executeQuery($pdo, $sql_time2, [$vencedores[1], $torneio_id]);
    $time2_nome = $stmt_time2 ? $stmt_time2->fetch()['nome'] : 'Time ID ' . $vencedores[1];
    
    addDebug("=== TIMES PARA A FINAL DA SÉRIE: $serie ===");
    addDebug("Time 1: $time1_nome (ID: {$vencedores[0]})");
    addDebug("Time 2: $time2_nome (ID: {$vencedores[1]})");
    
    $pdo->beginTransaction();
    
    // Criar a final na tabela partidas_2fase_eliminatorias
    $sql_insert_final = "INSERT INTO partidas_2fase_eliminatorias 
                        (torneio_id, time1_id, time2_id, tipo_eliminatoria, serie, rodada, quadra, status, pontos_time1, pontos_time2) 
                        VALUES (?, ?, ?, 'Final', ?, 1, 1, 'Agendada', 0, 0)";
    $result_final = executeQuery($pdo, $sql_insert_final, [
        $torneio_id,
        $vencedores[0],
        $vencedores[1],
        $serie
    ]);
    
    if ($result_final === false) {
        $error_info = $pdo->errorInfo();
        throw new Exception("Erro ao criar final: " . ($error_info[2] ?? 'Erro desconhecido'));
    }
    
    $final_id = $pdo->lastInsertId();
    addDebug("✓ Final criada com sucesso! ID: $final_id");
    
    $pdo->commit();
    
    $debug_output = implode("\n", $debug_messages);
    
    echo json_encode([
        'success' => true,
        'message' => "Final da série $serie criada com sucesso! $time1_nome vs $time2_nome",
        'debug' => $debug_output
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $debug_output = implode("\n", $debug_messages);
    
    error_log("Erro ao gerar final do Ouro: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar final: ' . $e->getMessage(),
        'debug' => $debug_output
    ]);
}
?>

