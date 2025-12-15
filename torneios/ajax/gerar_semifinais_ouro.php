<?php
// Habilitar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Função para capturar erros e exceções
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("ERRO PHP [$errno]: $errstr em $errfile:$errline");
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $errstr,
            'error' => [
                'code' => $errno,
                'file' => basename($errfile),
                'line' => $errline
            ]
        ]);
        exit();
    }
}

function handleException($exception) {
    error_log("EXCEÇÃO: " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $exception->getMessage(),
            'error' => [
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        ]);
        exit();
    }
}

set_error_handler('handleError');
set_exception_handler('handleException');

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

if ($torneio['modalidade'] !== 'torneio_pro') {
    echo json_encode(['success' => false, 'message' => 'Esta função é apenas para torneios do tipo Torneio Pro.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Array para armazenar mensagens de debug
$debug_messages = [];

// Função auxiliar para adicionar mensagens de debug
function addDebug($message) {
    global $debug_messages;
    $debug_messages[] = date('H:i:s') . ' - ' . $message;
}

// Verificar se a coluna grupo_id existe em torneio_partidas
$columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
$tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;

// Verificar se a coluna tipo_fase existe em torneio_partidas
$columnsQuery_tipo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
$tem_tipo_fase = $columnsQuery_tipo && $columnsQuery_tipo->rowCount() > 0;

// Buscar grupos da série A e B
$sql_grupo_a = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
$sql_grupo_b = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
$stmt_grupo_a = executeQuery($pdo, $sql_grupo_a, [$torneio_id, "2ª Fase - $serie A"]);
$stmt_grupo_b = executeQuery($pdo, $sql_grupo_b, [$torneio_id, "2ª Fase - $serie B"]);
$grupo_a = $stmt_grupo_a ? $stmt_grupo_a->fetch() : null;
$grupo_b = $stmt_grupo_b ? $stmt_grupo_b->fetch() : null;

if (!$grupo_a || !$grupo_b) {
    echo json_encode(['success' => false, 'message' => "Os grupos $serie A e $serie B não foram encontrados."]);
    exit();
}

$grupo_a_id = (int)$grupo_a['id'];
$grupo_b_id = (int)$grupo_b['id'];

// Verificar se todas as partidas todos contra todos de Ouro A e Ouro B estão finalizadas
// Usar a nova tabela partidas_2fase_torneio

// Verificar jogos da 2ª fase na nova tabela partidas_2fase_torneio
    $sql_check_partidas_a = "SELECT COUNT(*) as total, 
                             SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM partidas_2fase_torneio 
                         WHERE torneio_id = ? AND grupo_id = ?";
    $sql_check_partidas_b = "SELECT COUNT(*) as total, 
                             SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                         FROM partidas_2fase_torneio 
                         WHERE torneio_id = ? AND grupo_id = ?";

$stmt_check_a = executeQuery($pdo, $sql_check_partidas_a, [$torneio_id, $grupo_a_id]);
$info_partidas_a = $stmt_check_a ? $stmt_check_a->fetch() : ['total' => 0, 'finalizadas' => 0];

$stmt_check_b = executeQuery($pdo, $sql_check_partidas_b, [$torneio_id, $grupo_b_id]);
$info_partidas_b = $stmt_check_b ? $stmt_check_b->fetch() : ['total' => 0, 'finalizadas' => 0];

error_log("DEBUG: $serie A - Total jogos 'Todos Contra Todos': {$info_partidas_a['total']}, Finalizados: {$info_partidas_a['finalizadas']}");
error_log("DEBUG: $serie B - Total jogos 'Todos Contra Todos': {$info_partidas_b['total']}, Finalizados: {$info_partidas_b['finalizadas']}");

// Verificar se os jogos da 2ª fase foram criados
error_log("DEBUG: $serie A - Total jogos 2ª fase (exceto Semi-Final/Final): {$info_partidas_a['total']}, Finalizados: {$info_partidas_a['finalizadas']}");
error_log("DEBUG: $serie B - Total jogos 2ª fase (exceto Semi-Final/Final): {$info_partidas_b['total']}, Finalizados: {$info_partidas_b['finalizadas']}");

if ($info_partidas_a['total'] == 0 || $info_partidas_b['total'] == 0) {
    echo json_encode([
        'success' => false, 
        'message' => "Os jogos da 2ª fase ainda não foram gerados para $serie A e $serie B.",
        'debug' => [
            'serie_a_total' => $info_partidas_a['total'],
            'serie_b_total' => $info_partidas_b['total'],
            'grupo_a_id' => $grupo_a_id,
            'grupo_b_id' => $grupo_b_id,
            'tem_tipo_fase' => $tem_tipo_fase
        ]
    ]);
    exit();
}

// Verificar se todas as partidas estão finalizadas
if (($info_partidas_a['total'] > 0 && $info_partidas_a['finalizadas'] < $info_partidas_a['total']) ||
    ($info_partidas_b['total'] > 0 && $info_partidas_b['finalizadas'] < $info_partidas_b['total'])) {
    echo json_encode([
        'success' => false, 
        'message' => "Nem todas as partidas dos grupos $serie A e $serie B estão finalizadas. Por favor, finalize todos os jogos antes de gerar as semi-finais.",
        'debug' => [
            'serie_a_total' => $info_partidas_a['total'],
            'serie_a_finalizadas' => $info_partidas_a['finalizadas'],
            'serie_b_total' => $info_partidas_b['total'],
            'serie_b_finalizadas' => $info_partidas_b['finalizadas']
        ]
    ]);
    exit();
}

// O campo serie é um ENUM que aceita: 'Ouro', 'Ouro A', 'Ouro B', 'Prata', 'Prata A', 'Prata B', 'Bronze', 'Bronze A', 'Bronze B'
// Para semi-finais e finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
// Para outras fases, pode usar 'Ouro A', 'Ouro B', etc.

// Verificar se há semi-finais com série incorreta (ex: 'Ouro A' em vez de 'Ouro') que pertencem a esta série
$serie_a_old = $serie . ' A';
$serie_b_old = $serie . ' B';
$sql_check_invalid_serie = "SELECT p.id, p.time1_id, p.time2_id, p.serie
                             FROM partidas_2fase_eliminatorias p
                             WHERE p.torneio_id = ? 
                             AND p.tipo_eliminatoria = 'Semi-Final'
                             AND p.serie IN (?, ?)
                             AND (
                                 (p.time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                  AND p.time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                                 OR
                                 (p.time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                  AND p.time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                             )";
$stmt_check_invalid_serie = executeQuery($pdo, $sql_check_invalid_serie, [
    $torneio_id,
    $serie_a_old, $serie_b_old,
    $grupo_a_id, $grupo_b_id,
    $grupo_b_id, $grupo_a_id
]);
$semi_invalid_serie = $stmt_check_invalid_serie ? $stmt_check_invalid_serie->fetchAll(PDO::FETCH_ASSOC) : [];

if (count($semi_invalid_serie) > 0) {
    // Atualizar essas semi-finais para ter a série correta (apenas 'Ouro', 'Prata' ou 'Bronze')
    $sql_update_invalid_serie = "UPDATE partidas_2fase_eliminatorias 
                                 SET serie = ?
                                 WHERE torneio_id = ? 
                                 AND tipo_eliminatoria = 'Semi-Final'
                                 AND serie IN (?, ?)
                                 AND (
                                     (time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                      AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                                     OR
                                     (time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                      AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                                 )";
    $stmt_update_invalid_serie = executeQuery($pdo, $sql_update_invalid_serie, [
        $serie,  // Atualizar para 'Ouro', 'Prata' ou 'Bronze' (sem A ou B)
        $torneio_id,
        $serie_a_old, $serie_b_old,
        $grupo_a_id, $grupo_b_id,
        $grupo_b_id, $grupo_a_id
    ]);
    $atualizados_invalid = $stmt_update_invalid_serie ? $stmt_update_invalid_serie->rowCount() : 0;
    if ($atualizados_invalid > 0) {
        error_log("Corrigidas $atualizados_invalid semi-finais com série incorreta ($serie_a_old/$serie_b_old) para série $serie no torneio $torneio_id");
        addDebug("Corrigidas $atualizados_invalid semi-finais com série incorreta para série $serie");
    }
}

// Verificar se já existem semi-finais da série (nova tabela partidas_2fase_eliminatorias)
// Para semi-finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
$sql_check_semifinais = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                         WHERE torneio_id = ? 
                         AND tipo_eliminatoria = 'Semi-Final'
                         AND serie = ?";
$stmt_check_semifinais = executeQuery($pdo, $sql_check_semifinais, [
    $torneio_id,
    $serie  // Apenas 'Ouro', 'Prata' ou 'Bronze'
]);
$tem_semifinais = $stmt_check_semifinais ? (int)$stmt_check_semifinais->fetch()['total'] > 0 : false;

if ($tem_semifinais) {
    echo json_encode(['success' => false, 'message' => "As semi-finais da série $serie já foram geradas."]);
    exit();
}

// Verificar se a coluna grupo_id existe em torneio_classificacao
$columnsQuery_class = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
$tem_grupo_id_classificacao = $columnsQuery_class && $columnsQuery_class->rowCount() > 0;

if (!$tem_grupo_id_classificacao) {
    echo json_encode(['success' => false, 'message' => 'A coluna grupo_id não existe em torneio_classificacao.']);
    exit();
}

$pdo->beginTransaction();

try {
    // Inicializar variáveis de classificação para evitar erros
    $classificacao_a = [];
    $classificacao_b = [];
    $todos_times_a = [];
    $todos_times_b = [];
    
    // Buscar classificação completa do grupo A da série
    // Buscar classificação do grupo A - PRIMEIRO tentar da nova tabela torneio_classificacao_2fase
    $sql_check_table_2fase = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
    $stmt_check_table_2fase = executeQuery($pdo, $sql_check_table_2fase, []);
    $table_exists_2fase = $stmt_check_table_2fase ? $stmt_check_table_2fase->fetch() : null;
    
    // Garantir que $table_exists_2fase seja boolean
    $table_exists_2fase = ($table_exists_2fase !== null && $table_exists_2fase !== false);
    
    $todos_times_a = [];
    
    if ($table_exists_2fase) {
        // Usar nova tabela torneio_classificacao_2fase
        $sql_class_a = "SELECT 
                                tc.time_id, 
                                tc.serie,
                                tc.vitorias, 
                                tc.derrotas, 
                                tc.empates,
                                tc.pontos_pro, 
                                tc.pontos_contra, 
                                tc.saldo_pontos, 
                                tc.average, 
                                tc.pontos_total,
                                tc.posicao,
                                tt.nome AS time_nome, 
                                tt.nome AS nome,
                                tt.cor AS time_cor,
                                tt.cor AS cor
                             FROM torneio_classificacao_2fase tc
                             JOIN torneio_times tt ON tt.id = tc.time_id
                             WHERE tc.torneio_id = ? AND tc.serie = ?
                             ORDER BY 
                                tc.pontos_total DESC, 
                                tc.vitorias DESC,
                                tc.average DESC, 
                                tc.saldo_pontos DESC";
        $stmt_class_a = executeQuery($pdo, $sql_class_a, [$torneio_id, "$serie A"]);
        $todos_times_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($todos_times_a) > 0) {
            addDebug("✓ Classificação $serie A encontrada na tabela torneio_classificacao_2fase: " . count($todos_times_a) . " times");
        }
    }
    
    // Se não encontrou na nova tabela, usar nova tabela partidas_2fase_classificacao
    if (count($todos_times_a) == 0) {
        $sql_class_a = "SELECT 
                                tc.time_id, 
                                tc.grupo_id,
                                tc.vitorias, 
                                tc.derrotas, 
                                tc.empates,
                                tc.pontos_pro, 
                                tc.pontos_contra, 
                                tc.saldo_pontos, 
                                tc.average, 
                                tc.pontos_total,
                                tt.nome AS time_nome, 
                                tt.nome AS nome,
                                tt.cor AS time_cor,
                                tt.cor AS cor
                             FROM partidas_2fase_classificacao tc
                             JOIN torneio_times tt ON tt.id = tc.time_id
                             WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                             ORDER BY 
                                tc.pontos_total DESC, 
                                tc.vitorias DESC,
                                tc.average DESC, 
                                tc.saldo_pontos DESC";
        $stmt_class_a = executeQuery($pdo, $sql_class_a, [$torneio_id, $grupo_a_id]);
        $todos_times_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($todos_times_a) > 0) {
            addDebug("⚠ Classificação $serie A encontrada na tabela partidas_2fase_classificacao: " . count($todos_times_a) . " times");
        }
    }
    
    // Debug: Verificar se há times no grupo e jogos finalizados
    $sql_check_times = "SELECT COUNT(*) as total FROM torneio_grupo_times WHERE grupo_id = ?";
    $stmt_check_times = executeQuery($pdo, $sql_check_times, [$grupo_a_id]);
    $total_times = $stmt_check_times ? (int)$stmt_check_times->fetch()['total'] : 0;
    
    // DEBUG COMPLETO: Verificar TODOS os jogos do grupo A primeiro (sem filtro de fase)
    $sql_debug_jogos_grupo_a = "SELECT id, time1_id, time2_id, grupo_id, status, fase, tipo_fase, pontos_time1, pontos_time2
                                     FROM torneio_partidas 
                                     WHERE torneio_id = ? AND grupo_id = ?
                                     ORDER BY id";
    $stmt_debug_jogos_grupo = executeQuery($pdo, $sql_debug_jogos_grupo_a, [$torneio_id, $grupo_a_id]);
    $jogos_grupo_a = $stmt_debug_jogos_grupo ? $stmt_debug_jogos_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
    
    addDebug("=== DEBUG COMPLETO: Análise de Jogos e Classificação ===");
    addDebug("Total de jogos no grupo $serie A (ID=$grupo_a_id) - SEM filtro de fase: " . count($jogos_grupo_a));
    foreach ($jogos_grupo_a as $jogo) {
        addDebug("  Jogo ID={$jogo['id']}: fase='{$jogo['fase']}', tipo_fase='{$jogo['tipo_fase']}', status='{$jogo['status']}', pontos={$jogo['pontos_time1']}x{$jogo['pontos_time2']}");
    }
    
    // Agora verificar TODOS os jogos da 2ª fase para encontrar onde estão
    $sql_debug_todos_jogos_2fase_completo = "SELECT id, time1_id, time2_id, grupo_id, status, fase, tipo_fase, pontos_time1, pontos_time2
                                             FROM torneio_partidas 
                                             WHERE torneio_id = ? AND fase = '2ª Fase' 
                                             ORDER BY grupo_id, id";
    $stmt_debug_todos_completo = executeQuery($pdo, $sql_debug_todos_jogos_2fase_completo, [$torneio_id]);
    $todos_jogos_2fase_completo = $stmt_debug_todos_completo ? $stmt_debug_todos_completo->fetchAll(PDO::FETCH_ASSOC) : [];
    
    addDebug("Total de jogos da 2ª fase no torneio (com filtro fase='2ª Fase'): " . count($todos_jogos_2fase_completo));
    $jogos_por_grupo = [];
    foreach ($todos_jogos_2fase_completo as $jogo) {
        $grupo_id_jogo = $jogo['grupo_id'] ?? 'NULL';
        if (!isset($jogos_por_grupo[$grupo_id_jogo])) {
            $jogos_por_grupo[$grupo_id_jogo] = ['total' => 0, 'finalizados' => 0];
        }
        $jogos_por_grupo[$grupo_id_jogo]['total']++;
        if ($jogo['status'] === 'Finalizada') {
            $jogos_por_grupo[$grupo_id_jogo]['finalizados']++;
            addDebug("  Jogo ID={$jogo['id']}: grupo_id=$grupo_id_jogo, status={$jogo['status']}, tipo_fase={$jogo['tipo_fase']}, time1_id={$jogo['time1_id']}, time2_id={$jogo['time2_id']}, pontos={$jogo['pontos_time1']}x{$jogo['pontos_time2']}");
        }
    }
    addDebug("Jogos por grupo_id: " . json_encode($jogos_por_grupo));
    
    // Verificar qual grupo_id tem os times do Ouro A
    $sql_times_ouro_a = "SELECT tgt.grupo_id, tg.nome AS grupo_nome, COUNT(*) as total 
                         FROM torneio_grupo_times tgt
                         JOIN torneio_times tt ON tt.id = tgt.time_id
                         JOIN torneio_grupos tg ON tg.id = tgt.grupo_id
                         WHERE tt.torneio_id = ? AND tg.nome LIKE '2ª Fase%'
                         GROUP BY tgt.grupo_id, tg.nome";
    $stmt_times_ouro_a = executeQuery($pdo, $sql_times_ouro_a, [$torneio_id]);
    $times_por_grupo = $stmt_times_ouro_a ? $stmt_times_ouro_a->fetchAll(PDO::FETCH_ASSOC) : [];
    addDebug("Times por grupo_id da 2ª fase:");
    foreach ($times_por_grupo as $info) {
        addDebug("  grupo_id={$info['grupo_id']} ({$info['grupo_nome']}): {$info['total']} times");
    }
    
    // Verificar se há classificação na tabela APENAS para grupos da 2ª fase
    $sql_class_qualquer_grupo = "SELECT tc.*, tt.nome AS time_nome, tg.nome AS grupo_nome, tg.id AS grupo_id_real
                                 FROM torneio_classificacao tc
                                 JOIN torneio_times tt ON tt.id = tc.time_id
                                 JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                                 WHERE tc.torneio_id = ? 
                                 AND tc.time_id IN (
                                     SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?
                                 )
                                 AND tg.nome LIKE '2ª Fase%'
                                 ORDER BY tc.pontos_total DESC";
    $stmt_class_qualquer = executeQuery($pdo, $sql_class_qualquer_grupo, [$torneio_id, $grupo_a_id]);
    $classificacao_qualquer = $stmt_class_qualquer ? $stmt_class_qualquer->fetchAll(PDO::FETCH_ASSOC) : [];
    addDebug("Classificação encontrada para times do Ouro A em grupos da 2ª fase: " . count($classificacao_qualquer));
    foreach ($classificacao_qualquer as $class) {
        addDebug("  Time: {$class['time_nome']}, Grupo: {$class['grupo_nome']} (ID={$class['grupo_id_real']}), Pontos: {$class['pontos_total']}, Avg: {$class['average']}, V: {$class['vitorias']}, D: {$class['derrotas']}");
    }
    
    // Se não encontrou na 2ª fase, verificar se há na 1ª fase (para debug)
    $sql_class_1fase = "SELECT tc.*, tt.nome AS time_nome, tg.nome AS grupo_nome, tg.id AS grupo_id_real
                        FROM torneio_classificacao tc
                        JOIN torneio_times tt ON tt.id = tc.time_id
                        JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                        WHERE tc.torneio_id = ? 
                        AND tc.time_id IN (
                            SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?
                        )
                        AND tg.nome NOT LIKE '2ª Fase%'
                        ORDER BY tc.pontos_total DESC";
    $stmt_class_1fase = executeQuery($pdo, $sql_class_1fase, [$torneio_id, $grupo_a_id]);
    $classificacao_1fase = $stmt_class_1fase ? $stmt_class_1fase->fetchAll(PDO::FETCH_ASSOC) : [];
    if (count($classificacao_1fase) > 0) {
        addDebug("⚠ ATENÇÃO: Encontrada classificação da 1ª fase (será ignorada): " . count($classificacao_1fase));
        foreach ($classificacao_1fase as $class) {
            addDebug("  Time: {$class['time_nome']}, Grupo: {$class['grupo_nome']} (ID={$class['grupo_id_real']}), Pontos: {$class['pontos_total']}");
        }
    }
    
    // Verificar jogos finalizados (nova tabela partidas_2fase_torneio)
    $sql_check_jogos = "SELECT COUNT(*) as total FROM partidas_2fase_torneio 
                        WHERE torneio_id = ? AND grupo_id = ? 
                        AND status = 'Finalizada'";
    $stmt_check_jogos = executeQuery($pdo, $sql_check_jogos, [$torneio_id, $grupo_a_id]);
    $total_jogos_finalizados = $stmt_check_jogos ? (int)$stmt_check_jogos->fetch()['total'] : 0;
    
    addDebug("Grupo $serie A (ID=$grupo_a_id) - Times no grupo: $total_times, Jogos finalizados: $total_jogos_finalizados");
    
    // Se encontrou classificação em outro grupo_id da 2ª fase, usar ela
    if (count($classificacao_qualquer) > 0 && count($todos_times_a) == 0) {
        addDebug("⚠ Classificação encontrada em outro grupo_id da 2ª fase! Usando esses dados...");
        // Filtrar apenas do grupo A (pode ter encontrado de outros grupos da 2ª fase)
        $classificacao_a_filtrada = [];
        foreach ($classificacao_qualquer as $class) {
            if (strpos($class['grupo_nome'], "$serie A") !== false) {
                $classificacao_a_filtrada[] = $class;
            }
        }
        
        if (count($classificacao_a_filtrada) > 0) {
            $grupo_id_encontrado = $classificacao_a_filtrada[0]['grupo_id_real'];
            addDebug("Usando grupo_id encontrado: $grupo_id_encontrado ($serie A)");
            
            // Converter para o formato esperado
            $todos_times_a = [];
            foreach ($classificacao_a_filtrada as $class) {
                $todos_times_a[] = [
                    'time_id' => $class['time_id'],
                    'time_nome' => $class['time_nome'],
                    'nome' => $class['time_nome'], // Adicionar campo 'nome' também para compatibilidade
                    'time_cor' => $class['time_cor'] ?? '#000000',
                    'cor' => $class['time_cor'] ?? '#000000', // Adicionar campo 'cor' também
                    'vitorias' => $class['vitorias'],
                    'derrotas' => $class['derrotas'],
                    'empates' => $class['empates'],
                    'pontos_pro' => $class['pontos_pro'],
                    'pontos_contra' => $class['pontos_contra'],
                    'saldo_pontos' => $class['saldo_pontos'],
                    'average' => $class['average'],
                    'pontos_total' => $class['pontos_total']
                ];
            }
        } else {
            addDebug("⚠ Classificação encontrada mas não é do $serie A, será calculada dos jogos");
        }
    }
    
    // Debug simplificado - exibir tabela do grupo A diretamente da tabela torneio_classificacao
    addDebug("=== TABELA CLASSIFICAÇÃO - $serie A (grupo_id=$grupo_a_id) ===");
    addDebug("Pos | Time | J | V | D | Pts | PF | PS | Saldo | Avg");
    addDebug("--- | ---- | - | - | - | --- | -- | -- | ----- | ---");
    if (count($todos_times_a) > 0) {
        $posicao = 1;
        foreach ($todos_times_a as $time) {
            $jogos = ($time['vitorias'] ?? 0) + ($time['derrotas'] ?? 0) + ($time['empates'] ?? 0);
            $vitorias = (int)($time['vitorias'] ?? 0);
            $derrotas = (int)($time['derrotas'] ?? 0);
            $empates = (int)($time['empates'] ?? 0);
            $pontos = (int)($time['pontos_total'] ?? 0);
            $pf = (int)($time['pontos_pro'] ?? 0);
            $ps = (int)($time['pontos_contra'] ?? 0);
            $saldo = (int)($time['saldo_pontos'] ?? 0);
            $avg = number_format((float)($time['average'] ?? 0), 2);
            $nome = $time['time_nome'] ?? 'N/A';
            $time_id = $time['time_id'] ?? 'N/A';
            
            // Debug adicional: mostrar valores brutos para verificar
            addDebug("$posicao | $nome | $jogos | $vitorias | $derrotas | $pontos | $pf | $ps | $saldo | $avg [time_id=$time_id]");
            $posicao++;
        }
    } else {
        addDebug("⚠ Nenhum registro encontrado na tabela torneio_classificacao para $serie A (grupo_id=$grupo_a_id)");
        if ($total_jogos_finalizados == 0 && $total_times > 0) {
            addDebug("⚠ ATENÇÃO: Os jogos da 2ª fase ainda não foram criados ou não foram finalizados.");
            addDebug("   Passos necessários:");
            addDebug("   1. Clique em 'Gerar Jogos da 2ª Fase' para criar os confrontos");
            addDebug("   2. Finalize todos os jogos dos grupos $serie A e $serie B");
            addDebug("   3. Depois clique em 'Gerar Semi-Final' novamente");
        } else {
            addDebug("Verifique se os jogos da 2ª fase foram finalizados e a classificação foi atualizada.");
        }
    }
    
    // Selecionar os 2 primeiros colocados do grupo A
    $classificacao_a = [];
    if (count($todos_times_a) >= 2) {
        $classificacao_a = [
            $todos_times_a[0], // 1º colocado
            $todos_times_a[1]   // 2º colocado
        ];
    } elseif (count($todos_times_a) === 1) {
        $classificacao_a = [$todos_times_a[0]];
    }
    
    // Se não encontrou na tabela de classificação, calcular diretamente dos jogos
    // Mas primeiro, tentar atualizar a classificação forçando o recálculo
    if (count($classificacao_a) < 2) {
        addDebug("⚠ ATENÇÃO: Classificação não encontrada ou incompleta na tabela. Tentando atualizar...");
        
        // Forçar atualização da classificação recalculando dos jogos
        // Buscar todos os times do grupo (com nome e cor)
        $sql_times_grupo = "SELECT tt.id as time_id, tt.nome as time_nome, tt.cor as time_cor
                           FROM torneio_times tt
                           JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                           WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
        $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo_a_id, $torneio_id]);
        $times_grupo = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
        
        addDebug("Times encontrados no grupo para cálculo: " . count($times_grupo));
        
        // Para cada time, recalcular e inserir/atualizar classificação
        foreach ($times_grupo as $time_grupo) {
            $time_id = (int)$time_grupo['time_id'];
            
            // USAR MESMA LÓGICA DA TELA: buscar jogos apenas pelo grupo_id, sem filtro de fase (nova tabela partidas_2fase_torneio)
            $sql_jogos_time = "SELECT pontos_time1, pontos_time2, time1_id, time2_id
                               FROM partidas_2fase_torneio
                               WHERE grupo_id = ? 
                                   AND status = 'Finalizada'
                                   AND (time1_id = ? OR time2_id = ?)";
            
            $stmt_jogos_time = executeQuery($pdo, $sql_jogos_time, [$grupo_a_id, $time_id, $time_id]);
            $jogos_time = $stmt_jogos_time ? $stmt_jogos_time->fetchAll(PDO::FETCH_ASSOC) : [];
            
            // Calcular estatísticas manualmente (MESMA LÓGICA DA TELA)
            $vitorias = 0;
            $derrotas = 0;
            $empates = 0;
            $pontos_pro = 0;
            $pontos_contra = 0;
            
            foreach ($jogos_time as $jogo) {
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
            
            if (count($jogos_time) > 0) {
                addDebug("  Time {$time_grupo['time_nome']} (ID=$time_id): V=$vitorias, D=$derrotas, E=$empates, PF=$pontos_pro, PS=$pontos_contra, Pts=$pontos_total, Avg=" . number_format($average, 2));
                
                // Verificar se existe classificação na nova tabela partidas_2fase_classificacao
                $sql_check = "SELECT id FROM partidas_2fase_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $time_id, $grupo_a_id]);
                $existe = $stmt_check ? $stmt_check->fetch() : null;
                
                if ($existe) {
                    // Atualizar
                    $sql_update = "UPDATE partidas_2fase_classificacao 
                                  SET vitorias = ?, derrotas = ?, empates = ?, 
                                      pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                      average = ?, pontos_total = ?
                                  WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    executeQuery($pdo, $sql_update, [
                        $vitorias, $derrotas, $empates, $pontos_pro, $pontos_contra, 
                        $saldo, $average, $pontos_total, $torneio_id, $time_id, $grupo_a_id
                    ]);
                } else {
                    // Inserir
                    $sql_insert = "INSERT INTO partidas_2fase_classificacao 
                                  (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                   pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    executeQuery($pdo, $sql_insert, [
                        $torneio_id, $time_id, $grupo_a_id, $vitorias, $derrotas, $empates,
                        $pontos_pro, $pontos_contra, $saldo, $average, $pontos_total
                    ]);
                }
            } else {
                addDebug("  Time {$time_grupo['time_nome']} (ID=$time_id): Nenhum jogo encontrado");
            }
        }
        
        // Buscar novamente da tabela
        $stmt_class_a = executeQuery($pdo, $sql_class_a, [$torneio_id, $grupo_a_id]);
        $todos_times_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($todos_times_a) >= 2) {
            $classificacao_a = [
                $todos_times_a[0],
                $todos_times_a[1]
            ];
            addDebug("✓ Classificação atualizada com sucesso! Encontrados " . count($todos_times_a) . " times.");
        } else {
            // Se ainda não encontrou, calcular diretamente dos jogos e usar os dados calculados
            addDebug("⚠ Ainda não encontrou classificação após atualização. Calculando diretamente dos jogos...");
            
            // Recalcular e criar array de classificação diretamente
            $classificacao_calculada_direta = [];
            foreach ($times_grupo as $time_grupo) {
                $time_id = (int)$time_grupo['time_id'];
                
                // Buscar jogos (mesma lógica)
                $sql_jogos_time = "SELECT pontos_time1, pontos_time2, time1_id, time2_id
                                   FROM torneio_partidas
                                   WHERE grupo_id = ? 
                                       AND status = 'Finalizada'
                                       AND (time1_id = ? OR time2_id = ?)";
                
                $stmt_jogos_time = executeQuery($pdo, $sql_jogos_time, [$grupo_a_id, $time_id, $time_id]);
                $jogos_time = $stmt_jogos_time ? $stmt_jogos_time->fetchAll(PDO::FETCH_ASSOC) : [];
                
                if (count($jogos_time) > 0) {
                    // Calcular
                    $vitorias = 0;
                    $derrotas = 0;
                    $empates = 0;
                    $pontos_pro = 0;
                    $pontos_contra = 0;
                    
                    foreach ($jogos_time as $jogo) {
                        $pontos1 = (int)$jogo['pontos_time1'];
                        $pontos2 = (int)$jogo['pontos_time2'];
                        
                        if ($jogo['time1_id'] == $time_id) {
                            $pontos_pro += $pontos1;
                            $pontos_contra += $pontos2;
                            if ($pontos1 > $pontos2) $vitorias++;
                            elseif ($pontos1 < $pontos2) $derrotas++;
                            else $empates++;
                        } else {
                            $pontos_pro += $pontos2;
                            $pontos_contra += $pontos1;
                            if ($pontos2 > $pontos1) $vitorias++;
                            elseif ($pontos2 < $pontos1) $derrotas++;
                            else $empates++;
                        }
                    }
                    
                    $saldo = $pontos_pro - $pontos_contra;
                    $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
                    $pontos_total = ($vitorias * 3) + ($empates * 1);
                    
                    $classificacao_calculada_direta[] = [
                        'time_id' => $time_id,
                        'time_nome' => $time_grupo['time_nome'],
                        'nome' => $time_grupo['time_nome'],
                        'time_cor' => $time_grupo['time_cor'],
                        'cor' => $time_grupo['time_cor'],
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
            }
            
            // Ordenar
            usort($classificacao_calculada_direta, function($a, $b) {
                if ($a['pontos_total'] != $b['pontos_total']) return $b['pontos_total'] - $a['pontos_total'];
                if ($a['vitorias'] != $b['vitorias']) return $b['vitorias'] - $a['vitorias'];
                if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];
                return $b['saldo_pontos'] - $a['saldo_pontos'];
            });
            
            // Usar dados calculados
            if (count($classificacao_calculada_direta) >= 2) {
                $classificacao_a = [
                    $classificacao_calculada_direta[0],
                    $classificacao_calculada_direta[1]
                ];
                addDebug("✓ Classificação calculada diretamente dos jogos! 1º: {$classificacao_calculada_direta[0]['nome']} ({$classificacao_calculada_direta[0]['pontos_total']} pts), 2º: {$classificacao_calculada_direta[1]['nome']} ({$classificacao_calculada_direta[1]['pontos_total']} pts)");
            }
            
            // Se chegou aqui, significa que não encontrou na tabela nem conseguiu calcular acima
            // Usar fallback: buscar times do grupo e calcular manualmente (MESMA LÓGICA DA TELA)
            addDebug("⚠ Fallback: Calculando manualmente dos jogos (mesma lógica da tela)...");
            
            $sql_times_fallback = "SELECT tt.id as time_id, tt.nome as time_nome, tt.cor as time_cor
                                  FROM torneio_times tt
                                  JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                  WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
            $stmt_times_fallback = executeQuery($pdo, $sql_times_fallback, [$grupo_a_id, $torneio_id]);
            $times_fallback = $stmt_times_fallback ? $stmt_times_fallback->fetchAll(PDO::FETCH_ASSOC) : [];
            
            $classificacao_fallback = [];
            foreach ($times_fallback as $time_fallback) {
                $time_id = (int)$time_fallback['time_id'];
                
                // Buscar jogos (SEM filtro de fase - apenas grupo_id e status) - nova tabela partidas_2fase_torneio
                $sql_jogos_fallback = "SELECT pontos_time1, pontos_time2, time1_id, time2_id
                                       FROM partidas_2fase_torneio
                                       WHERE grupo_id = ? 
                                           AND status = 'Finalizada'
                                           AND (time1_id = ? OR time2_id = ?)";
                
                $stmt_jogos_fallback = executeQuery($pdo, $sql_jogos_fallback, [$grupo_a_id, $time_id, $time_id]);
                $jogos_fallback = $stmt_jogos_fallback ? $stmt_jogos_fallback->fetchAll(PDO::FETCH_ASSOC) : [];
                
                if (count($jogos_fallback) > 0) {
                    // Calcular (mesma lógica da tela)
                    $vitorias = 0;
                    $derrotas = 0;
                    $empates = 0;
                    $pontos_pro = 0;
                    $pontos_contra = 0;
                    
                    foreach ($jogos_fallback as $jogo) {
                        $pontos1 = (int)$jogo['pontos_time1'];
                        $pontos2 = (int)$jogo['pontos_time2'];
                        
                        if ($jogo['time1_id'] == $time_id) {
                            $pontos_pro += $pontos1;
                            $pontos_contra += $pontos2;
                            if ($pontos1 > $pontos2) $vitorias++;
                            elseif ($pontos1 < $pontos2) $derrotas++;
                            else $empates++;
                        } else {
                            $pontos_pro += $pontos2;
                            $pontos_contra += $pontos1;
                            if ($pontos2 > $pontos1) $vitorias++;
                            elseif ($pontos2 < $pontos1) $derrotas++;
                            else $empates++;
                        }
                    }
                    
                    $saldo = $pontos_pro - $pontos_contra;
                    $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
                    $pontos_total = ($vitorias * 3) + ($empates * 1);
                    
                    $classificacao_fallback[] = [
                        'time_id' => $time_id,
                        'time_nome' => $time_fallback['time_nome'],
                        'nome' => $time_fallback['time_nome'],
                        'time_cor' => $time_fallback['time_cor'],
                        'cor' => $time_fallback['time_cor'],
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
            }
            
            // Ordenar
            usort($classificacao_fallback, function($a, $b) {
                if ($a['pontos_total'] != $b['pontos_total']) return $b['pontos_total'] - $a['pontos_total'];
                if ($a['vitorias'] != $b['vitorias']) return $b['vitorias'] - $a['vitorias'];
                if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];
                return $b['saldo_pontos'] - $a['saldo_pontos'];
            });
            
            // Exibir tabela
            addDebug("=== TABELA CLASSIFICAÇÃO - OURO A (CALCULADA DOS JOGOS) ===");
            addDebug("Pos | Time | J | V | D | Pts | PF | PS | Saldo | Avg");
            addDebug("--- | ---- | - | - | - | --- | -- | -- | ----- | ---");
            $posicao = 1;
            foreach ($classificacao_fallback as $time) {
                $jogos = $time['vitorias'] + $time['derrotas'] + $time['empates'];
                addDebug("$posicao | {$time['nome']} | $jogos | {$time['vitorias']} | {$time['derrotas']} | {$time['pontos_total']} | {$time['pontos_pro']} | {$time['pontos_contra']} | {$time['saldo_pontos']} | " . number_format($time['average'], 2));
                $posicao++;
            }
            
            // Usar dados calculados
            if (count($classificacao_fallback) >= 2) {
                $classificacao_a = [
                    $classificacao_fallback[0],
                    $classificacao_fallback[1]
                ];
                addDebug("✓ Classificação calculada! 1º: {$classificacao_fallback[0]['nome']} ({$classificacao_fallback[0]['pontos_total']} pts), 2º: {$classificacao_fallback[1]['nome']} ({$classificacao_fallback[1]['pontos_total']} pts)");
            } elseif (count($classificacao_fallback) === 1) {
                $classificacao_a = [$classificacao_fallback[0]];
            }
        }
    }
    
    // Buscar classificação do grupo B da série - PRIMEIRO tentar da nova tabela torneio_classificacao_2fase
    // Inicializar variável
    $todos_times_b = [];
    
    if ($table_exists_2fase) {
        // Usar nova tabela torneio_classificacao_2fase
        $sql_class_b = "SELECT 
                                tc.time_id, 
                                tc.serie,
                                tc.vitorias, 
                                tc.derrotas, 
                                tc.empates,
                                tc.pontos_pro, 
                                tc.pontos_contra, 
                                tc.saldo_pontos, 
                                tc.average, 
                                tc.pontos_total,
                                tc.posicao,
                                tt.nome AS time_nome, 
                                tt.nome AS nome,
                                tt.cor AS time_cor,
                                tt.cor AS cor
                             FROM torneio_classificacao_2fase tc
                             JOIN torneio_times tt ON tt.id = tc.time_id
                             WHERE tc.torneio_id = ? AND tc.serie = ?
                             ORDER BY 
                                tc.pontos_total DESC, 
                                tc.vitorias DESC,
                                tc.average DESC, 
                                tc.saldo_pontos DESC";
        $stmt_class_b = executeQuery($pdo, $sql_class_b, [$torneio_id, "$serie B"]);
        $todos_times_b = $stmt_class_b ? $stmt_class_b->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($todos_times_b) > 0) {
            addDebug("✓ Classificação $serie B encontrada na tabela torneio_classificacao_2fase: " . count($todos_times_b) . " times");
        }
    }
    
    // Se não encontrou na nova tabela, usar nova tabela partidas_2fase_classificacao
    if (count($todos_times_b) == 0) {
        $sql_class_b = "SELECT tc.time_id, tt.id, tt.nome, tt.cor, tc.pontos_total, tc.vitorias, tc.average, tc.saldo_pontos, tc.posicao, tc.grupo_id
                             FROM partidas_2fase_classificacao tc
                             JOIN torneio_times tt ON tt.id = tc.time_id
                             WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                             ORDER BY 
                                tc.pontos_total DESC, 
                                tc.average DESC";
        $stmt_class_b = executeQuery($pdo, $sql_class_b, [$torneio_id, $grupo_b_id]);
        $todos_times_b = $stmt_class_b ? $stmt_class_b->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($todos_times_b) > 0) {
            addDebug("⚠ Classificação $serie B encontrada na tabela partidas_2fase_classificacao: " . count($todos_times_b) . " times");
        }
    }
    
    // Verificar se os valores estão zerados
    $valores_zerados_b = false;
    if (count($todos_times_b) > 0) {
        $primeiro_time = $todos_times_b[0];
        $pontos_total = $primeiro_time['pontos_total'] ?? null;
        $average = $primeiro_time['average'] ?? null;
        if (($pontos_total === null || $pontos_total == 0) && ($average === null || $average == 0)) {
            $valores_zerados_b = true;
        }
    }
    
    // Selecionar os 2 primeiros do grupo B
    $classificacao_b = [];
    if (!$valores_zerados_b && count($todos_times_b) >= 2) {
        $classificacao_b = [
            $todos_times_b[0],
            $todos_times_b[1]
        ];
    } elseif (!$valores_zerados_b && count($todos_times_b) === 1) {
        $classificacao_b = [$todos_times_b[0]];
    }
    
    // Armazenar classificação completa do grupo B (sem debug)
    $classificacao_completa_b = [];
    if (count($todos_times_b) > 0) {
        foreach ($todos_times_b as $idx => $time) {
            $classificacao_completa_b[] = [
                'posicao' => $idx + 1,
                'time_id' => $time['time_id'] ?? $time['id'],
                'nome' => $time['nome'] ?? 'N/A',
                'pontos_total' => $time['pontos_total'] ?? 0,
                'average' => $time['average'] ?? 0,
                'vitorias' => $time['vitorias'] ?? 0,
                'saldo_pontos' => $time['saldo_pontos'] ?? 0,
                'selecionado' => $idx < 2
            ];
        }
    }
    
    // Se não encontrou na tabela, calcular dos jogos (MESMA LÓGICA DA TELA)
    if (count($classificacao_b) < 2 || $valores_zerados_b) {
        addDebug("⚠ $serie B: Calculando diretamente dos jogos (mesma lógica da tela)...");
        
        // Buscar times do grupo
        $sql_times_b = "SELECT tt.id as time_id, tt.nome as time_nome, tt.cor as time_cor
                            FROM torneio_times tt
                            JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                            WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
        $stmt_times_b = executeQuery($pdo, $sql_times_b, [$grupo_b_id, $torneio_id]);
        $times_b = $stmt_times_b ? $stmt_times_b->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $classificacao_b_calculada = [];
        foreach ($times_b as $time_b) {
            $time_id = (int)$time_b['time_id'];
            
            // Buscar jogos (SEM filtro de fase - apenas grupo_id e status) - nova tabela partidas_2fase_torneio
            $sql_jogos_b = "SELECT pontos_time1, pontos_time2, time1_id, time2_id
                                 FROM partidas_2fase_torneio
                                 WHERE grupo_id = ? 
                                     AND status = 'Finalizada'
                                     AND (time1_id = ? OR time2_id = ?)";
            
            $stmt_jogos_b = executeQuery($pdo, $sql_jogos_b, [$grupo_b_id, $time_id, $time_id]);
            $jogos_b = $stmt_jogos_b ? $stmt_jogos_b->fetchAll(PDO::FETCH_ASSOC) : [];
            
            if (count($jogos_b) > 0) {
                // Calcular (mesma lógica da tela)
                $vitorias = 0;
                $derrotas = 0;
                $empates = 0;
                $pontos_pro = 0;
                $pontos_contra = 0;
                
                foreach ($jogos_b as $jogo) {
                    $pontos1 = (int)$jogo['pontos_time1'];
                    $pontos2 = (int)$jogo['pontos_time2'];
                    
                    if ($jogo['time1_id'] == $time_id) {
                        $pontos_pro += $pontos1;
                        $pontos_contra += $pontos2;
                        if ($pontos1 > $pontos2) $vitorias++;
                        elseif ($pontos1 < $pontos2) $derrotas++;
                        else $empates++;
                    } else {
                        $pontos_pro += $pontos2;
                        $pontos_contra += $pontos1;
                        if ($pontos2 > $pontos1) $vitorias++;
                        elseif ($pontos2 < $pontos1) $derrotas++;
                        else $empates++;
                    }
                }
                
                $saldo = $pontos_pro - $pontos_contra;
                $average = $pontos_contra > 0 ? ($pontos_pro / $pontos_contra) : ($pontos_pro > 0 ? 999.99 : 0.00);
                $pontos_total = ($vitorias * 3) + ($empates * 1);
                
                $classificacao_b_calculada[] = [
                    'time_id' => $time_id,
                    'time_nome' => $time_b['time_nome'],
                    'nome' => $time_b['time_nome'],
                    'time_cor' => $time_b['time_cor'],
                    'cor' => $time_b['time_cor'],
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
        }
        
        // Ordenar
        usort($classificacao_b_calculada, function($a, $b) {
            if ($a['pontos_total'] != $b['pontos_total']) return $b['pontos_total'] - $a['pontos_total'];
            if ($a['vitorias'] != $b['vitorias']) return $b['vitorias'] - $a['vitorias'];
            if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];
            return $b['saldo_pontos'] - $a['saldo_pontos'];
        });
        
        // Log da ordenação calculada
        addDebug("=== $serie B CALCULADO DOS JOGOS ===");
        foreach ($classificacao_b_calculada as $idx => $time) {
            $jogos = $time['vitorias'] + $time['derrotas'] + $time['empates'];
            addDebug(($idx + 1) . "º: {$time['nome']} | J=$jogos | V={$time['vitorias']} | D={$time['derrotas']} | Pts={$time['pontos_total']} | Avg=" . number_format($time['average'], 2));
        }
        
        // Usar dados calculados
        if (count($classificacao_b_calculada) >= 2) {
            $classificacao_b = [
                $classificacao_b_calculada[0],
                $classificacao_b_calculada[1]
            ];
            addDebug("✓ $serie B calculado! 1º: {$classificacao_b_calculada[0]['nome']} ({$classificacao_b_calculada[0]['pontos_total']} pts), 2º: {$classificacao_b_calculada[1]['nome']} ({$classificacao_b_calculada[1]['pontos_total']} pts)");
        } elseif (count($classificacao_b_calculada) === 1) {
            $classificacao_b = [$classificacao_b_calculada[0]];
        }
        
        // Atualizar array completo
        $todos_times_b = $classificacao_b_calculada;
        // Se ainda não encontrou, buscar apenas os times do grupo (sem classificação)
        if (count($classificacao_b) < 2) {
            $sql_times_grupo_b = "SELECT tt.id AS time_id, tt.id, tt.nome, tt.cor
                                 FROM torneio_times tt
                                 JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                 WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                                 LIMIT 2";
            $stmt_times_grupo_b = executeQuery($pdo, $sql_times_grupo_b, [$grupo_b_id, $torneio_id]);
            $times_grupo_b = $stmt_times_grupo_b ? $stmt_times_grupo_b->fetchAll(PDO::FETCH_ASSOC) : [];
            if (count($times_grupo_b) >= 2) {
                $classificacao_b = $times_grupo_b;
            }
        }
    }
    
    // Se ainda não encontrou grupo A, buscar diretamente do grupo
    if (count($classificacao_a) < 2) {
        $sql_times_grupo_a = "SELECT tt.id AS time_id, tt.id, tt.nome, tt.cor
                             FROM torneio_times tt
                             JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                             WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                             LIMIT 2";
        $stmt_times_grupo_a = executeQuery($pdo, $sql_times_grupo_a, [$grupo_a_id, $torneio_id]);
        $times_grupo_a = $stmt_times_grupo_a ? $stmt_times_grupo_a->fetchAll(PDO::FETCH_ASSOC) : [];
        if (count($times_grupo_a) >= 2) {
            $classificacao_a = $times_grupo_a;
        }
    }
    
    if (count($classificacao_a) < 2 || count($classificacao_b) < 2) {
        // Verificar se há times nos grupos
        $sql_check_times_a = "SELECT COUNT(*) as total FROM torneio_grupo_times WHERE grupo_id = ?";
        $stmt_check_times_a = executeQuery($pdo, $sql_check_times_a, [$grupo_a_id]);
        $total_times_a = $stmt_check_times_a ? (int)$stmt_check_times_a->fetch()['total'] : 0;
        
        $sql_check_times_b = "SELECT COUNT(*) as total FROM torneio_grupo_times WHERE grupo_id = ?";
        $stmt_check_times_b = executeQuery($pdo, $sql_check_times_b, [$grupo_b_id]);
        $total_times_b = $stmt_check_times_b ? (int)$stmt_check_times_b->fetch()['total'] : 0;
        
        // Verificar se há jogos finalizados
        if ($tem_tipo_fase) {
            $sql_check_jogos_a = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ? AND grupo_id = ? AND status = 'Finalizada'";
            $sql_check_jogos_b = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ? AND grupo_id = ? AND status = 'Finalizada'";
        } else {
            $sql_check_jogos_a = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ? AND grupo_id = ? AND status = 'Finalizada'";
            $sql_check_jogos_b = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ? AND grupo_id = ? AND status = 'Finalizada'";
        }
        $stmt_check_jogos_a = executeQuery($pdo, $sql_check_jogos_a, [$torneio_id, $grupo_a_id]);
        $total_jogos_a = $stmt_check_jogos_a ? (int)$stmt_check_jogos_a->fetch()['total'] : 0;
        $stmt_check_jogos_b = executeQuery($pdo, $sql_check_jogos_b, [$torneio_id, $grupo_b_id]);
        $total_jogos_b = $stmt_check_jogos_b ? (int)$stmt_check_jogos_b->fetch()['total'] : 0;
        
        $msg_erro = "❌ Não foi possível gerar as semi-finais da série $serie.\n\n";
        $msg_erro .= "📊 Situação atual:\n";
        $msg_erro .= "• $serie A: " . count($classificacao_a) . " time(s) classificados (necessário: 2)\n";
        $msg_erro .= "• $serie B: " . count($classificacao_b) . " time(s) classificados (necessário: 2)\n\n";
        
        $msg_erro .= "📋 Detalhes dos grupos:\n";
        $msg_erro .= "• Times no grupo $serie A: $total_times_a\n";
        $msg_erro .= "• Times no grupo $serie B: $total_times_b\n";
        $msg_erro .= "• Jogos finalizados $serie A: $total_jogos_a\n";
        $msg_erro .= "• Jogos finalizados $serie B: $total_jogos_b\n\n";
        
        // Adicionar informações sobre o que está faltando
        if (count($classificacao_a) < 2) {
            $msg_erro .= "⚠️ Problema no $serie A:\n";
            if ($total_times_a < 2) {
                $msg_erro .= "   - O grupo $serie A tem menos de 2 times ($total_times_a). É necessário pelo menos 2 times.\n";
            } elseif ($total_jogos_a == 0) {
                $msg_erro .= "   - Nenhum jogo foi finalizado no grupo $serie A. Finalize os jogos antes de gerar as semi-finais.\n";
            } else {
                $msg_erro .= "   - Apenas " . count($classificacao_a) . " time(s) foi(ram) encontrado(s) na classificação.\n";
                $msg_erro .= "   - Verifique se os jogos foram finalizados corretamente e se a classificação foi atualizada.\n";
            }
        }
        
        if (count($classificacao_b) < 2) {
            $msg_erro .= "\n⚠️ Problema no $serie B:\n";
            if ($total_times_b < 2) {
                $msg_erro .= "   - O grupo $serie B tem menos de 2 times ($total_times_b). É necessário pelo menos 2 times.\n";
            } elseif ($total_jogos_b == 0) {
                $msg_erro .= "   - Nenhum jogo foi finalizado no grupo $serie B. Finalize os jogos antes de gerar as semi-finais.\n";
            } else {
                $msg_erro .= "   - Apenas " . count($classificacao_b) . " time(s) foi(ram) encontrado(s) na classificação.\n";
                $msg_erro .= "   - Verifique se os jogos foram finalizados corretamente e se a classificação foi atualizada.\n";
            }
        }
        
        $msg_erro .= "\n✅ Para gerar as semi-finais, você precisa:\n";
        $msg_erro .= "1. Ter pelo menos 2 times em cada grupo (Ouro A e Ouro B)\n";
        $msg_erro .= "2. Gerar os jogos da 2ª fase (clique em 'Gerar Jogos da 2ª Fase')\n";
        $msg_erro .= "3. Finalizar todos os jogos dos grupos $serie A e $serie B\n";
        $msg_erro .= "4. Aguardar a atualização da classificação\n";
        $msg_erro .= "5. Tentar gerar as semi-finais novamente\n";
        
        throw new Exception($msg_erro);
    }
    
    // Garantir que estamos usando o campo correto (time_id ou id)
    $primeiro_a = (int)($classificacao_a[0]['time_id'] ?? $classificacao_a[0]['id'] ?? 0);
    $segundo_a = (int)($classificacao_a[1]['time_id'] ?? $classificacao_a[1]['id'] ?? 0);
    $primeiro_b = (int)($classificacao_b[0]['time_id'] ?? $classificacao_b[0]['id'] ?? 0);
    $segundo_b = (int)($classificacao_b[1]['time_id'] ?? $classificacao_b[1]['id'] ?? 0);
    
    if ($primeiro_a == 0 || $segundo_a == 0 || $primeiro_b == 0 || $segundo_b == 0) {
        $msg_erro = "❌ Erro ao identificar os times classificados.\n\n";
        $msg_erro .= "📊 Times identificados:\n";
        $msg_erro .= "• 1º $serie A: " . ($primeiro_a > 0 ? "ID=$primeiro_a" : "❌ NÃO ENCONTRADO") . "\n";
        $msg_erro .= "• 2º $serie A: " . ($segundo_a > 0 ? "ID=$segundo_a" : "❌ NÃO ENCONTRADO") . "\n";
        $msg_erro .= "• 1º $serie B: " . ($primeiro_b > 0 ? "ID=$primeiro_b" : "❌ NÃO ENCONTRADO") . "\n";
        $msg_erro .= "• 2º $serie B: " . ($segundo_b > 0 ? "ID=$segundo_b" : "❌ NÃO ENCONTRADO") . "\n\n";
        
        $msg_erro .= "📋 Dados da classificação:\n";
        $msg_erro .= "• $serie A encontrados: " . count($classificacao_a) . " time(s)\n";
        if (count($classificacao_a) > 0) {
            $msg_erro .= "  - 1º: " . ($classificacao_a[0]['nome'] ?? 'N/A') . " (ID: " . ($classificacao_a[0]['time_id'] ?? $classificacao_a[0]['id'] ?? 'N/A') . ")\n";
            if (count($classificacao_a) > 1) {
                $msg_erro .= "  - 2º: " . ($classificacao_a[1]['nome'] ?? 'N/A') . " (ID: " . ($classificacao_a[1]['time_id'] ?? $classificacao_a[1]['id'] ?? 'N/A') . ")\n";
            }
        }
        $msg_erro .= "• $serie B encontrados: " . count($classificacao_b) . " time(s)\n";
        if (count($classificacao_b) > 0) {
            $msg_erro .= "  - 1º: " . ($classificacao_b[0]['nome'] ?? 'N/A') . " (ID: " . ($classificacao_b[0]['time_id'] ?? $classificacao_b[0]['id'] ?? 'N/A') . ")\n";
            if (count($classificacao_b) > 1) {
                $msg_erro .= "  - 2º: " . ($classificacao_b[1]['nome'] ?? 'N/A') . " (ID: " . ($classificacao_b[1]['time_id'] ?? $classificacao_b[1]['id'] ?? 'N/A') . ")\n";
            }
        }
        
        $msg_erro .= "\n⚠️ Possíveis causas:\n";
        $msg_erro .= "• Os times não têm o campo 'time_id' ou 'id' preenchido corretamente\n";
        $msg_erro .= "• A classificação não foi calculada corretamente\n";
        $msg_erro .= "• Há menos de 2 times em algum dos grupos\n";
        $msg_erro .= "\n✅ Solução:\n";
        $msg_erro .= "1. Verifique se há pelo menos 2 times em cada grupo ($serie A e $serie B)\n";
        $msg_erro .= "2. Finalize todos os jogos da 2ª fase\n";
        $msg_erro .= "3. Verifique se a classificação está sendo atualizada corretamente\n";
        $msg_erro .= "4. Tente gerar as semi-finais novamente\n";
        
        throw new Exception($msg_erro);
    }
    
    // Buscar ou criar grupo de chaves da série (Semi-Final)
    $sql_grupo_chaves = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
    $stmt_grupo_chaves = executeQuery($pdo, $sql_grupo_chaves, [$torneio_id, "2ª Fase - $serie - Chaves"]);
    $grupo_chaves_existente = $stmt_grupo_chaves ? $stmt_grupo_chaves->fetch() : null;
    
    if ($grupo_chaves_existente) {
        $grupo_chaves_id = (int)$grupo_chaves_existente['id'];
        addDebug("Usando grupo existente '2ª Fase - $serie - Chaves' com ID: $grupo_chaves_id");
    } else {
        $sql_insert_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
        executeQuery($pdo, $sql_insert_grupo, [$torneio_id, "2ª Fase - $serie - Chaves", 200]);
        $grupo_chaves_id = (int)$pdo->lastInsertId();
        addDebug("Criado novo grupo '2ª Fase - $serie - Chaves' com ID: $grupo_chaves_id");
    }
    
    // Verificar se já existem semi-finais da série e removê-las completamente (para evitar duplicatas)
    // Remover APENAS as semi-finais da série específica (não remover de outras séries)
    // Para semi-finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
    $sql_limpar_jogos = "DELETE FROM partidas_2fase_eliminatorias 
                        WHERE torneio_id = ? 
                        AND tipo_eliminatoria = 'Semi-Final' 
                        AND serie = ?";
    $stmt_limpar = executeQuery($pdo, $sql_limpar_jogos, [
        $torneio_id,
        $serie  // Apenas 'Ouro', 'Prata' ou 'Bronze'
    ]);
    $jogos_removidos = $stmt_limpar ? $stmt_limpar->rowCount() : 0;
    addDebug("Removidos $jogos_removidos semi-finais existentes da série $serie (limpando para criar apenas os 2 jogos da semi-final)");
    
    // Se houver semi-finais com série incorreta (ex: 'Ouro A' em vez de 'Ouro') que correspondem a esta série, corrigir
    // Isso corrige registros antigos que podem ter sido criados com série incorreta
    if ($jogos_removidos == 0) {
        // Verificar se há semi-finais com série incorreta (com A ou B) que podem ser desta série
        $serie_a_old = $serie . ' A';
        $serie_b_old = $serie . ' B';
        $sql_check_incorreta = "SELECT id FROM partidas_2fase_eliminatorias 
                          WHERE torneio_id = ? 
                          AND tipo_eliminatoria = 'Semi-Final' 
                          AND (serie = ? OR serie = ?)
                          AND ((time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                               OR (time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                   AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?)))";
        $stmt_check_incorreta = executeQuery($pdo, $sql_check_incorreta, [
            $torneio_id,
            $serie_a_old, $serie_b_old,
            $grupo_a_id, $grupo_b_id,
            $grupo_b_id, $grupo_a_id
        ]);
        $semi_incorreta = $stmt_check_incorreta ? $stmt_check_incorreta->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($semi_incorreta) > 0) {
            // Atualizar essas semi-finais para ter a série correta (sem A ou B)
            $sql_update_serie = "UPDATE partidas_2fase_eliminatorias 
                                SET serie = ?
                                WHERE torneio_id = ? 
                                AND tipo_eliminatoria = 'Semi-Final' 
                                AND (serie = ? OR serie = ?)
                                AND ((time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                      AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?))
                                     OR (time1_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?) 
                                         AND time2_id IN (SELECT time_id FROM torneio_grupo_times WHERE grupo_id = ?)))";
            $stmt_update_serie = executeQuery($pdo, $sql_update_serie, [
                $serie,  // Atualizar para 'Ouro', 'Prata' ou 'Bronze' (sem A ou B)
                $torneio_id,
                $serie_a_old, $serie_b_old,
                $grupo_a_id, $grupo_b_id,
                $grupo_b_id, $grupo_a_id
            ]);
            $atualizados = $stmt_update_serie ? $stmt_update_serie->rowCount() : 0;
            if ($atualizados > 0) {
                addDebug("Atualizadas $atualizados semi-finais com série incorreta ($serie_a_old/$serie_b_old) para série $serie");
                // Agora remover novamente com a série correta
                $stmt_limpar = executeQuery($pdo, $sql_limpar_jogos, [
                    $torneio_id,
                    $serie  // Apenas 'Ouro', 'Prata' ou 'Bronze'
                ]);
                $jogos_removidos = $stmt_limpar ? $stmt_limpar->rowCount() : 0;
            }
        }
    }
    
    $partidas_inseridas = 0;
    
    // Criar apenas 2 jogos eliminatórios da semi-final:
    // Jogo 1: 1º Série A vs 2º Série B
    // Jogo 2: 2º Série A vs 1º Série B
    // Os 2 vencedores vão para a final
    
    // Para semi-finais e finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
    // O campo serie é um ENUM que aceita: 'Ouro', 'Ouro A', 'Ouro B', 'Prata', 'Prata A', 'Prata B', 'Bronze', 'Bronze A', 'Bronze B'
    
    $jogos = [
        ['time1' => $primeiro_a, 'time2' => $segundo_b, 'descricao' => "1º $serie A vs 2º $serie B", 'rodada' => 1, 'serie' => $serie],  // Apenas 'Ouro', 'Prata' ou 'Bronze'
        ['time1' => $segundo_a, 'time2' => $primeiro_b, 'descricao' => "2º $serie A vs 1º $serie B", 'rodada' => 1, 'serie' => $serie]   // Apenas 'Ouro', 'Prata' ou 'Bronze'
    ];
    
    foreach ($jogos as $index => $jogo) {
        addDebug("Criando jogo " . ($index + 1) . ": " . $jogo['descricao'] . " (Time1 ID=" . $jogo['time1'] . " vs Time2 ID=" . $jogo['time2'] . ")");
        
        // Usar série do array - garantir que está definida
        // Para semi-finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
        $serie_eliminatoria = $jogo['serie'] ?? $serie;
        if (empty($serie_eliminatoria)) {
            $serie_eliminatoria = $serie; // Padrão: "Ouro", "Prata", "Bronze"
        }
        
        // Validar que é um valor válido do ENUM para semi-finais
        $valores_enum_validos_semi = ['Ouro', 'Prata', 'Bronze'];
        if (!in_array($serie_eliminatoria, $valores_enum_validos_semi)) {
            // Se não for válido, usar a série principal
            $serie_eliminatoria = $serie;
            addDebug("AVISO: Série '$serie_eliminatoria' não é válida para semi-finais, usando: $serie_eliminatoria");
        }
        
        addDebug("Série para inserção (semi-final): '$serie_eliminatoria'");
        
        // Verificar se o jogo já existe para evitar duplicatas (nova tabela partidas_2fase_eliminatorias)
        // Verificar também pela série para evitar conflitos entre séries diferentes
        // Para semi-finais, usamos apenas: 'Ouro', 'Prata', 'Bronze' (sem A ou B)
        $sql_check_jogo = "SELECT id FROM partidas_2fase_eliminatorias 
                          WHERE torneio_id = ? 
                          AND tipo_eliminatoria = 'Semi-Final'
                          AND serie = ?
                          AND ((time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?))";
        $stmt_check_jogo = executeQuery($pdo, $sql_check_jogo, [
            $torneio_id,
            $serie_eliminatoria,  // Apenas 'Ouro', 'Prata' ou 'Bronze'
            $jogo['time1'], 
            $jogo['time2'],
            $jogo['time2'], 
            $jogo['time1']
        ]);
        $jogo_existente = $stmt_check_jogo ? $stmt_check_jogo->fetch() : null;
        
        if ($jogo_existente) {
            addDebug("Jogo já existe, pulando: " . $jogo['descricao'] . " (ID: {$jogo_existente['id']})");
            continue;
        }
        
        // Inserir na nova tabela partidas_2fase_eliminatorias
        // Garantir que a série seja sempre preenchida
        // Validar que a série não está vazia antes de inserir
        if (empty($serie_eliminatoria) || trim($serie_eliminatoria) === '') {
            $serie_eliminatoria = $serie; // Fallback para a série principal
            addDebug("AVISO: Série estava vazia, usando série principal: $serie_eliminatoria");
        }
        
        // Garantir que a série é uma string válida e não vazia
        $serie_eliminatoria = trim((string)$serie_eliminatoria);
        if (empty($serie_eliminatoria)) {
            throw new Exception("Erro: Não foi possível determinar a série para o jogo " . ($index + 1) . ". Série recebida: '" . ($jogo['serie'] ?? 'NULL') . "', Série principal: '$serie'");
        }
        
        // Log detalhado antes de inserir
        addDebug("=== INSERÇÃO DE JOGO " . ($index + 1) . " ===");
        addDebug("Série para inserção: '$serie_eliminatoria' (tipo: " . gettype($serie_eliminatoria) . ", tamanho: " . strlen($serie_eliminatoria) . ")");
        addDebug("torneio_id: $torneio_id");
        addDebug("time1_id: {$jogo['time1']}");
        addDebug("time2_id: {$jogo['time2']}");
        addDebug("rodada: {$jogo['rodada']}");
        addDebug("quadra: 1");
        
        $sql_insert = "INSERT INTO partidas_2fase_eliminatorias (torneio_id, time1_id, time2_id, serie, tipo_eliminatoria, rodada, quadra, status) 
                      VALUES (?, ?, ?, ?, 'Semi-Final', ?, ?, 'Agendada')";
        
        // Preparar statement manualmente para garantir que os valores estão corretos
        $stmt_insert = $pdo->prepare($sql_insert);
        if (!$stmt_insert) {
            $error_info = $pdo->errorInfo();
            addDebug("ERRO ao preparar statement: " . json_encode($error_info));
            throw new Exception("Erro ao preparar statement: " . ($error_info[2] ?? 'Erro desconhecido'));
        }
        
        // Preparar array de valores na ordem correta
        $valores_insert = [
            $torneio_id, 
            (int)$jogo['time1'], 
            (int)$jogo['time2'], 
            $serie_eliminatoria, // Garantir que a série está sendo passada como string
            (int)$jogo['rodada'], // usar rodada do array
            1 // quadra padrão
        ];
        
        addDebug("Valores a serem inseridos: " . json_encode($valores_insert));
        
        $result = $stmt_insert->execute($valores_insert);
        
        if ($result === false) {
            $error_info = $stmt_insert->errorInfo();
            addDebug("ERRO ao inserir jogo " . ($index + 1) . ": " . ($error_info[2] ?? 'Erro desconhecido'));
            addDebug("Valores tentados: torneio_id=$torneio_id, time1={$jogo['time1']}, time2={$jogo['time2']}, serie='$serie_eliminatoria', rodada={$jogo['rodada']}");
            throw new Exception("Erro ao inserir jogo " . ($index + 1) . ": " . ($error_info[2] ?? 'Erro desconhecido'));
        }
        
        $partida_id = (int)$pdo->lastInsertId();
        addDebug("Jogo " . ($index + 1) . " criado com sucesso! ID: $partida_id");
        
        // Verificar se a série foi realmente salva corretamente
        $sql_verificar_serie = "SELECT serie FROM partidas_2fase_eliminatorias WHERE id = ?";
        $stmt_verificar_serie = $pdo->prepare($sql_verificar_serie);
        $stmt_verificar_serie->execute([$partida_id]);
        $serie_salva = $stmt_verificar_serie->fetch(PDO::FETCH_ASSOC);
        
        $serie_salva_valor = $serie_salva['serie'] ?? null;
        
        // Validar que a série salva é o valor esperado (apenas 'Ouro', 'Prata' ou 'Bronze' para semi-finais)
        if ($serie_salva_valor !== $serie_eliminatoria) {
            // Se a série não foi salva corretamente, atualizar manualmente
            addDebug("AVISO: Série não foi salva corretamente para o jogo ID $partida_id. Valor salvo: '$serie_salva_valor', Esperado: '$serie_eliminatoria'. Atualizando...");
            $sql_update_serie = "UPDATE partidas_2fase_eliminatorias SET serie = ? WHERE id = ?";
            $stmt_update_serie = $pdo->prepare($sql_update_serie);
            $result_update = $stmt_update_serie->execute([$serie_eliminatoria, $partida_id]);
            if ($result_update) {
                addDebug("Série atualizada manualmente para o jogo ID $partida_id: '$serie_eliminatoria'");
            } else {
                $error_update = $stmt_update_serie->errorInfo();
                addDebug("ERRO ao atualizar série: " . ($error_update[2] ?? 'Erro desconhecido'));
            }
        } else {
            addDebug("Série confirmada no banco para o jogo ID $partida_id: '$serie_salva_valor' (esperado: '$serie_eliminatoria')");
        }
        
        $partidas_inseridas++;
    }
    
    if ($partidas_inseridas === 0) {
        // Verificar o que aconteceu
        $jogos_pulados = 0;
        $jogos_com_erro = 0;
        $detalhes_erro = [];
        
        foreach ($jogos as $index => $jogo) {
            // Verificar se o jogo já existe
            $sql_check_jogo = "SELECT id FROM partidas_2fase_eliminatorias 
                              WHERE torneio_id = ? 
                              AND tipo_eliminatoria = 'Semi-Final'
                              AND ((time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?))";
            $stmt_check_jogo = executeQuery($pdo, $sql_check_jogo, [
                $torneio_id, 
                $jogo['time1'], 
                $jogo['time2'],
                $jogo['time2'], 
                $jogo['time1']
            ]);
            $jogo_existente = $stmt_check_jogo ? $stmt_check_jogo->fetch() : null;
            
            if ($jogo_existente) {
                $jogos_pulados++;
                $detalhes_erro[] = "Jogo " . ($index + 1) . " ({$jogo['descricao']}) já existe (ID: {$jogo_existente['id']})";
            } else {
                // Tentar inserir para ver o erro
                $sql_insert = "INSERT INTO partidas_2fase_eliminatorias (torneio_id, time1_id, time2_id, serie, tipo_eliminatoria, rodada, quadra, status) 
                              VALUES (?, ?, ?, ?, 'Semi-Final', ?, ?, 'Agendada')";
                $result = executeQuery($pdo, $sql_insert, [
                    $torneio_id, 
                    $jogo['time1'], 
                    $jogo['time2'], 
                    $jogo['serie'],
                    $jogo['rodada'],
                    1
                ]);
                
                if ($result === false) {
                    $jogos_com_erro++;
                    $error_info = $pdo->errorInfo();
                    $detalhes_erro[] = "Jogo " . ($index + 1) . " ({$jogo['descricao']}): " . ($error_info[2] ?? 'Erro desconhecido');
                }
            }
        }
        
        $msg_erro = "Nenhuma semi-final foi criada.\n\n";
        $msg_erro .= "Detalhes:\n";
        $msg_erro .= "- Total de jogos tentados: " . count($jogos) . "\n";
        $msg_erro .= "- Jogos já existentes (pulados): $jogos_pulados\n";
        $msg_erro .= "- Jogos com erro: $jogos_com_erro\n\n";
        
        if (count($detalhes_erro) > 0) {
            $msg_erro .= "Detalhes por jogo:\n";
            foreach ($detalhes_erro as $detalhe) {
                $msg_erro .= "- $detalhe\n";
            }
        }
        
        // Adicionar informações sobre os times
        $msg_erro .= "\nTimes identificados:\n";
        $msg_erro .= "- 1º $serie A: ID={$primeiro_a} (" . ($classificacao_a[0]['nome'] ?? 'N/A') . ")\n";
        $msg_erro .= "- 2º $serie A: ID={$segundo_a} (" . ($classificacao_a[1]['nome'] ?? 'N/A') . ")\n";
        $msg_erro .= "- 1º $serie B: ID={$primeiro_b} (" . ($classificacao_b[0]['nome'] ?? 'N/A') . ")\n";
        $msg_erro .= "- 2º $serie B: ID={$segundo_b} (" . ($classificacao_b[1]['nome'] ?? 'N/A') . ")\n";
        
        // Verificar se os times existem
        $sql_check_time1 = "SELECT id, nome FROM torneio_times WHERE id = ? AND torneio_id = ?";
        $stmt_check_time1 = executeQuery($pdo, $sql_check_time1, [$primeiro_a, $torneio_id]);
        $time1_existe = $stmt_check_time1 ? $stmt_check_time1->fetch() : null;
        
        if (!$time1_existe) {
            $msg_erro .= "\n⚠ ATENÇÃO: O time 1º $serie A (ID=$primeiro_a) não foi encontrado no banco de dados!";
        }
        
        throw new Exception($msg_erro);
    }
    
    $pdo->commit();
    
    // Verificar se os jogos foram realmente inseridos (nova tabela partidas_2fase_eliminatorias)
    // Verificar pela série específica (apenas 'Ouro', 'Prata' ou 'Bronze' para semi-finais)
    $sql_verificar = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                      WHERE torneio_id = ? 
                      AND tipo_eliminatoria = 'Semi-Final' 
                      AND serie = ?";
    $stmt_verificar = executeQuery($pdo, $sql_verificar, [$torneio_id, $serie]);
    $total_verificado = $stmt_verificar ? (int)$stmt_verificar->fetch()['total'] : 0;
    addDebug("Total de partidas verificadas após commit (série $serie): $total_verificado (esperado: $partidas_inseridas)");
    
        // Se não encontrou pela série exata, verificar se foram inseridas com série incorreta (problema anterior)
        // O campo serie é um ENUM NOT NULL, então não pode ser NULL, mas pode ter valor incorreto (ex: 'Ouro A' em vez de 'Ouro')
        if ($total_verificado == 0 && $partidas_inseridas > 0) {
            $serie_a_old = $serie . ' A';
            $serie_b_old = $serie . ' B';
            $sql_verificar_incorreta = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                               WHERE torneio_id = ? 
                               AND tipo_eliminatoria = 'Semi-Final' 
                               AND serie IN (?, ?)
                               AND ((time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?)
                                    OR (time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?))";
            $stmt_verificar_incorreta = executeQuery($pdo, $sql_verificar_incorreta, [
                $torneio_id,
                $serie_a_old, $serie_b_old,
                $primeiro_a, $primeiro_b,
                $primeiro_b, $primeiro_a,
                $segundo_a, $segundo_b,
                $segundo_b, $segundo_a
            ]);
            $total_incorreta = $stmt_verificar_incorreta ? (int)$stmt_verificar_incorreta->fetch()['total'] : 0;
            
            if ($total_incorreta > 0) {
                // Atualizar essas partidas para ter a série correta (apenas 'Ouro', 'Prata' ou 'Bronze')
                $sql_update_serie_verificacao = "UPDATE partidas_2fase_eliminatorias 
                                                SET serie = ?
                                                WHERE torneio_id = ? 
                                                AND tipo_eliminatoria = 'Semi-Final' 
                                                AND serie IN (?, ?)
                                                AND ((time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?)
                                                     OR (time1_id = ? AND time2_id = ?) OR (time1_id = ? AND time2_id = ?))";
                executeQuery($pdo, $sql_update_serie_verificacao, [
                    $serie,  // Atualizar para 'Ouro', 'Prata' ou 'Bronze' (sem A ou B)
                    $torneio_id,
                    $serie_a_old, $serie_b_old,
                    $primeiro_a, $primeiro_b,
                    $primeiro_b, $primeiro_a,
                    $segundo_a, $segundo_b,
                    $segundo_b, $segundo_a
                ]);
                addDebug("Atualizadas $total_incorreta partidas com série incorreta ($serie_a_old/$serie_b_old) para série $serie durante verificação");
                $total_verificado = $total_incorreta;
            }
        }
    
    if ($total_verificado === 0 && $partidas_inseridas > 0) {
        // Se inseriu mas não encontrou, pode ser problema de transação ou tipo_fase
        addDebug("AVISO - Partidas inseridas ($partidas_inseridas) mas não encontradas na verificação. Continuando mesmo assim.");
        // Não lançar exceção, apenas logar o aviso
    } else if ($total_verificado === 0) {
        // Verificar se há partidas com outros critérios
        $sql_verificar_alternativa = "SELECT id, time1_id, time2_id, serie, tipo_eliminatoria 
                                     FROM partidas_2fase_eliminatorias 
                                     WHERE torneio_id = ? 
                                     AND tipo_eliminatoria = 'Semi-Final'";
        $stmt_verificar_alt = executeQuery($pdo, $sql_verificar_alternativa, [$torneio_id]);
        $partidas_alternativas = $stmt_verificar_alt ? $stmt_verificar_alt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $msg_erro = "Nenhuma partida foi criada após a verificação.\n\n";
        $msg_erro .= "Detalhes:\n";
        $msg_erro .= "- Partidas inseridas no loop: $partidas_inseridas\n";
        $msg_erro .= "- Partidas encontradas na verificação: $total_verificado\n";
        
        if (count($partidas_alternativas) > 0) {
            $msg_erro .= "- Partidas encontradas com critérios alternativos: " . count($partidas_alternativas) . "\n";
            foreach ($partidas_alternativas as $p) {
                $msg_erro .= "  * ID={$p['id']}, Time1={$p['time1_id']}, Time2={$p['time2_id']}, Série={$p['serie']}\n";
            }
            $msg_erro .= "\n⚠ As partidas podem ter sido criadas com uma série diferente. Verifique se a série está correta.\n";
        }
        
        $msg_erro .= "\nPossíveis causas:\n";
        $msg_erro .= "1. Problema na transação do banco de dados\n";
        $msg_erro .= "2. A série das partidas não corresponde ao filtro de verificação\n";
        $msg_erro .= "3. Erro silencioso na inserção\n";
        $msg_erro .= "\nVerifique os logs do servidor para mais detalhes.";
        
        throw new Exception($msg_erro);
    }
    
    // Preparar informações dos times selecionados
    // Garantir que as variáveis estão definidas
    if (!isset($classificacao_a) || !is_array($classificacao_a) || count($classificacao_a) < 2) {
        throw new Exception("Erro: classificação do grupo $serie A não encontrada ou incompleta.");
    }
    if (!isset($classificacao_b) || !is_array($classificacao_b) || count($classificacao_b) < 2) {
        throw new Exception("Erro: classificação do grupo $serie B não encontrada ou incompleta.");
    }
    
    $times_selecionados = [
        strtolower($serie) . '_a' => [
            '1º' => [
                'id' => $primeiro_a,
                'nome' => $classificacao_a[0]['nome'] ?? $classificacao_a[0]['time_nome'] ?? 'N/A',
                'pontos_total' => $classificacao_a[0]['pontos_total'] ?? 0,
                'average' => $classificacao_a[0]['average'] ?? 0
            ],
            '2º' => [
                'id' => $segundo_a,
                'nome' => $classificacao_a[1]['nome'] ?? $classificacao_a[1]['time_nome'] ?? 'N/A',
                'pontos_total' => $classificacao_a[1]['pontos_total'] ?? 0,
                'average' => $classificacao_a[1]['average'] ?? 0
            ]
        ],
        strtolower($serie) . '_b' => [
            '1º' => [
                'id' => $primeiro_b,
                'nome' => $classificacao_b[0]['nome'] ?? $classificacao_b[0]['time_nome'] ?? 'N/A',
                'pontos_total' => $classificacao_b[0]['pontos_total'] ?? 0,
                'average' => $classificacao_b[0]['average'] ?? 0
            ],
            '2º' => [
                'id' => $segundo_b,
                'nome' => $classificacao_b[1]['nome'] ?? $classificacao_b[1]['time_nome'] ?? 'N/A',
                'pontos_total' => $classificacao_b[1]['pontos_total'] ?? 0,
                'average' => $classificacao_b[1]['average'] ?? 0
            ]
        ]
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => "Semi-finais da série $serie geradas com sucesso! Total de " . $partidas_inseridas . " partidas eliminatórias criadas. Os 2 vencedores irão para a final.",
        'total_partidas' => $partidas_inseridas,
        'grupo_id' => $grupo_chaves_id,
        'grupo_nome' => "2ª Fase - $serie - Chaves",
        'debug' => $debug_messages
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    addDebug("Erro ao gerar semi-finais da série $serie: " . $e->getMessage());
    addDebug("Stack trace: " . $e->getTraceAsString());
    error_log("EXCEÇÃO em gerar_semifinais_ouro.php: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false, 
        'message' => "Erro ao gerar semi-finais da série $serie: " . $e->getMessage(),
        'debug' => $debug_messages,
        'error_details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ]);
} catch (Error $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    addDebug("Erro fatal ao gerar semi-finais da série $serie: " . $e->getMessage());
    error_log("ERRO FATAL em gerar_semifinais_ouro.php: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro fatal ao gerar semi-finais: ' . $e->getMessage(),
        'debug' => $debug_messages,
        'error_details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>

