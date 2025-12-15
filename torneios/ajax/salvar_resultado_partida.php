<?php
// Função para retornar JSON de erro de forma segura
function returnJsonError($message) {
    // Limpar qualquer output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Função para retornar JSON de sucesso de forma segura
function returnJsonSuccess($data) {
    // Limpar qualquer output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit();
}

// Iniciar buffer de saída ANTES de qualquer coisa
if (!ob_get_level()) {
    ob_start();
}

// Desabilitar exibição de erros para evitar que apareçam no JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Registrar handler de erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        returnJsonError('Erro fatal: ' . $error['message']);
    }
});

// Iniciar sessão silenciosamente
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Incluir arquivos com supressão de warnings
try {
    @require_once '../../includes/db_connect.php';
    @require_once '../../includes/functions.php';
} catch (Exception $e) {
    returnJsonError('Erro ao carregar arquivos: ' . $e->getMessage());
} catch (Error $e) {
    returnJsonError('Erro fatal ao carregar arquivos: ' . $e->getMessage());
}

// Limpar qualquer output que possa ter sido gerado pelos includes
if (ob_get_length() > 0) {
    ob_clean();
}

// Garantir que o header JSON seja enviado antes de qualquer saída
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!isLoggedIn()) {
    returnJsonError('Você precisa estar logado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonError('Método não permitido');
}

$partida_id = (int)($_POST['partida_id'] ?? 0);
$pontos_time1 = (int)($_POST['pontos_time1'] ?? 0);
$pontos_time2 = (int)($_POST['pontos_time2'] ?? 0);
$status = $_POST['status'] ?? 'Agendada';

if ($partida_id <= 0) {
    returnJsonError('Partida inválida.');
}

if (!in_array($status, ['Agendada', 'Em Andamento', 'Finalizada'])) {
    returnJsonError('Status inválido.');
}

// Buscar partida - verificar primeiro nas novas tabelas da 2ª fase
$partida = null;
$tabela_partida = null;

// Verificar na tabela partidas_2fase_torneio
$sql_2fase_torneio = "SELECT *, 'partidas_2fase_torneio' AS tabela_origem, '2ª Fase' AS fase FROM partidas_2fase_torneio WHERE id = ?";
$stmt_2fase_torneio = executeQuery($pdo, $sql_2fase_torneio, [$partida_id]);
$partida_2fase_torneio = $stmt_2fase_torneio ? $stmt_2fase_torneio->fetch() : false;

if ($partida_2fase_torneio) {
    $partida = $partida_2fase_torneio;
    $tabela_partida = 'partidas_2fase_torneio';
} else {
    // Verificar na tabela partidas_2fase_eliminatorias
    $sql_2fase_elim = "SELECT *, 'partidas_2fase_eliminatorias' AS tabela_origem, '2ª Fase' AS fase FROM partidas_2fase_eliminatorias WHERE id = ?";
    $stmt_2fase_elim = executeQuery($pdo, $sql_2fase_elim, [$partida_id]);
    $partida_2fase_elim = $stmt_2fase_elim ? $stmt_2fase_elim->fetch() : false;
    
    if ($partida_2fase_elim) {
        $partida = $partida_2fase_elim;
        $tabela_partida = 'partidas_2fase_eliminatorias';
    } else {
        // Buscar na tabela antiga torneio_partidas (1ª fase - fase = 'Grupos' ou NULL ou '')
        $sql = "SELECT *, 'torneio_partidas' AS tabela_origem FROM torneio_partidas WHERE id = ?";
        $stmt = executeQuery($pdo, $sql, [$partida_id]);
        $partida = $stmt ? $stmt->fetch() : false;
        
        if ($partida) {
            $tabela_partida = 'torneio_partidas';
            // Garantir que a fase está definida (pode ser 'Grupos', NULL ou '')
            if (empty($partida['fase']) || $partida['fase'] === '') {
                $partida['fase'] = 'Grupos'; // Padronizar para 'Grupos' se estiver vazio
            }
        }
    }
}

if (!$partida) {
    returnJsonError('Partida não encontrada.');
}

// Verificar permissão
$sql_torneio = "SELECT t.*, g.administrador_id 
                FROM torneios t
                LEFT JOIN grupos g ON g.id = t.grupo_id
                WHERE t.id = ?";
$stmt_torneio = executeQuery($pdo, $sql_torneio, [$partida['torneio_id']]);
$torneio = $stmt_torneio ? $stmt_torneio->fetch() : false;

if (!$torneio) {
    returnJsonError('Torneio não encontrado.');
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    returnJsonError('Sem permissão.');
}

$pdo->beginTransaction();

try {
    // Determinar vencedor se a partida foi finalizada
    $vencedor_id = null;
    if ($status === 'Finalizada') {
        if ($pontos_time1 > $pontos_time2) {
            $vencedor_id = $partida['time1_id'];
        } elseif ($pontos_time2 > $pontos_time1) {
            $vencedor_id = $partida['time2_id'];
        }
        // Se empate, vencedor_id fica null
    }
    
    // Atualizar partida na tabela correta
    if ($tabela_partida === 'partidas_2fase_torneio' || $tabela_partida === 'partidas_2fase_eliminatorias') {
        $sql_update = "UPDATE {$tabela_partida} 
                       SET pontos_time1 = ?, pontos_time2 = ?, vencedor_id = ?, status = ?
                       WHERE id = ?";
    } else {
        $sql_update = "UPDATE torneio_partidas 
                       SET pontos_time1 = ?, pontos_time2 = ?, vencedor_id = ?, status = ?
                       WHERE id = ?";
    }
    
    // Log para debug
    error_log("DEBUG: Atualizando partida ID $partida_id na tabela $tabela_partida");
    error_log("DEBUG: SQL: $sql_update");
    error_log("DEBUG: Parâmetros: pontos_time1=$pontos_time1, pontos_time2=$pontos_time2, vencedor_id=" . ($vencedor_id ?? 'NULL') . ", status=$status");
    
    $stmt_update = executeQuery($pdo, $sql_update, [$pontos_time1, $pontos_time2, $vencedor_id, $status, $partida_id]);
    
    // Verificar se a atualização foi bem-sucedida
    if ($stmt_update === false) {
        $error_info = $pdo->errorInfo();
        $error_msg = $error_info[2] ?? 'Erro desconhecido';
        error_log("ERRO ao atualizar partida ID $partida_id na tabela $tabela_partida: $error_msg");
        error_log("ERRO completo: " . json_encode($error_info));
        throw new Exception("Erro ao atualizar partida: $error_msg");
    }
    
    // Verificar se alguma linha foi afetada (apenas se for um objeto PDOStatement)
    $rows_affected = 0;
    if ($stmt_update && is_object($stmt_update) && $stmt_update instanceof PDOStatement) {
        $rows_affected = $stmt_update->rowCount();
        error_log("DEBUG: Linhas afetadas: $rows_affected");
        if ($rows_affected === 0) {
            error_log("AVISO: Nenhuma linha foi atualizada para partida ID $partida_id na tabela $tabela_partida. Verificar se a partida existe.");
            // Não lançar exceção aqui, apenas logar, pois pode ser que a partida já esteja atualizada
        }
    } else {
        error_log("DEBUG: executeQuery retornou: " . gettype($stmt_update) . " (não é PDOStatement)");
    }
    
    // Buscar modalidade do torneio (necessário para várias verificações)
    $sql_torneio = "SELECT modalidade FROM torneios WHERE id = ?";
    $stmt_torneio = executeQuery($pdo, $sql_torneio, [$partida['torneio_id']]);
    $modalidade_torneio = $stmt_torneio ? $stmt_torneio->fetch()['modalidade'] : null;
    
    // Variáveis para criação automática da final (será processado após o commit)
    $final_criada_auto = false;
    $mensagem_final_auto = '';
    $debug_messages_final = [];
    $eh_semi_final_ouro = false;
    
    // Verificar se é uma semi-final de qualquer série (Ouro, Prata, Bronze) - será processado após o commit
    if ($status === 'Finalizada' && $tabela_partida === 'partidas_2fase_eliminatorias') {
        // Verificar se é uma semi-final de qualquer série
        $sql_check_semi = "SELECT tipo_eliminatoria, serie FROM partidas_2fase_eliminatorias WHERE id = ?";
        $stmt_check_semi = executeQuery($pdo, $sql_check_semi, [$partida_id]);
        $info_semi = $stmt_check_semi ? $stmt_check_semi->fetch() : null;
        
        // Verificar se é uma semi-final de qualquer série (Ouro, Prata, Bronze)
        if ($info_semi && $info_semi['tipo_eliminatoria'] === 'Semi-Final') {
            $serie_raw = trim($info_semi['serie'] ?? '');
            // Considerar semi-final de qualquer série ou com serie vazia/NULL (assumir Ouro como padrão)
            if (empty($serie_raw) || 
                stripos($serie_raw, 'Ouro') !== false || 
                stripos($serie_raw, 'Prata') !== false || 
                stripos($serie_raw, 'Bronze') !== false) {
                $eh_semi_final_ouro = true; // Mantém o nome da variável por compatibilidade, mas funciona para todas as séries
            }
        }
    }
    
    // Se a partida foi finalizada, atualizar classificação
    // IMPORTANTE: Partidas eliminatórias (semi-final, final) NÃO atualizam classificação
    // Apenas partidas "Todos Contra Todos" (partidas_2fase_torneio) atualizam classificação
    if ($status === 'Finalizada' && $tabela_partida !== 'partidas_2fase_eliminatorias') {
        
        // Verificar se a coluna grupo_id existe na tabela torneio_classificacao
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
        $tem_grupo_id_classificacao = $columnsQuery && $columnsQuery->rowCount() > 0;
        
        // Buscar grupo_id dos times se for torneio_pro
        $grupo_id_time1 = null;
        $grupo_id_time2 = null;
        
        // Se a partida não tem grupo_id, buscar dos times (para torneio_pro)
        if (empty($partida['grupo_id']) && $modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao) {
            // Buscar grupo_id dos times (apenas grupos da 1ª fase, não da 2ª fase)
            $sql_grupo_time1 = "SELECT grupo_id FROM torneio_grupo_times WHERE time_id = ? AND grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%') LIMIT 1";
            $stmt_grupo_time1 = executeQuery($pdo, $sql_grupo_time1, [$partida['time1_id'], $partida['torneio_id']]);
            $grupo_time1 = $stmt_grupo_time1 ? $stmt_grupo_time1->fetch() : false;
            $grupo_id_time1 = $grupo_time1 ? $grupo_time1['grupo_id'] : null;
            
            $sql_grupo_time2 = "SELECT grupo_id FROM torneio_grupo_times WHERE time_id = ? AND grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%') LIMIT 1";
            $stmt_grupo_time2 = executeQuery($pdo, $sql_grupo_time2, [$partida['time2_id'], $partida['torneio_id']]);
            $grupo_time2 = $stmt_grupo_time2 ? $stmt_grupo_time2->fetch() : false;
            $grupo_id_time2 = $grupo_time2 ? $grupo_time2['grupo_id'] : null;
            
            // Se ambos os times estão no mesmo grupo, usar esse grupo_id
            if ($grupo_id_time1 && $grupo_id_time2 && $grupo_id_time1 == $grupo_id_time2) {
                // Atualizar a partida com o grupo_id encontrado
                $sql_update_grupo = "UPDATE torneio_partidas SET grupo_id = ? WHERE id = ?";
                executeQuery($pdo, $sql_update_grupo, [$grupo_id_time1, $partida_id]);
                $partida['grupo_id'] = $grupo_id_time1;
            }
        } elseif ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && !empty($partida['grupo_id'])) {
            $grupo_id_time1 = $partida['grupo_id'];
            $grupo_id_time2 = $partida['grupo_id'];
        }
        
        // Buscar classificação atual dos times
        $sql_class1 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        if ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && $grupo_id_time1) {
            $sql_class1 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
            $stmt_class1 = executeQuery($pdo, $sql_class1, [$partida['torneio_id'], $partida['time1_id'], $grupo_id_time1]);
        } else {
        $stmt_class1 = executeQuery($pdo, $sql_class1, [$partida['torneio_id'], $partida['time1_id']]);
        }
        $class1 = $stmt_class1 ? $stmt_class1->fetch() : false;
        
        $sql_class2 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ?";
        if ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && $grupo_id_time2) {
            $sql_class2 = "SELECT * FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
            $stmt_class2 = executeQuery($pdo, $sql_class2, [$partida['torneio_id'], $partida['time2_id'], $grupo_id_time2]);
        } else {
        $stmt_class2 = executeQuery($pdo, $sql_class2, [$partida['torneio_id'], $partida['time2_id']]);
        }
        $class2 = $stmt_class2 ? $stmt_class2->fetch() : false;
        
        // Se não existir, criar com grupo_id se necessário
        if (!$class1) {
            if ($tem_grupo_id_classificacao && $grupo_id_time1) {
                $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                              VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time1_id'], $grupo_id_time1]);
            } else {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time1_id']]);
            }
            $class1 = ['vitorias' => 0, 'derrotas' => 0, 'empates' => 0, 'pontos_pro' => 0, 'pontos_contra' => 0];
        }
        
        if (!$class2) {
            if ($tem_grupo_id_classificacao && $grupo_id_time2) {
                $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                              VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time2_id'], $grupo_id_time2]);
            } else {
            $sql_insert = "INSERT INTO torneio_classificacao (torneio_id, time_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                          VALUES (?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
            executeQuery($pdo, $sql_insert, [$partida['torneio_id'], $partida['time2_id']]);
            }
            $class2 = ['vitorias' => 0, 'derrotas' => 0, 'empates' => 0, 'pontos_pro' => 0, 'pontos_contra' => 0];
        }
        
        // Recalcular todas as estatísticas do torneio (incluindo jogos eliminatórios)
        // Se for torneio_pro e a partida tem grupo_id (ou foi encontrado), recalcular apenas para times do mesmo grupo
        $grupo_id_para_recalc = !empty($partida['grupo_id']) ? $partida['grupo_id'] : ($grupo_id_time1 ?? null);
        $is_2fase_partida = !empty($partida['fase']) && $partida['fase'] === '2ª Fase';
        
        if ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && !empty($grupo_id_para_recalc)) {
            // Verificar se o grupo é da 2ª fase
            $sql_check_grupo_tipo = "SELECT id, nome FROM torneio_grupos WHERE id = ?";
            $stmt_check_grupo_tipo = executeQuery($pdo, $sql_check_grupo_tipo, [$grupo_id_para_recalc]);
            $grupo_info = $stmt_check_grupo_tipo ? $stmt_check_grupo_tipo->fetch() : null;
            $is_grupo_2fase = $grupo_info && strpos($grupo_info['nome'], '2ª Fase') !== false;
            
            error_log("DEBUG: Recalculando classificação para grupo_id: $grupo_id_para_recalc, torneio_id: {$partida['torneio_id']}, é 2ª fase: " . ($is_grupo_2fase ? 'SIM' : 'NÃO'));
            
            if ($grupo_id_para_recalc) {
                if ($is_grupo_2fase || $is_2fase_partida) {
                    // Recalcular APENAS para 2ª fase - considerar apenas jogos da 2ª fase
                    // CORRIGIDO: Buscar times do grupo primeiro, não depender de registros existentes em torneio_classificacao
                    $sql_recalc = "SELECT 
                        tt.id as time_id,
                        ? as grupo_id,
                        COUNT(CASE WHEN jogo.vencedor_id = tt.id THEN 1 END) as vitorias,
                        COUNT(CASE WHEN jogo.vencedor_id IS NOT NULL AND jogo.vencedor_id != tt.id AND (jogo.time1_id = tt.id OR jogo.time2_id = tt.id) THEN 1 END) as derrotas,
                        COUNT(CASE WHEN jogo.vencedor_id IS NULL AND jogo.status = 'Finalizada' AND (jogo.time1_id = tt.id OR jogo.time2_id = tt.id) THEN 1 END) as empates,
                        COALESCE(SUM(CASE WHEN jogo.time1_id = tt.id THEN jogo.pontos_time1 ELSE 0 END), 0) + 
                        COALESCE(SUM(CASE WHEN jogo.time2_id = tt.id THEN jogo.pontos_time2 ELSE 0 END), 0) as pontos_pro,
                        COALESCE(SUM(CASE WHEN jogo.time1_id = tt.id THEN jogo.pontos_time2 ELSE 0 END), 0) + 
                        COALESCE(SUM(CASE WHEN jogo.time2_id = tt.id THEN jogo.pontos_time1 ELSE 0 END), 0) as pontos_contra
                    FROM torneio_times tt
                    JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                    LEFT JOIN (
                        SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id, grupo_id
                        FROM partidas_2fase_torneio
                        WHERE status = 'Finalizada' 
                          AND torneio_id = ?
                          AND grupo_id = ?
                    ) jogo ON (jogo.time1_id = tt.id OR jogo.time2_id = tt.id) 
                        AND jogo.torneio_id = tt.torneio_id
                    WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                    GROUP BY tt.id";
                    
                    $stmt_recalc = executeQuery($pdo, $sql_recalc, [$grupo_id_para_recalc, $partida['torneio_id'], $grupo_id_para_recalc, $grupo_id_para_recalc, $partida['torneio_id']]);
                } else {
                    // Recalcular APENAS para 1ª fase - considerar apenas jogos da 1ª fase (Grupos)
                    $sql_recalc = "SELECT 
                        tc.time_id,
                        tc.grupo_id,
                        COUNT(CASE WHEN jogo.vencedor_id = tc.time_id THEN 1 END) as vitorias,
                        COUNT(CASE WHEN jogo.vencedor_id IS NOT NULL AND jogo.vencedor_id != tc.time_id AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as derrotas,
                        COUNT(CASE WHEN jogo.vencedor_id IS NULL AND jogo.status = 'Finalizada' AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as empates,
                        COALESCE(SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END), 0) + 
                        COALESCE(SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END), 0) as pontos_pro,
                        COALESCE(SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END), 0) + 
                        COALESCE(SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END), 0) as pontos_contra
                    FROM torneio_classificacao tc
                    LEFT JOIN (
                        SELECT time1_id, time2_id, vencedor_id, pontos_time1, pontos_time2, status, torneio_id, grupo_id
                        FROM torneio_partidas
                        WHERE status = 'Finalizada' 
                          AND torneio_id = ?
                          AND grupo_id = ?
                          AND (fase = 'Grupos' OR fase IS NULL OR fase = '')
                          AND fase != '2ª Fase'
                    ) jogo ON (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) 
                        AND jogo.torneio_id = tc.torneio_id
                    WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                    GROUP BY tc.time_id, tc.grupo_id";
                    
                    $stmt_recalc = executeQuery($pdo, $sql_recalc, [$partida['torneio_id'], $grupo_id_para_recalc, $partida['torneio_id'], $grupo_id_para_recalc]);
                }
                
                if ($stmt_recalc) {
                    $recalcs_debug = $stmt_recalc->fetchAll();
                    error_log("DEBUG: Resultados do recálculo para grupo $grupo_id_para_recalc: " . json_encode($recalcs_debug));
                    error_log("DEBUG: Total de registros retornados pelo recálculo: " . count($recalcs_debug));
                    // Re-executar a query para usar no loop
                    if ($is_grupo_2fase || $is_2fase_partida) {
                        $stmt_recalc = executeQuery($pdo, $sql_recalc, [$grupo_id_para_recalc, $partida['torneio_id'], $grupo_id_para_recalc, $grupo_id_para_recalc, $partida['torneio_id']]);
                    } else {
                        $stmt_recalc = executeQuery($pdo, $sql_recalc, [$partida['torneio_id'], $grupo_id_para_recalc, $partida['torneio_id'], $grupo_id_para_recalc]);
                    }
                    error_log("DEBUG: Query re-executada. Resultados encontrados: " . ($stmt_recalc ? $stmt_recalc->rowCount() : 0));
                } else {
                    error_log("DEBUG: ERRO na execução da query de recálculo");
                    $error_info = $pdo->errorInfo();
                    error_log("DEBUG: Erro PDO: " . ($error_info[2] ?? 'Desconhecido'));
                }
            } else {
                error_log("DEBUG: grupo_id não encontrado para a partida. Usando recálculo geral.");
                // Se não encontrar grupo_id, usar recálculo geral
                $sql_recalc = "SELECT 
                    tc.time_id,
                    COUNT(CASE WHEN jogo.vencedor_id = tc.time_id THEN 1 END) as vitorias,
                    COUNT(CASE WHEN jogo.vencedor_id IS NOT NULL AND jogo.vencedor_id != tc.time_id AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as derrotas,
                    COUNT(CASE WHEN jogo.vencedor_id IS NULL AND jogo.status = 'Finalizada' AND (jogo.time1_id = tc.time_id OR jogo.time2_id = tc.time_id) THEN 1 END) as empates,
                    COALESCE(SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END), 0) + 
                    COALESCE(SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END), 0) as pontos_pro,
                    COALESCE(SUM(CASE WHEN jogo.time1_id = tc.time_id THEN jogo.pontos_time2 ELSE 0 END), 0) + 
                    COALESCE(SUM(CASE WHEN jogo.time2_id = tc.time_id THEN jogo.pontos_time1 ELSE 0 END), 0) as pontos_contra
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
                
                $stmt_recalc = executeQuery($pdo, $sql_recalc, [$partida['torneio_id']]);
            }
        } else {
            // Recalcular para todos os times (comportamento padrão)
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
        
        $stmt_recalc = executeQuery($pdo, $sql_recalc, [$partida['torneio_id']]);
        }
        
        $recalcs = $stmt_recalc ? $stmt_recalc->fetchAll() : [];
        
        error_log("DEBUG: Total de registros recalculados: " . count($recalcs));
        
        foreach ($recalcs as $recalc) {
            $saldo = (int)$recalc['pontos_pro'] - (int)$recalc['pontos_contra'];
            $average = (int)$recalc['pontos_contra'] > 0 ? (float)$recalc['pontos_pro'] / (float)$recalc['pontos_contra'] : ((int)$recalc['pontos_pro'] > 0 ? 999.99 : 0.00);
            $pontos_total = ((int)$recalc['vitorias'] * 3) + ((int)$recalc['empates'] * 1);
            
            error_log("DEBUG: Atualizando time_id {$recalc['time_id']} - V: {$recalc['vitorias']}, D: {$recalc['derrotas']}, PF: {$recalc['pontos_pro']}, PS: {$recalc['pontos_contra']}");
            
            // Se for torneio_pro e tiver grupo_id na partida, atualizar com filtro de grupo_id
            // IMPORTANTE: Verificar se é partida da 2ª fase para não afetar a 1ª fase
            $is_2fase = !empty($partida['fase']) && $partida['fase'] === '2ª Fase';
            $grupo_2fase = null;
            if ($tem_grupo_id_classificacao && !empty($grupo_id_para_recalc)) {
                // Verificar se o grupo_id pertence à 2ª fase
                $sql_check_grupo_2fase = "SELECT id, nome FROM torneio_grupos WHERE id = ? AND nome LIKE '2ª Fase%'";
                $stmt_check_grupo_2fase = executeQuery($pdo, $sql_check_grupo_2fase, [$grupo_id_para_recalc]);
                $grupo_2fase = $stmt_check_grupo_2fase ? $stmt_check_grupo_2fase->fetch() : null;
                
                // Debug: verificar se identificou corretamente
                if ($is_2fase) {
                    error_log("DEBUG: Partida da 2ª fase detectada. grupo_id_para_recalc: $grupo_id_para_recalc, grupo_2fase encontrado: " . ($grupo_2fase ? 'SIM (' . $grupo_2fase['nome'] . ')' : 'NÃO'));
                }
            }
            
            // Atualizar classificação se for 2ª fase (verificar tanto pela fase da partida quanto pelo nome do grupo)
            $deve_atualizar_2fase = $modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && !empty($grupo_id_para_recalc) && ($is_2fase || $grupo_2fase);
            
            error_log("DEBUG: Verificando atualização 2ª fase - modalidade=$modalidade_torneio, tem_grupo_id=$tem_grupo_id_classificacao, grupo_id_para_recalc=$grupo_id_para_recalc, is_2fase=" . ($is_2fase ? 'SIM' : 'NÃO') . ", grupo_2fase=" . ($grupo_2fase ? 'SIM (' . ($grupo_2fase['nome'] ?? 'N/A') . ')' : 'NÃO') . ", deve_atualizar=" . ($deve_atualizar_2fase ? 'SIM' : 'NÃO'));
            
            if ($deve_atualizar_2fase) {
                // Atualizar APENAS classificação da 2ª fase (não afetar 1ª fase)
                $grupo_id_update = !empty($recalc['grupo_id']) ? $recalc['grupo_id'] : $grupo_id_para_recalc;
                
                // Verificar qual série pertence este grupo (Ouro A/B, Prata A/B, Bronze A/B)
                $sql_grupo_info = "SELECT nome FROM torneio_grupos WHERE id = ?";
                $stmt_grupo_info = executeQuery($pdo, $sql_grupo_info, [$grupo_id_update]);
                $grupo_info = $stmt_grupo_info ? $stmt_grupo_info->fetch() : null;
                
                $serie_nome = null;
                if ($grupo_info) {
                    $nome_grupo = $grupo_info['nome'];
                    // Identificar série completa (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)
                    if (preg_match('/Ouro\s+A/i', $nome_grupo)) {
                        $serie_nome = 'Ouro A';
                    } elseif (preg_match('/Ouro\s+B/i', $nome_grupo)) {
                        $serie_nome = 'Ouro B';
                    } elseif (preg_match('/Prata\s+A/i', $nome_grupo)) {
                        $serie_nome = 'Prata A';
                    } elseif (preg_match('/Prata\s+B/i', $nome_grupo)) {
                        $serie_nome = 'Prata B';
                    } elseif (preg_match('/Bronze\s+A/i', $nome_grupo)) {
                        $serie_nome = 'Bronze A';
                    } elseif (preg_match('/Bronze\s+B/i', $nome_grupo)) {
                        $serie_nome = 'Bronze B';
                    } elseif (strpos($nome_grupo, 'Ouro') !== false) {
                        $serie_nome = 'Ouro'; // Fallback genérico
                    } elseif (strpos($nome_grupo, 'Prata') !== false) {
                        $serie_nome = 'Prata'; // Fallback genérico
                    } elseif (strpos($nome_grupo, 'Bronze') !== false) {
                        $serie_nome = 'Bronze'; // Fallback genérico
                    }
                }
                
                // Verificar se o registro existe antes de atualizar
                $sql_check_exists = "SELECT id FROM torneio_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                $stmt_check_exists = executeQuery($pdo, $sql_check_exists, [$partida['torneio_id'], $recalc['time_id'], $grupo_id_update]);
                $exists = $stmt_check_exists ? $stmt_check_exists->fetch() : null;
                
                // Verificar se é grupo da 2ª fase para salvar na nova tabela
                $eh_grupo_2fase = $grupo_2fase !== null;
                
                if ($eh_grupo_2fase) {
                    // Salvar na nova tabela partidas_2fase_classificacao
                    $sql_check_exists_2fase = "SELECT id FROM partidas_2fase_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $stmt_check_exists_2fase = executeQuery($pdo, $sql_check_exists_2fase, [$partida['torneio_id'], $recalc['time_id'], $grupo_id_update]);
                    $exists_2fase = $stmt_check_exists_2fase ? $stmt_check_exists_2fase->fetch() : null;
                    
                    if ($exists_2fase) {
                        // Atualizar classificação na nova tabela
                        $sql_update_class_2fase = "UPDATE partidas_2fase_classificacao 
                                                  SET vitorias = ?, derrotas = ?, empates = ?, 
                                                      pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                                      average = ?, pontos_total = ?
                                                  WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                        $result_update_2fase = executeQuery($pdo, $sql_update_class_2fase, [
                            (int)$recalc['vitorias'],
                            (int)$recalc['derrotas'],
                            (int)$recalc['empates'],
                            (int)$recalc['pontos_pro'],
                            (int)$recalc['pontos_contra'],
                            $saldo,
                            $average,
                            $pontos_total,
                            $partida['torneio_id'],
                            $recalc['time_id'],
                            $grupo_id_update
                        ]);
                        
                        if ($result_update_2fase === false) {
                            $error_info = $pdo->errorInfo();
                            error_log("DEBUG: ERRO ao atualizar classificação 2ª fase do time {$recalc['time_id']}: " . ($error_info[2] ?? 'Desconhecido'));
                        } else {
                            error_log("DEBUG: Classificação 2ª fase atualizada com sucesso para time {$recalc['time_id']} no grupo $grupo_id_update");
                        }
                    } else {
                        // Inserir registro na nova tabela
                        $sql_insert_class_2fase = "INSERT INTO partidas_2fase_classificacao 
                                                  (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $result_insert_2fase = executeQuery($pdo, $sql_insert_class_2fase, [
                            $partida['torneio_id'],
                            $recalc['time_id'],
                            $grupo_id_update,
                            (int)$recalc['vitorias'],
                            (int)$recalc['derrotas'],
                            (int)$recalc['empates'],
                            (int)$recalc['pontos_pro'],
                            (int)$recalc['pontos_contra'],
                            $saldo,
                            $average,
                            $pontos_total
                        ]);
                        
                        if ($result_insert_2fase === false) {
                            $error_info = $pdo->errorInfo();
                            error_log("DEBUG: ERRO ao inserir classificação 2ª fase do time {$recalc['time_id']}: " . ($error_info[2] ?? 'Desconhecido'));
                        } else {
                            error_log("DEBUG: Classificação 2ª fase inserida com sucesso para time {$recalc['time_id']} no grupo $grupo_id_update");
                        }
                    }
                } else {
                    // Salvar na tabela antiga torneio_classificacao (1ª fase)
                    if ($exists) {
                        // Atualizar classificação do grupo original (1ª fase)
                        $sql_update_class = "UPDATE torneio_classificacao 
                                            SET vitorias = ?, derrotas = ?, empates = ?, 
                                                pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                                average = ?, pontos_total = ?
                                            WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                        $result_update = executeQuery($pdo, $sql_update_class, [
                            (int)$recalc['vitorias'],
                            (int)$recalc['derrotas'],
                            (int)$recalc['empates'],
                            (int)$recalc['pontos_pro'],
                            (int)$recalc['pontos_contra'],
                            $saldo,
                            $average,
                            $pontos_total,
                            $partida['torneio_id'],
                            $recalc['time_id'],
                            $grupo_id_update
                        ]);
                        
                        if ($result_update === false) {
                            $error_info = $pdo->errorInfo();
                            error_log("DEBUG: ERRO ao atualizar classificação do time {$recalc['time_id']}: " . ($error_info[2] ?? 'Desconhecido'));
                        } else {
                            error_log("DEBUG: Classificação atualizada com sucesso para time {$recalc['time_id']} no grupo $grupo_id_update");
                        }
                    } else {
                        // Inserir registro se não existir
                        $sql_insert_class = "INSERT INTO torneio_classificacao 
                                            (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $result_insert = executeQuery($pdo, $sql_insert_class, [
                            $partida['torneio_id'],
                            $recalc['time_id'],
                            $grupo_id_update,
                            (int)$recalc['vitorias'],
                            (int)$recalc['derrotas'],
                            (int)$recalc['empates'],
                            (int)$recalc['pontos_pro'],
                            (int)$recalc['pontos_contra'],
                            $saldo,
                            $average,
                            $pontos_total
                        ]);
                        
                        if ($result_insert === false) {
                            $error_info = $pdo->errorInfo();
                            error_log("DEBUG: ERRO ao inserir classificação do time {$recalc['time_id']}: " . ($error_info[2] ?? 'Desconhecido'));
                        } else {
                            error_log("DEBUG: Classificação inserida com sucesso para time {$recalc['time_id']} no grupo $grupo_id_update");
                        }
                    }
                }
                
                // ATUALIZAR NOVA TABELA torneio_classificacao_2fase
                if ($serie_nome && in_array($serie_nome, ['Ouro A', 'Ouro B', 'Prata A', 'Prata B', 'Bronze A', 'Bronze B'])) {
                    error_log("DEBUG: Tentando atualizar torneio_classificacao_2fase - Série: $serie_nome, Time: {$recalc['time_id']}");
                    
                    // Verificar se a tabela existe
                    $sql_check_table_2fase = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
                    $stmt_check_table_2fase = executeQuery($pdo, $sql_check_table_2fase, []);
                    $table_exists_2fase = $stmt_check_table_2fase ? $stmt_check_table_2fase->fetch() : null;
                    
                    if ($table_exists_2fase) {
                        error_log("DEBUG: Tabela torneio_classificacao_2fase existe! Atualizando série: $serie_nome");
                        
                        // Verificar se já existe registro
                        $sql_check_2fase = "SELECT id FROM torneio_classificacao_2fase WHERE torneio_id = ? AND serie = ? AND time_id = ?";
                        $stmt_check_2fase = executeQuery($pdo, $sql_check_2fase, [$partida['torneio_id'], $serie_nome, $recalc['time_id']]);
                        $exists_2fase = $stmt_check_2fase ? $stmt_check_2fase->fetch() : null;
                        
                        error_log("DEBUG: Verificando registro - torneio_id={$partida['torneio_id']}, serie=$serie_nome, time_id={$recalc['time_id']}, existe=" . ($exists_2fase ? 'SIM' : 'NÃO'));
                        
                        if ($exists_2fase) {
                            // Atualizar
                            $sql_update_2fase = "UPDATE torneio_classificacao_2fase 
                                                SET vitorias = ?, derrotas = ?, empates = ?, 
                                                    pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                                    average = ?, pontos_total = ?
                                                WHERE torneio_id = ? AND serie = ? AND time_id = ?";
                            $result_update = executeQuery($pdo, $sql_update_2fase, [
                                (int)$recalc['vitorias'],
                                (int)$recalc['derrotas'],
                                (int)$recalc['empates'],
                                (int)$recalc['pontos_pro'],
                                (int)$recalc['pontos_contra'],
                                $saldo,
                                $average,
                                $pontos_total,
                                $partida['torneio_id'],
                                $serie_nome,
                                $recalc['time_id']
                            ]);
                            
                            if ($result_update === false) {
                                $error_info = $pdo->errorInfo();
                                error_log("DEBUG: ERRO ao atualizar torneio_classificacao_2fase: " . ($error_info[2] ?? 'Desconhecido'));
                            } else {
                                error_log("DEBUG: ✓ Atualizada classificação 2ª fase - Série: $serie_nome, Time: {$recalc['time_id']}, V:{$recalc['vitorias']}, D:{$recalc['derrotas']}, Pts:$pontos_total");
                            }
                        } else {
                            // Inserir
                            $sql_insert_2fase = "INSERT INTO torneio_classificacao_2fase 
                                                (torneio_id, serie, time_id, vitorias, derrotas, empates, 
                                                 pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $result_insert = executeQuery($pdo, $sql_insert_2fase, [
                                $partida['torneio_id'],
                                $serie_nome,
                                $recalc['time_id'],
                                (int)$recalc['vitorias'],
                                (int)$recalc['derrotas'],
                                (int)$recalc['empates'],
                                (int)$recalc['pontos_pro'],
                                (int)$recalc['pontos_contra'],
                                $saldo,
                                $average,
                                $pontos_total
                            ]);
                            
                            if ($result_insert === false) {
                                $error_info = $pdo->errorInfo();
                                error_log("DEBUG: ERRO ao inserir torneio_classificacao_2fase: " . ($error_info[2] ?? 'Desconhecido'));
                            } else {
                                error_log("DEBUG: ✓ Inserida classificação 2ª fase - Série: $serie_nome, Time: {$recalc['time_id']}, V:{$recalc['vitorias']}, D:{$recalc['derrotas']}, Pts:$pontos_total");
                            }
                        }
                    } else {
                        error_log("DEBUG: ⚠ Tabela torneio_classificacao_2fase NÃO existe! Execute o script SQL primeiro.");
                    }
                }
                
                // Se identificou a série, também atualizar o grupo de classificação da série (Ouro, Prata, Bronze)
                if ($serie_nome) {
                    // Buscar grupo de classificação da série
                    $sql_grupo_class_serie = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
                    $stmt_grupo_class_serie = executeQuery($pdo, $sql_grupo_class_serie, [$partida['torneio_id'], "2ª Fase - $serie_nome - Classificação"]);
                    $grupo_class_serie = $stmt_grupo_class_serie ? $stmt_grupo_class_serie->fetch() : null;
                    
                    if ($grupo_class_serie) {
                        $grupo_class_serie_id = (int)$grupo_class_serie['id'];
                        
                        // Verificar se existe classificação para este time no grupo de classificação da série
                        $sql_class_serie_atual = "SELECT id FROM torneio_classificacao 
                                                  WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                        $stmt_class_serie_atual = executeQuery($pdo, $sql_class_serie_atual, [$partida['torneio_id'], $recalc['time_id'], $grupo_class_serie_id]);
                        $class_serie_atual = $stmt_class_serie_atual ? $stmt_class_serie_atual->fetch() : null;
                        
                        // Se não existe, criar
                        if (!$class_serie_atual) {
                            $sql_insert_class_serie = "INSERT INTO torneio_classificacao 
                                                      (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                                       pontos_pro, pontos_contra, saldo_pontos, average, pontos_total)
                                                      VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0)";
                            executeQuery($pdo, $sql_insert_class_serie, [$partida['torneio_id'], $recalc['time_id'], $grupo_class_serie_id]);
                        }
                        
                        // Recalcular estatísticas agregadas de todos os jogos da série para este time
                        // Buscar grupos A e B da série (ex: "2ª Fase - Ouro A", "2ª Fase - Ouro B")
                        $sql_grupos_serie = "SELECT id FROM torneio_grupos 
                                            WHERE torneio_id = ? 
                                            AND (nome = ? OR nome = ?)";
                        $stmt_grupos_serie = executeQuery($pdo, $sql_grupos_serie, [
                            $partida['torneio_id'], 
                            "2ª Fase - $serie_nome A",
                            "2ª Fase - $serie_nome B"
                        ]);
                        $grupos_serie_ids = $stmt_grupos_serie ? $stmt_grupos_serie->fetchAll(PDO::FETCH_COLUMN) : [];
                        
                        if (!empty($grupos_serie_ids)) {
                            $grupos_serie_ids_str = implode(',', array_map('intval', $grupos_serie_ids));
                            
                            $sql_recalc_serie = "SELECT 
                                                SUM(CASE WHEN (time1_id = ? AND pontos_time1 > pontos_time2) OR (time2_id = ? AND pontos_time2 > pontos_time1) THEN 1 ELSE 0 END) as vitorias,
                                                SUM(CASE WHEN (time1_id = ? AND pontos_time1 < pontos_time2) OR (time2_id = ? AND pontos_time2 < pontos_time1) THEN 1 ELSE 0 END) as derrotas,
                                                SUM(CASE WHEN pontos_time1 = pontos_time2 AND status = 'Finalizada' AND (time1_id = ? OR time2_id = ?) THEN 1 ELSE 0 END) as empates,
                                                SUM(CASE WHEN time1_id = ? THEN pontos_time1 ELSE 0 END) + 
                                                SUM(CASE WHEN time2_id = ? THEN pontos_time2 ELSE 0 END) as pontos_pro,
                                                SUM(CASE WHEN time1_id = ? THEN pontos_time2 ELSE 0 END) + 
                                                SUM(CASE WHEN time2_id = ? THEN pontos_time1 ELSE 0 END) as pontos_contra
                                                FROM torneio_partidas
                                                WHERE torneio_id = ? 
                                                AND status = 'Finalizada'
                                                AND fase = '2ª Fase'
                                                AND grupo_id IN ($grupos_serie_ids_str)
                                                AND (time1_id = ? OR time2_id = ?)
                                                AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
                            $stmt_recalc_serie = executeQuery($pdo, $sql_recalc_serie, [
                                $recalc['time_id'], $recalc['time_id'], 
                                $recalc['time_id'], $recalc['time_id'],
                                $recalc['time_id'], $recalc['time_id'],
                                $recalc['time_id'], $recalc['time_id'],
                                $recalc['time_id'], $recalc['time_id'],
                                $partida['torneio_id'], $recalc['time_id'], $recalc['time_id']
                            ]);
                            $recalc_serie = $stmt_recalc_serie ? $stmt_recalc_serie->fetch() : null;
                            
                            if ($recalc_serie) {
                                $vitorias_serie = (int)($recalc_serie['vitorias'] ?? 0);
                                $derrotas_serie = (int)($recalc_serie['derrotas'] ?? 0);
                                $empates_serie = (int)($recalc_serie['empates'] ?? 0);
                                $pontos_pro_serie = (int)($recalc_serie['pontos_pro'] ?? 0);
                                $pontos_contra_serie = (int)($recalc_serie['pontos_contra'] ?? 0);
                                $saldo_serie = $pontos_pro_serie - $pontos_contra_serie;
                                $average_serie = $pontos_contra_serie > 0 ? ($pontos_pro_serie / $pontos_contra_serie) : ($pontos_pro_serie > 0 ? 999.99 : 0.00);
                                $pontos_total_serie = ($vitorias_serie * 3) + ($empates_serie * 1);
                                
                                // Atualizar classificação da série
                                $sql_update_class_serie = "UPDATE torneio_classificacao 
                                                          SET vitorias = ?, derrotas = ?, empates = ?, 
                                                              pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                                              average = ?, pontos_total = ?
                                                          WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                                executeQuery($pdo, $sql_update_class_serie, [
                                    $vitorias_serie,
                                    $derrotas_serie,
                                    $empates_serie,
                                    $pontos_pro_serie,
                                    $pontos_contra_serie,
                                    $saldo_serie,
                                    $average_serie,
                                    $pontos_total_serie,
                                    $partida['torneio_id'],
                                    $recalc['time_id'],
                                    $grupo_class_serie_id
                                ]);
                            }
                        }
                    }
                }
            } elseif ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && !empty($grupo_id_para_recalc) && !$is_2fase) {
                // Atualizar APENAS classificação da 1ª fase (grupos/chaves)
                $grupo_id_update = !empty($recalc['grupo_id']) ? $recalc['grupo_id'] : $grupo_id_para_recalc;
                // Verificar se o grupo_id NÃO é da 2ª fase
                $sql_check_grupo_1fase = "SELECT id FROM torneio_grupos WHERE id = ? AND nome NOT LIKE '2ª Fase%'";
                $stmt_check_grupo_1fase = executeQuery($pdo, $sql_check_grupo_1fase, [$grupo_id_update]);
                $grupo_1fase = $stmt_check_grupo_1fase ? $stmt_check_grupo_1fase->fetch() : null;
                
                if ($grupo_1fase) {
                    $sql_update_class = "UPDATE torneio_classificacao 
                                        SET vitorias = ?, derrotas = ?, empates = ?, 
                                            pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                            average = ?, pontos_total = ?
                                        WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    $result_update = executeQuery($pdo, $sql_update_class, [
                        (int)$recalc['vitorias'],
                        (int)$recalc['derrotas'],
                        (int)$recalc['empates'],
                        (int)$recalc['pontos_pro'],
                        (int)$recalc['pontos_contra'],
                        $saldo,
                        $average,
                        $pontos_total,
                        $partida['torneio_id'],
                        $recalc['time_id'],
                        $grupo_id_update
                    ]);
                    
                    if ($result_update === false) {
                        $error_info = $pdo->errorInfo();
                        error_log("DEBUG: ERRO ao atualizar classificação da 1ª fase do time {$recalc['time_id']}: " . ($error_info[2] ?? 'Desconhecido'));
                    }
                }
            } else {
                // Comportamento padrão (não torneio_pro ou sem grupo_id)
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
                $partida['torneio_id'],
                $recalc['time_id']
            ]);
            }
        }
        
        // Atualizar posições
        // IMPORTANTE: Separar atualização de posições da 1ª e 2ª fase
        if ($modalidade_torneio === 'torneio_pro' && $tem_grupo_id_classificacao && !empty($grupo_id_para_recalc)) {
            // Verificar se é grupo da 2ª fase
            $sql_check_grupo_tipo_pos = "SELECT id, nome FROM torneio_grupos WHERE id = ?";
            $stmt_check_grupo_tipo_pos = executeQuery($pdo, $sql_check_grupo_tipo_pos, [$grupo_id_para_recalc]);
            $grupo_info_pos = $stmt_check_grupo_tipo_pos ? $stmt_check_grupo_tipo_pos->fetch() : null;
            $is_grupo_2fase_pos = $grupo_info_pos && strpos($grupo_info_pos['nome'], '2ª Fase') !== false;
            
            if ($is_grupo_2fase_pos || $is_2fase_partida) {
                // Atualizar posições APENAS da 2ª fase (grupo individual) - nova tabela partidas_2fase_classificacao
                $sql_pos = "SELECT time_id FROM partidas_2fase_classificacao 
                           WHERE torneio_id = ? AND grupo_id = ?
                           ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
                $stmt_pos = executeQuery($pdo, $sql_pos, [$partida['torneio_id'], $grupo_id_para_recalc]);
                $posicoes = $stmt_pos ? $stmt_pos->fetchAll() : [];
                
                $posicao = 1;
                foreach ($posicoes as $pos) {
                    $sql_update_pos = "UPDATE partidas_2fase_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    executeQuery($pdo, $sql_update_pos, [$posicao++, $partida['torneio_id'], $pos['time_id'], $grupo_id_para_recalc]);
                }
                
                // ATUALIZAR POSIÇÕES NA NOVA TABELA torneio_classificacao_2fase
                if ($grupo_info_pos) {
                    $nome_grupo_pos = $grupo_info_pos['nome'];
                    $serie_nome_pos = null;
                    
                    // Identificar série completa
                    if (preg_match('/Ouro\s+A/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Ouro A';
                    } elseif (preg_match('/Ouro\s+B/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Ouro B';
                    } elseif (preg_match('/Prata\s+A/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Prata A';
                    } elseif (preg_match('/Prata\s+B/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Prata B';
                    } elseif (preg_match('/Bronze\s+A/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Bronze A';
                    } elseif (preg_match('/Bronze\s+B/i', $nome_grupo_pos)) {
                        $serie_nome_pos = 'Bronze B';
                    }
                    
                    if ($serie_nome_pos) {
                        // Verificar se a tabela existe
                        $sql_check_table_2fase_pos = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
                        $stmt_check_table_2fase_pos = executeQuery($pdo, $sql_check_table_2fase_pos, []);
                        $table_exists_2fase_pos = $stmt_check_table_2fase_pos ? $stmt_check_table_2fase_pos->fetch() : null;
                        
                        if ($table_exists_2fase_pos) {
                            // Buscar posições ordenadas da série
                            $sql_pos_2fase = "SELECT time_id FROM torneio_classificacao_2fase 
                                             WHERE torneio_id = ? AND serie = ?
                                             ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
                            $stmt_pos_2fase = executeQuery($pdo, $sql_pos_2fase, [$partida['torneio_id'], $serie_nome_pos]);
                            $posicoes_2fase = $stmt_pos_2fase ? $stmt_pos_2fase->fetchAll() : [];
                            
                            $posicao_2fase = 1;
                            foreach ($posicoes_2fase as $pos_2fase) {
                                $sql_update_pos_2fase = "UPDATE torneio_classificacao_2fase SET posicao = ? WHERE torneio_id = ? AND serie = ? AND time_id = ?";
                                executeQuery($pdo, $sql_update_pos_2fase, [$posicao_2fase++, $partida['torneio_id'], $serie_nome_pos, $pos_2fase['time_id']]);
                            }
                            error_log("DEBUG: Posições atualizadas na tabela torneio_classificacao_2fase para série $serie_nome_pos");
                        }
                    }
                }
                
                // Também atualizar posições do grupo de classificação da série (se for partida todos contra todos)
                if ($is_2fase_partida && $tem_grupo_id_classificacao) {
                    $tipo_fase_partida = $partida['tipo_fase'] ?? '';
                    if (empty($tipo_fase_partida) || $tipo_fase_partida === 'Todos Contra Todos') {
                        // Identificar série
                        $sql_grupo_serie_pos = "SELECT nome FROM torneio_grupos WHERE id = ?";
                        $stmt_grupo_serie_pos = executeQuery($pdo, $sql_grupo_serie_pos, [$grupo_id_para_recalc]);
                        $grupo_serie_pos = $stmt_grupo_serie_pos ? $stmt_grupo_serie_pos->fetch() : null;
                        
                        $serie_nome_pos = null;
                        if ($grupo_serie_pos) {
                            $nome_grupo_pos = $grupo_serie_pos['nome'];
                            if (strpos($nome_grupo_pos, 'Ouro') !== false) {
                                $serie_nome_pos = 'Ouro';
                            } elseif (strpos($nome_grupo_pos, 'Prata') !== false) {
                                $serie_nome_pos = 'Prata';
                            } elseif (strpos($nome_grupo_pos, 'Bronze') !== false) {
                                $serie_nome_pos = 'Bronze';
                            }
                        }
                        
                        if ($serie_nome_pos) {
                            // Buscar grupo de classificação da série
                            $sql_grupo_class_serie_pos = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
                            $stmt_grupo_class_serie_pos = executeQuery($pdo, $sql_grupo_class_serie_pos, [$partida['torneio_id'], "2ª Fase - $serie_nome_pos - Classificação"]);
                            $grupo_class_serie_pos = $stmt_grupo_class_serie_pos ? $stmt_grupo_class_serie_pos->fetch() : null;
                            
                            if ($grupo_class_serie_pos) {
                                $grupo_class_serie_id_pos = (int)$grupo_class_serie_pos['id'];
                                
                                // Atualizar posições do grupo de classificação da série
                                $sql_pos_serie = "SELECT time_id FROM torneio_classificacao 
                                                 WHERE torneio_id = ? AND grupo_id = ?
                                                 ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
                                $stmt_pos_serie = executeQuery($pdo, $sql_pos_serie, [$partida['torneio_id'], $grupo_class_serie_id_pos]);
                                $posicoes_serie = $stmt_pos_serie ? $stmt_pos_serie->fetchAll() : [];
                                
                                $posicao_serie = 1;
                                foreach ($posicoes_serie as $pos_serie) {
                                    $sql_update_pos_serie = "UPDATE torneio_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                                    executeQuery($pdo, $sql_update_pos_serie, [$posicao_serie++, $partida['torneio_id'], $pos_serie['time_id'], $grupo_class_serie_id_pos]);
                                }
                            }
                        }
                    }
                }
            } else {
                // Atualizar posições APENAS da 1ª fase (grupos/chaves)
                $sql_pos = "SELECT time_id FROM torneio_classificacao 
                           WHERE torneio_id = ? AND grupo_id = ?
                           ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
                $stmt_pos = executeQuery($pdo, $sql_pos, [$partida['torneio_id'], $grupo_id_para_recalc]);
                $posicoes = $stmt_pos ? $stmt_pos->fetchAll() : [];
                
                $posicao = 1;
                foreach ($posicoes as $pos) {
                    $sql_update_pos = "UPDATE torneio_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                    executeQuery($pdo, $sql_update_pos, [$posicao++, $partida['torneio_id'], $pos['time_id'], $grupo_id_para_recalc]);
                }
            }
        } else {
            // Atualizar posições gerais (não torneio_pro)
        $sql_pos = "SELECT time_id FROM torneio_classificacao 
                   WHERE torneio_id = ? 
                   ORDER BY pontos_total DESC, vitorias DESC, average DESC, saldo_pontos DESC";
        $stmt_pos = executeQuery($pdo, $sql_pos, [$partida['torneio_id']]);
        $posicoes = $stmt_pos ? $stmt_pos->fetchAll() : [];
        
        $posicao = 1;
        foreach ($posicoes as $pos) {
            $sql_update_pos = "UPDATE torneio_classificacao SET posicao = ? WHERE torneio_id = ? AND time_id = ?";
            executeQuery($pdo, $sql_update_pos, [$posicao++, $partida['torneio_id'], $pos['time_id']]);
            }
        }
    }
    
    // Verificar se é uma semi-final do Ouro e criar a final automaticamente se ambas estiverem finalizadas
    $final_criada = false;
    $mensagem_final = '';
    $debug_messages = [];
    
    if ($status === 'Finalizada' && $modalidade_torneio === 'torneio_pro') {
        // Verificar se a coluna tipo_fase existe
        $columnsQuery_tipo_fase = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
        $tem_tipo_fase = $columnsQuery_tipo_fase && $columnsQuery_tipo_fase->rowCount() > 0;
        
        if ($tem_tipo_fase) {
            // Buscar tipo_fase da partida atual (já atualizada) - usar dados atualizados
            $sql_tipo_fase = "SELECT tipo_fase, grupo_id, status, pontos_time1, pontos_time2 FROM torneio_partidas WHERE id = ?";
            $stmt_tipo_fase = executeQuery($pdo, $sql_tipo_fase, [$partida_id]);
            $info_partida = $stmt_tipo_fase ? $stmt_tipo_fase->fetch() : null;
            
            $tipo_fase_atual = $info_partida['tipo_fase'] ?? '';
            $tipo_fase_lower = strtolower($tipo_fase_atual);
            $eh_semi_final = ($tipo_fase_lower === 'semi-final' || strpos($tipo_fase_lower, 'semi') !== false);
            
            if ($info_partida && $eh_semi_final) {
                $debug_messages[] = "=== VERIFICANDO CRIAÇÃO DE FINAL ===";
                $debug_messages[] = "Partida salva: ID={$partida_id}, tipo_fase={$tipo_fase_atual}, grupo_id={$info_partida['grupo_id']}";
                
                // Verificar se o grupo contém "Ouro" e "Chaves"
                $sql_grupo = "SELECT nome FROM torneio_grupos WHERE id = ?";
                $stmt_grupo = executeQuery($pdo, $sql_grupo, [$info_partida['grupo_id']]);
                $grupo_info = $stmt_grupo ? $stmt_grupo->fetch() : null;
                
                if ($grupo_info && (strpos($grupo_info['nome'], 'Ouro') !== false && strpos($grupo_info['nome'], 'Chaves') !== false)) {
                    $debug_messages[] = "Grupo identificado: {$grupo_info['nome']}";
                    
                    // Verificar se todas as semi-finais estão finalizadas
                    $sql_semifinais = "SELECT id, time1_id, time2_id, pontos_time1, pontos_time2, vencedor_id, status 
                                      FROM torneio_partidas 
                                      WHERE torneio_id = ? AND grupo_id = ? AND tipo_fase = 'Semi-Final'
                                      ORDER BY id";
                    $stmt_semifinais = executeQuery($pdo, $sql_semifinais, [$partida['torneio_id'], $info_partida['grupo_id']]);
                    $semifinais = $stmt_semifinais ? $stmt_semifinais->fetchAll(PDO::FETCH_ASSOC) : [];
                    
                    $debug_messages[] = "Total de semi-finais encontradas: " . count($semifinais);
                    
                    $todas_finalizadas = true;
                    $vencedores = [];
                    $nomes_vencedores = [];
                    
                    foreach ($semifinais as $idx => $semi) {
                        // Buscar nomes dos times
                        $sql_time1_nome = "SELECT nome FROM torneio_times WHERE id = ?";
                        $stmt_time1_nome = executeQuery($pdo, $sql_time1_nome, [$semi['time1_id']]);
                        $time1_nome_semi = $stmt_time1_nome ? $stmt_time1_nome->fetch()['nome'] : 'Time ID ' . $semi['time1_id'];
                        
                        $sql_time2_nome = "SELECT nome FROM torneio_times WHERE id = ?";
                        $stmt_time2_nome = executeQuery($pdo, $sql_time2_nome, [$semi['time2_id']]);
                        $time2_nome_semi = $stmt_time2_nome ? $stmt_time2_nome->fetch()['nome'] : 'Time ID ' . $semi['time2_id'];
                        
                        $debug_messages[] = "Semi-final " . ($idx + 1) . " (ID: {$semi['id']}): {$time1_nome_semi} ({$semi['pontos_time1']} pts) vs {$time2_nome_semi} ({$semi['pontos_time2']} pts) - Status: {$semi['status']}";
                        
                        if ($semi['status'] !== 'Finalizada') {
                            $todas_finalizadas = false;
                            $debug_messages[] = "⚠ Semi-final " . ($idx + 1) . " ainda não está finalizada";
                            break;
                        }
                        
                        // Determinar vencedor
                        if ($semi['pontos_time1'] > $semi['pontos_time2']) {
                            $vencedores[] = (int)$semi['time1_id'];
                            $nomes_vencedores[] = $time1_nome_semi;
                            $debug_messages[] = "✓ Vencedor da semi-final " . ($idx + 1) . ": {$time1_nome_semi} (ID={$semi['time1_id']})";
                        } elseif ($semi['pontos_time2'] > $semi['pontos_time1']) {
                            $vencedores[] = (int)$semi['time2_id'];
                            $nomes_vencedores[] = $time2_nome_semi;
                            $debug_messages[] = "✓ Vencedor da semi-final " . ($idx + 1) . ": {$time2_nome_semi} (ID={$semi['time2_id']})";
                        } else {
                            // Empate - não pode criar final
                            $todas_finalizadas = false;
                            $debug_messages[] = "⚠ Semi-final " . ($idx + 1) . " terminou em empate - não pode criar final";
                            break;
                        }
                    }
                    
                    $debug_messages[] = "Todas finalizadas: " . ($todas_finalizadas ? 'SIM' : 'NÃO') . " | Vencedores encontrados: " . count($vencedores);
                    
                    // Se todas as semi-finais estão finalizadas e temos 2 vencedores, criar a final
                    if ($todas_finalizadas && count($vencedores) === 2) {
                        // Verificar se já existe uma final
                        $sql_check_final = "SELECT id FROM torneio_partidas 
                                           WHERE torneio_id = ? AND grupo_id = ? AND tipo_fase = 'Final'";
                        $stmt_check_final = executeQuery($pdo, $sql_check_final, [$partida['torneio_id'], $info_partida['grupo_id']]);
                        $final_existente = $stmt_check_final ? $stmt_check_final->fetch() : null;
                        
                        if (!$final_existente) {
                            // Verificar se grupo_id existe em torneio_partidas
                            $columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
                            $tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;
                            
                            if ($tem_grupo_id_partidas) {
                                $debug_messages[] = "=== CRIANDO FINAL DO OURO ===";
                                $debug_messages[] = "Time 1: {$nomes_vencedores[0]} (ID: {$vencedores[0]})";
                                $debug_messages[] = "Time 2: {$nomes_vencedores[1]} (ID: {$vencedores[1]})";
                                
                                // Criar a final
                                $sql_insert_final = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                                                    VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada', 'Final')";
                                $result_final = executeQuery($pdo, $sql_insert_final, [
                                    $partida['torneio_id'],
                                    $vencedores[0],
                                    $vencedores[1],
                                    $info_partida['grupo_id']
                                ]);
                                
                                if ($result_final !== false) {
                                    $final_id = $pdo->lastInsertId();
                                    $final_criada = true;
                                    $mensagem_final = "Final do Ouro criada automaticamente: {$nomes_vencedores[0]} vs {$nomes_vencedores[1]}";
                                    $debug_messages[] = "✓ Final criada com sucesso! ID: $final_id";
                                    $debug_messages[] = "✓ {$nomes_vencedores[0]} vs {$nomes_vencedores[1]}";
                                } else {
                                    $error_info = $pdo->errorInfo();
                                    $debug_messages[] = "✗ ERRO ao criar final: " . ($error_info[2] ?? 'Erro desconhecido');
                                }
                            } else {
                                $debug_messages[] = "✗ Coluna grupo_id não existe em torneio_partidas";
                            }
                        } else {
                            $debug_messages[] = "Final já existe (ID: {$final_existente['id']})";
                        }
                    } else {
                        $debug_messages[] = "Aguardando: " . (count($semifinais) - count(array_filter($semifinais, function($s) { return $s['status'] === 'Finalizada'; }))) . " semi-final(is) ainda não finalizada(s)";
                    }
                } else {
                    $debug_messages[] = "Partida não é do grupo Ouro - Chaves (grupo: " . ($grupo_info['nome'] ?? 'N/A') . ")";
                }
            }
        }
    }
    
    $pdo->commit();
    
    // IMPORTANTE: Processar criação da final APÓS o commit para garantir que os dados estão atualizados
    // Funciona para todas as séries: Ouro, Prata, Bronze
    if ($eh_semi_final_ouro && $status === 'Finalizada') {
        // Determinar a série da semi-final finalizada
        $serie_semi_final = null;
        if ($info_semi) {
            $serie_raw = trim($info_semi['serie'] ?? '');
            if (empty($serie_raw)) {
                // Se serie está vazia, tentar determinar pela partida
                $serie_semi_final = 'Ouro'; // Default
            } else {
                // Extrair série base (Ouro, Prata, Bronze)
                if (stripos($serie_raw, 'Ouro') !== false) {
                    $serie_semi_final = 'Ouro';
                } elseif (stripos($serie_raw, 'Prata') !== false) {
                    $serie_semi_final = 'Prata';
                } elseif (stripos($serie_raw, 'Bronze') !== false) {
                    $serie_semi_final = 'Bronze';
                } else {
                    $serie_semi_final = $serie_raw;
                }
            }
        }
        
        if ($serie_semi_final) {
            $debug_messages_final[] = "=== VERIFICANDO CRIAÇÃO DE FINAL DA SÉRIE: $serie_semi_final ===";
            $debug_messages_final[] = "Semi-final ID {$partida_id} foi finalizada. Verificando se todas as semi-finais foram finalizadas...";
            
            // Buscar todas as semi-finais da série (após o commit, os dados estão atualizados)
            $sql_semifinais_serie = "SELECT id, time1_id, time2_id, pontos_time1, pontos_time2, status, rodada, serie
                                   FROM partidas_2fase_eliminatorias
                                   WHERE torneio_id = ? 
                                   AND tipo_eliminatoria = 'Semi-Final'
                                   AND (serie = ? OR serie = ? OR serie = ? OR serie IS NULL OR serie = '')
                                   ORDER BY rodada ASC, id ASC";
            $stmt_semifinais_serie = executeQuery($pdo, $sql_semifinais_serie, [
                $partida['torneio_id'],
                $serie_semi_final,
                $serie_semi_final . ' A',
                $serie_semi_final . ' B'
            ]);
            $semifinais_serie = $stmt_semifinais_serie ? $stmt_semifinais_serie->fetchAll(PDO::FETCH_ASSOC) : [];
            
            // Filtrar apenas as da série correta (considerar string vazia também)
            $semifinais_serie = array_filter($semifinais_serie, function($semi) use ($serie_semi_final) {
                $serie = trim($semi['serie'] ?? '');
                return empty($serie) || 
                       $serie === $serie_semi_final || 
                       $serie === $serie_semi_final . ' A' || 
                       $serie === $serie_semi_final . ' B';
            });
            $semifinais_serie = array_values($semifinais_serie);
            
            $debug_messages_final[] = "Total de semi-finais encontradas da série $serie_semi_final: " . count($semifinais_serie);
            
            if (count($semifinais_serie) === 2) {
                $todas_finalizadas = true;
                $vencedores = [];
                $nomes_vencedores = [];
                
                foreach ($semifinais_serie as $idx => $semi) {
                    // Buscar nomes dos times
                    $sql_time1_nome = "SELECT nome FROM torneio_times WHERE id = ? AND torneio_id = ?";
                    $stmt_time1_nome = executeQuery($pdo, $sql_time1_nome, [$semi['time1_id'], $partida['torneio_id']]);
                    $time1_nome_semi = $stmt_time1_nome ? $stmt_time1_nome->fetch()['nome'] : 'Time ID ' . $semi['time1_id'];
                    
                    $sql_time2_nome = "SELECT nome FROM torneio_times WHERE id = ? AND torneio_id = ?";
                    $stmt_time2_nome = executeQuery($pdo, $sql_time2_nome, [$semi['time2_id'], $partida['torneio_id']]);
                    $time2_nome_semi = $stmt_time2_nome ? $stmt_time2_nome->fetch()['nome'] : 'Time ID ' . $semi['time2_id'];
                    
                    $debug_messages_final[] = "Semi-final " . ($idx + 1) . " (ID: {$semi['id']}): {$time1_nome_semi} ({$semi['pontos_time1']} pts) vs {$time2_nome_semi} ({$semi['pontos_time2']} pts) - Status: {$semi['status']}";
                    
                    if ($semi['status'] !== 'Finalizada') {
                        $todas_finalizadas = false;
                        $debug_messages_final[] = "⚠ Semi-final " . ($idx + 1) . " ainda não está finalizada";
                        break;
                    }
                    
                    // Determinar vencedor
                    if ($semi['pontos_time1'] > $semi['pontos_time2']) {
                        $vencedores[] = (int)$semi['time1_id'];
                        $nomes_vencedores[] = $time1_nome_semi;
                        $debug_messages_final[] = "✓ Vencedor da semi-final " . ($idx + 1) . ": {$time1_nome_semi} (ID={$semi['time1_id']})";
                    } elseif ($semi['pontos_time2'] > $semi['pontos_time1']) {
                        $vencedores[] = (int)$semi['time2_id'];
                        $nomes_vencedores[] = $time2_nome_semi;
                        $debug_messages_final[] = "✓ Vencedor da semi-final " . ($idx + 1) . ": {$time2_nome_semi} (ID={$semi['time2_id']})";
                    } else {
                        // Empate - não pode criar final
                        $todas_finalizadas = false;
                        $debug_messages_final[] = "⚠ Semi-final " . ($idx + 1) . " terminou em empate - não pode criar final";
                        break;
                    }
                }
                
                $debug_messages_final[] = "Todas finalizadas: " . ($todas_finalizadas ? 'SIM' : 'NÃO') . " | Vencedores encontrados: " . count($vencedores);
                
                // Se todas as semi-finais estão finalizadas e temos 2 vencedores, criar a final
                if ($todas_finalizadas && count($vencedores) === 2) {
                    // Verificar se já existe uma final da série
                    $sql_check_final = "SELECT id FROM partidas_2fase_eliminatorias 
                                       WHERE torneio_id = ? 
                                       AND tipo_eliminatoria = 'Final'
                                       AND (serie = ? OR serie = ? OR serie = ? OR serie IS NULL OR serie = '')";
                    $stmt_check_final = executeQuery($pdo, $sql_check_final, [
                        $partida['torneio_id'],
                        $serie_semi_final,
                        $serie_semi_final . ' A',
                        $serie_semi_final . ' B'
                    ]);
                    $final_existente = $stmt_check_final ? $stmt_check_final->fetch() : null;
                    
                    // Filtrar resultado se necessário
                    if ($final_existente) {
                        $serie_final = trim($final_existente['serie'] ?? '');
                        if (!empty($serie_final) && 
                            $serie_final !== $serie_semi_final && 
                            $serie_final !== $serie_semi_final . ' A' && 
                            $serie_final !== $serie_semi_final . ' B') {
                            $final_existente = null;
                        }
                    }
                    
                    if (!$final_existente) {
                        $debug_messages_final[] = "=== CRIANDO FINAL DA SÉRIE: $serie_semi_final ===";
                        $debug_messages_final[] = "Time 1: {$nomes_vencedores[0]} (ID: {$vencedores[0]})";
                        $debug_messages_final[] = "Time 2: {$nomes_vencedores[1]} (ID: {$vencedores[1]})";
                        
                        // Criar a final na tabela partidas_2fase_eliminatorias (nova transação)
                        $pdo->beginTransaction();
                        try {
                            $sql_insert_final = "INSERT INTO partidas_2fase_eliminatorias 
                                                (torneio_id, time1_id, time2_id, tipo_eliminatoria, serie, rodada, quadra, status, pontos_time1, pontos_time2) 
                                                VALUES (?, ?, ?, 'Final', ?, 1, 1, 'Agendada', 0, 0)";
                            $result_final = executeQuery($pdo, $sql_insert_final, [
                                $partida['torneio_id'],
                                $vencedores[0],
                                $vencedores[1],
                                $serie_semi_final
                            ]);
                            
                            if ($result_final !== false) {
                                $pdo->commit();
                                $final_id = $pdo->lastInsertId();
                                $final_criada_auto = true;
                                $mensagem_final_auto = "Final da série $serie_semi_final criada automaticamente: {$nomes_vencedores[0]} vs {$nomes_vencedores[1]}";
                                $debug_messages_final[] = "✓ Final criada com sucesso! ID: $final_id";
                                $debug_messages_final[] = "✓ {$nomes_vencedores[0]} vs {$nomes_vencedores[1]}";
                            } else {
                                $pdo->rollBack();
                                $error_info = $pdo->errorInfo();
                                $debug_messages_final[] = "✗ ERRO ao criar final: " . ($error_info[2] ?? 'Erro desconhecido');
                            }
                        } catch (Exception $e_final) {
                            $pdo->rollBack();
                            $debug_messages_final[] = "✗ ERRO ao criar final: " . $e_final->getMessage();
                        }
                    } else {
                        $debug_messages_final[] = "Final já existe (ID: {$final_existente['id']})";
                    }
                } else {
                    $debug_messages_final[] = "Aguardando: " . (2 - count(array_filter($semifinais_serie, function($s) { return $s['status'] === 'Finalizada'; }))) . " semi-final(is) ainda não finalizada(s)";
                }
            } else {
                $debug_messages_final[] = "⚠ Número incorreto de semi-finais encontradas. Esperado: 2, Encontrado: " . count($semifinais_serie);
            }
        }
    }
    
    // Verificar se todas as partidas "Todos Contra Todos" de cada série (Ouro, Prata, Bronze) estão finalizadas e gerar semi-finais automaticamente
    $semi_finais_geradas_auto = false;
    $mensagem_semi_finais_auto = '';
    
    if ($modalidade_torneio === 'torneio_pro' && $status === 'Finalizada' && $tabela_partida === 'partidas_2fase_torneio') {
        // Verificar se é grupo de alguma série (Ouro A/B, Prata A/B, Bronze A/B)
        $grupo_id_partida = $partida['grupo_id'] ?? null;
        if ($grupo_id_partida) {
            $sql_grupo_info_auto = "SELECT nome FROM torneio_grupos WHERE id = ?";
            $stmt_grupo_info_auto = executeQuery($pdo, $sql_grupo_info_auto, [$grupo_id_partida]);
            $grupo_info_auto = $stmt_grupo_info_auto ? $stmt_grupo_info_auto->fetch() : null;
            
            if ($grupo_info_auto) {
                $nome_grupo = $grupo_info_auto['nome'];
                $serie_detectada = null;
                
                // Detectar qual série (Ouro, Prata ou Bronze)
                if (strpos($nome_grupo, 'Ouro A') !== false || strpos($nome_grupo, 'Ouro B') !== false) {
                    $serie_detectada = 'Ouro';
                } elseif (strpos($nome_grupo, 'Prata A') !== false || strpos($nome_grupo, 'Prata B') !== false) {
                    $serie_detectada = 'Prata';
                } elseif (strpos($nome_grupo, 'Bronze A') !== false || strpos($nome_grupo, 'Bronze B') !== false) {
                    $serie_detectada = 'Bronze';
                }
                
                if ($serie_detectada) {
                    // Buscar grupos A e B da série detectada
                    $sql_grupos_serie = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND (nome = '2ª Fase - $serie_detectada A' OR nome = '2ª Fase - $serie_detectada B')";
                    $stmt_grupos_serie = executeQuery($pdo, $sql_grupos_serie, [$partida['torneio_id']]);
                    $grupos_serie = $stmt_grupos_serie ? $stmt_grupos_serie->fetchAll(PDO::FETCH_ASSOC) : [];
                    
                    if (count($grupos_serie) == 2) {
                        $grupo_a_id = null;
                        $grupo_b_id = null;
                        foreach ($grupos_serie as $grupo_serie) {
                            if (strpos($grupo_serie['nome'], "$serie_detectada A") !== false) {
                                $grupo_a_id = (int)$grupo_serie['id'];
                            } else {
                                $grupo_b_id = (int)$grupo_serie['id'];
                            }
                        }
                        
                        if ($grupo_a_id && $grupo_b_id) {
                            // Verificar se todos os jogos "Todos Contra Todos" de ambos os grupos estão finalizados
                            $sql_check_a = "SELECT COUNT(*) as total, 
                                            SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                            FROM partidas_2fase_torneio 
                                            WHERE torneio_id = ? AND grupo_id = ?";
                            $stmt_check_a = executeQuery($pdo, $sql_check_a, [$partida['torneio_id'], $grupo_a_id]);
                            $info_a = $stmt_check_a ? $stmt_check_a->fetch() : ['total' => 0, 'finalizadas' => 0];
                            
                            $sql_check_b = "SELECT COUNT(*) as total, 
                                            SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                            FROM partidas_2fase_torneio 
                                            WHERE torneio_id = ? AND grupo_id = ?";
                            $stmt_check_b = executeQuery($pdo, $sql_check_b, [$partida['torneio_id'], $grupo_b_id]);
                            $info_b = $stmt_check_b ? $stmt_check_b->fetch() : ['total' => 0, 'finalizadas' => 0];
                            
                            $todas_a_finalizadas = $info_a['total'] > 0 && $info_a['finalizadas'] == $info_a['total'];
                            $todas_b_finalizadas = $info_b['total'] > 0 && $info_b['finalizadas'] == $info_b['total'];
                            
                            // Verificar se já existem semi-finais desta série
                            $sql_check_semifinais = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                                                     WHERE torneio_id = ? 
                                                     AND tipo_eliminatoria = 'Semi-Final'
                                                     AND serie = ?";
                            $stmt_check_semifinais = executeQuery($pdo, $sql_check_semifinais, [$partida['torneio_id'], $serie_detectada]);
                            $tem_semifinais = $stmt_check_semifinais ? (int)$stmt_check_semifinais->fetch()['total'] > 0 : false;
                            
                            // Gerar semi-finais automaticamente quando todos os jogos estiverem finalizados
                            if ($todas_a_finalizadas && $todas_b_finalizadas && !$tem_semifinais) {
                                // Buscar classificação dos grupos A e B
                                $sql_class_a = "SELECT tc.time_id, tt.id, tt.nome, tt.cor
                                               FROM partidas_2fase_classificacao tc
                                               JOIN torneio_times tt ON tt.id = tc.time_id
                                               WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                                               ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                                               LIMIT 2";
                                $stmt_class_a = executeQuery($pdo, $sql_class_a, [$partida['torneio_id'], $grupo_a_id]);
                                $classificacao_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
                                
                                $sql_class_b = "SELECT tc.time_id, tt.id, tt.nome, tt.cor
                                               FROM partidas_2fase_classificacao tc
                                               JOIN torneio_times tt ON tt.id = tc.time_id
                                               WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                                               ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                                               LIMIT 2";
                                $stmt_class_b = executeQuery($pdo, $sql_class_b, [$partida['torneio_id'], $grupo_b_id]);
                                $classificacao_b = $stmt_class_b ? $stmt_class_b->fetchAll(PDO::FETCH_ASSOC) : [];
                                
                                if (count($classificacao_a) >= 2 && count($classificacao_b) >= 2) {
                                    $primeiro_a = (int)$classificacao_a[0]['time_id'];
                                    $segundo_a = (int)$classificacao_a[1]['time_id'];
                                    $primeiro_b = (int)$classificacao_b[0]['time_id'];
                                    $segundo_b = (int)$classificacao_b[1]['time_id'];
                                    
                                    // Criar semi-finais na tabela partidas_2fase_eliminatorias
                                    // Jogo 1: 1º A vs 2º B
                                    $sql_semi1 = "INSERT INTO partidas_2fase_eliminatorias (torneio_id, time1_id, time2_id, tipo_eliminatoria, serie, rodada, quadra, status) 
                                                 VALUES (?, ?, ?, 'Semi-Final', ?, 1, 1, 'Agendada')";
                                    executeQuery($pdo, $sql_semi1, [$partida['torneio_id'], $primeiro_a, $segundo_b, $serie_detectada]);
                                    
                                    // Jogo 2: 2º A vs 1º B
                                    $sql_semi2 = "INSERT INTO partidas_2fase_eliminatorias (torneio_id, time1_id, time2_id, tipo_eliminatoria, serie, rodada, quadra, status) 
                                                 VALUES (?, ?, ?, 'Semi-Final', ?, 1, 1, 'Agendada')";
                                    executeQuery($pdo, $sql_semi2, [$partida['torneio_id'], $segundo_a, $primeiro_b, $serie_detectada]);
                                    
                                    $semi_finais_geradas_auto = true;
                                    $mensagem_semi_finais_auto = "Semi-finais da série $serie_detectada geradas automaticamente: 1º $serie_detectada A vs 2º $serie_detectada B e 2º $serie_detectada A vs 1º $serie_detectada B";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Verificar se todas as partidas da 1ª fase foram finalizadas (apenas para torneio_pro)
    if ($modalidade_torneio === 'torneio_pro') {
        $sql_check_1fase = "SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                           FROM torneio_partidas 
                           WHERE torneio_id = ? AND (fase = 'Grupos' OR fase IS NULL OR fase = '')";
        $stmt_check_1fase = executeQuery($pdo, $sql_check_1fase, [$partida['torneio_id']]);
        $info_1fase = $stmt_check_1fase ? $stmt_check_1fase->fetch() : ['total' => 0, 'finalizadas' => 0];
        $todas_1fase_finalizadas = $info_1fase['total'] > 0 && $info_1fase['finalizadas'] == $info_1fase['total'];
        
        if ($todas_1fase_finalizadas) {
            // Verificar se a tabela torneio_classificacao_2fase existe
            $sql_check_table = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
            $stmt_check_table = $pdo->query($sql_check_table);
            $tabela_existe = $stmt_check_table && $stmt_check_table->rowCount() > 0;
            
            if ($tabela_existe) {
                // Verificar se já foi alimentada
                $sql_check_alimentada = "SELECT COUNT(*) as total FROM torneio_classificacao_2fase WHERE torneio_id = ?";
                $stmt_check_alimentada = executeQuery($pdo, $sql_check_alimentada, [$partida['torneio_id']]);
                $total_alimentada = $stmt_check_alimentada ? (int)$stmt_check_alimentada->fetch()['total'] : 0;
                
                if ($total_alimentada == 0) {
                    // Alimentar a tabela com os times classificados da 1ª fase
                    error_log("DEBUG: Alimentando tabela torneio_classificacao_2fase para torneio " . $partida['torneio_id']);
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Buscar todos os grupos da 1ª fase
                        $sql_grupos = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE 'Grupo%' ORDER BY ordem";
                        $stmt_grupos = executeQuery($pdo, $sql_grupos, [$partida['torneio_id']]);
                        $grupos = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        foreach ($grupos as $grupo) {
                            $grupo_id = (int)$grupo['id'];
                            $grupo_nome = $grupo['nome'];
                            
                            // Buscar classificação do grupo ordenada
                            $sql_class_grupo = "SELECT 
                                tc.time_id,
                                tc.pontos_total,
                                tc.vitorias,
                                tc.derrotas,
                                tc.empates,
                                tc.pontos_pro,
                                tc.pontos_contra,
                                tc.saldo_pontos,
                                tc.average
                            FROM torneio_classificacao tc
                            WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                            ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
                            
                            $stmt_class_grupo = executeQuery($pdo, $sql_class_grupo, [$partida['torneio_id'], $grupo_id]);
                            $classificacoes_grupo = $stmt_class_grupo ? $stmt_class_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
                            
                            // Calcular posição baseado na ordem (já vem ordenado)
                            $posicao = 1;
                            foreach ($classificacoes_grupo as $class) {
                                // Inserir na tabela torneio_classificacao_2fase
                                $sql_insert_2fase = "INSERT INTO torneio_classificacao_2fase 
                                    (torneio_id, time_id, chave_origem_id, chave_origem_nome, posicao_chave, pontos_total, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                    chave_origem_id = VALUES(chave_origem_id),
                                    chave_origem_nome = VALUES(chave_origem_nome),
                                    posicao_chave = VALUES(posicao_chave),
                                    pontos_total = VALUES(pontos_total),
                                    vitorias = VALUES(vitorias),
                                    derrotas = VALUES(derrotas),
                                    empates = VALUES(empates),
                                    pontos_pro = VALUES(pontos_pro),
                                    pontos_contra = VALUES(pontos_contra),
                                    saldo_pontos = VALUES(saldo_pontos),
                                    average = VALUES(average)";
                                
                                executeQuery($pdo, $sql_insert_2fase, [
                                    $partida['torneio_id'],
                                    (int)$class['time_id'],
                                    $grupo_id,
                                    $grupo_nome,
                                    $posicao,
                                    (int)$class['pontos_total'],
                                    (int)$class['vitorias'],
                                    (int)$class['derrotas'],
                                    (int)$class['empates'],
                                    (int)$class['pontos_pro'],
                                    (int)$class['pontos_contra'],
                                    (int)$class['saldo_pontos'],
                                    (float)$class['average']
                                ]);
                                
                                $posicao++;
                            }
                        }
                        
                        $pdo->commit();
                        error_log("DEBUG: Tabela torneio_classificacao_2fase alimentada com sucesso!");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("DEBUG: Erro ao alimentar torneio_classificacao_2fase: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    // Registrar semi-finais e finais na tabela torneio_classificacao_2fase_historico
    // IMPORTANTE: Executar DEPOIS do commit para garantir que os dados estão persistidos
    // NOTA: Para partidas eliminatórias da tabela partidas_2fase_eliminatorias, não é necessário registrar no histórico
    // Isso evita erros e simplifica o código
    if ($status === 'Finalizada' && $modalidade_torneio === 'torneio_pro' && $tabela_partida !== 'partidas_2fase_eliminatorias') {
        // Buscar dados atualizados da partida após o commit
        // Apenas para partidas normais (não eliminatórias)
        try {
            $sql_partida_atualizada = "SELECT fase, tipo_fase, grupo_id, time1_id, time2_id FROM torneio_partidas WHERE id = ?";
            $stmt_partida_atualizada = executeQuery($pdo, $sql_partida_atualizada, [$partida_id]);
            $partida_atualizada = $stmt_partida_atualizada ? $stmt_partida_atualizada->fetch() : null;
            
            if ($partida_atualizada && !empty($partida_atualizada['fase']) && $partida_atualizada['fase'] === '2ª Fase') {
                $tipo_fase_partida = $partida_atualizada['tipo_fase'] ?? '';
                $eh_eliminatoria = in_array($tipo_fase_partida, ['Semi-Final', 'Final', '3º Lugar']);
                
                if ($eh_eliminatoria && !empty($partida_atualizada['grupo_id'])) {
                    // Verificar se a tabela torneio_classificacao_2fase_historico existe
                    $sql_check_table_historico = "SHOW TABLES LIKE 'torneio_classificacao_2fase_historico'";
                    $stmt_check_table_historico = $pdo->query($sql_check_table_historico);
                    $tabela_historico_existe = $stmt_check_table_historico && $stmt_check_table_historico->rowCount() > 0;
                    
                    error_log("DEBUG ELIMINATORIA: tabela_historico_existe=" . ($tabela_historico_existe ? 'SIM' : 'NÃO'));
                    
                    if ($tabela_historico_existe) {
                        // Buscar informações do grupo
                        $sql_grupo_info_elim = "SELECT id, nome FROM torneio_grupos WHERE id = ?";
                        $stmt_grupo_info_elim = executeQuery($pdo, $sql_grupo_info_elim, [$partida_atualizada['grupo_id']]);
                        $grupo_info_elim = $stmt_grupo_info_elim ? $stmt_grupo_info_elim->fetch() : null;
                        
                        if ($grupo_info_elim) {
                            $grupo_id_elim = (int)$grupo_info_elim['id'];
                            $nome_grupo_elim = $grupo_info_elim['nome'];
                            error_log("DEBUG ELIMINATORIA: nome_grupo={$nome_grupo_elim}, grupo_id={$grupo_id_elim}");
                            
                            // Registrar ambos os times da partida eliminatória
                            $times_partida = [$partida_atualizada['time1_id'], $partida_atualizada['time2_id']];
                            
                            foreach ($times_partida as $time_id_elim) {
                                // Buscar dados do time da 1ª fase (grupo original) para preencher os dados históricos
                                $sql_time_1fase = "SELECT tc.grupo_id, tg.nome as grupo_nome, tc.posicao, tc.pontos_total, tc.vitorias, tc.derrotas, tc.empates, tc.pontos_pro, tc.pontos_contra, tc.saldo_pontos, tc.average
                                                   FROM torneio_classificacao tc
                                                   JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                                                   WHERE tc.torneio_id = ? AND tc.time_id = ? AND tg.nome LIKE 'Grupo%'
                                                   ORDER BY tc.pontos_total DESC, tc.vitorias DESC
                                                   LIMIT 1";
                                $stmt_time_1fase = executeQuery($pdo, $sql_time_1fase, [$partida['torneio_id'], $time_id_elim]);
                                $time_1fase = $stmt_time_1fase ? $stmt_time_1fase->fetch() : null;
                                
                                // Se não encontrou na 1ª fase, buscar dados da 2ª fase "Todos Contra Todos" se existir
                                if (!$time_1fase) {
                                    $sql_time_2fase = "SELECT tgt.grupo_id, tg.nome as grupo_nome
                                                       FROM torneio_grupo_times tgt
                                                       JOIN torneio_grupos tg ON tg.id = tgt.grupo_id
                                                       WHERE tgt.time_id = ? AND tg.torneio_id = ? AND tg.nome LIKE '2ª Fase%' 
                                                       AND (tg.nome LIKE '%Ouro A%' OR tg.nome LIKE '%Ouro B%' OR tg.nome LIKE '%Prata A%' OR tg.nome LIKE '%Prata B%' OR tg.nome LIKE '%Bronze A%' OR tg.nome LIKE '%Bronze B%')
                                                       LIMIT 1";
                                    $stmt_time_2fase = executeQuery($pdo, $sql_time_2fase, [$time_id_elim, $partida['torneio_id']]);
                                    $time_2fase_grupo = $stmt_time_2fase ? $stmt_time_2fase->fetch() : null;
                                    
                                    if ($time_2fase_grupo) {
                                        // Buscar dados da classificação da 2ª fase se existir
                                        $sql_class_2fase = "SELECT posicao, pontos_total, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average
                                                            FROM torneio_classificacao_2fase
                                                            WHERE torneio_id = ? AND time_id = ? 
                                                            LIMIT 1";
                                        $stmt_class_2fase = executeQuery($pdo, $sql_class_2fase, [$partida['torneio_id'], $time_id_elim]);
                                        $class_2fase = $stmt_class_2fase ? $stmt_class_2fase->fetch() : null;
                                        
                                        $time_1fase = [
                                            'grupo_id' => (int)$time_2fase_grupo['grupo_id'],
                                            'grupo_nome' => $time_2fase_grupo['grupo_nome'],
                                            'posicao' => $class_2fase ? (int)$class_2fase['posicao'] : null,
                                            'pontos_total' => $class_2fase ? (int)$class_2fase['pontos_total'] : 0,
                                            'vitorias' => $class_2fase ? (int)$class_2fase['vitorias'] : 0,
                                            'derrotas' => $class_2fase ? (int)$class_2fase['derrotas'] : 0,
                                            'empates' => $class_2fase ? (int)$class_2fase['empates'] : 0,
                                            'pontos_pro' => $class_2fase ? (int)$class_2fase['pontos_pro'] : 0,
                                            'pontos_contra' => $class_2fase ? (int)$class_2fase['pontos_contra'] : 0,
                                            'saldo_pontos' => $class_2fase ? (int)$class_2fase['saldo_pontos'] : 0,
                                            'average' => $class_2fase ? (float)$class_2fase['average'] : 0.00
                                        ];
                                    }
                                }
                                
                                // Se encontrou dados do time (1ª fase ou 2ª fase), inserir na tabela de histórico
                                if ($time_1fase) {
                                    $chave_origem_id = (int)$time_1fase['grupo_id'];
                                    
                                    // Verificar se já existe registro para este time com este grupo de origem
                                    $sql_check_historico = "SELECT id FROM torneio_classificacao_2fase_historico WHERE torneio_id = ? AND time_id = ? AND chave_origem_id = ?";
                                    $stmt_check_historico = executeQuery($pdo, $sql_check_historico, [$partida['torneio_id'], $time_id_elim, $chave_origem_id]);
                                    $exists_historico = $stmt_check_historico ? $stmt_check_historico->fetch() : null;
                                    
                                    if (!$exists_historico) {
                                        // Inserir registro na tabela de histórico
                                        $sql_insert_historico = "INSERT INTO torneio_classificacao_2fase_historico 
                                            (torneio_id, time_id, chave_origem_id, chave_origem_nome, posicao_chave, pontos_total, vitorias, derrotas, empates, pontos_pro, pontos_contra, saldo_pontos, average)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                        
                                        $result_insert = executeQuery($pdo, $sql_insert_historico, [
                                            $partida['torneio_id'],
                                            $time_id_elim,
                                            $chave_origem_id,
                                            $time_1fase['grupo_nome'],
                                            $time_1fase['posicao'],
                                            $time_1fase['pontos_total'],
                                            $time_1fase['vitorias'],
                                            $time_1fase['derrotas'],
                                            $time_1fase['empates'],
                                            $time_1fase['pontos_pro'],
                                            $time_1fase['pontos_contra'],
                                            $time_1fase['saldo_pontos'],
                                            $time_1fase['average']
                                        ]);
                                        
                                        if ($result_insert !== false) {
                                            error_log("DEBUG ELIMINATORIA: ✓ Inserido time_id={$time_id_elim} na tabela historico (chave_origem_id={$chave_origem_id}, grupo={$time_1fase['grupo_nome']})");
                                        } else {
                                            $error_info = $pdo->errorInfo();
                                            error_log("DEBUG ELIMINATORIA: ✗ ERRO ao inserir time_id={$time_id_elim}: " . ($error_info[2] ?? 'Desconhecido'));
                                        }
                                    } else {
                                        error_log("DEBUG ELIMINATORIA: Time_id={$time_id_elim} já existe na tabela historico (chave_origem_id={$chave_origem_id})");
                                    }
                                } else {
                                    error_log("DEBUG ELIMINATORIA: ⚠ Não foi possível encontrar dados do time_id={$time_id_elim} na 1ª fase nem na 2ª fase");
                                }
                            }
                        } else {
                            error_log("DEBUG ELIMINATORIA: Grupo não encontrado (ID: {$partida_atualizada['grupo_id']})");
                        }
                    }
                }
            }
        } catch (Exception $e_hist) {
            // Erro ao processar histórico - não é crítico, apenas logar
            error_log("DEBUG ELIMINATORIA: Erro ao processar histórico: " . $e_hist->getMessage());
        }
    }
    
    $mensagem_sucesso = 'Resultado salvo com sucesso!';
    if ($final_criada && !empty($mensagem_final)) {
        $mensagem_sucesso .= ' ' . $mensagem_final;
    }
    if ($final_criada_auto && !empty($mensagem_final_auto)) {
        $mensagem_sucesso .= ' ' . $mensagem_final_auto;
    }
    if ($semi_finais_geradas_auto && !empty($mensagem_semi_finais_auto)) {
        $mensagem_sucesso .= ' ' . $mensagem_semi_finais_auto;
    }
    
    $response = [
        'success' => true,
        'message' => $mensagem_sucesso,
        'final_criada' => $final_criada || $final_criada_auto,
        'semi_finais_geradas' => $semi_finais_geradas_auto
    ];
    
    // Adicionar debug se houver mensagens de debug
    if (!empty($debug_messages)) {
        $response['debug'] = $debug_messages; // Retornar como array para facilitar processamento
    }
    // Adicionar debug da criação automática da final
    if (!empty($debug_messages_final)) {
        if (isset($response['debug'])) {
            $response['debug'] = array_merge($response['debug'], $debug_messages_final);
        } else {
            $response['debug'] = $debug_messages_final;
        }
    }
    
    // Usar função helper para retornar JSON de forma segura
    returnJsonSuccess($response);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao salvar resultado: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    
    // Limpar qualquer output antes de enviar JSON de erro
    $output = ob_get_clean();
    if (!empty($output) && !empty(trim($output))) {
        error_log("Output capturado no catch Exception: " . substr($output, 0, 200));
    }
    
    // Garantir que o header JSON está definido
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar resultado: ' . $e->getMessage()]);
    exit();
} catch (Error $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro fatal ao salvar resultado: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    
    // Limpar qualquer output antes de enviar JSON de erro
    $output = ob_get_clean();
    if (!empty($output) && !empty(trim($output))) {
        error_log("Output capturado no catch Error: " . substr($output, 0, 200));
    }
    
    // Garantir que o header JSON está definido
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode(['success' => false, 'message' => 'Erro fatal ao salvar resultado: ' . $e->getMessage()]);
    exit();
}

// Se chegou aqui sem entrar em nenhum catch, algo está errado
$output = ob_get_clean();
if (!empty($output) && !empty(trim($output))) {
    error_log("Output capturado no final do script: " . substr($output, 0, 200));
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode(['success' => false, 'message' => 'Erro desconhecido ao processar requisição']);
exit();
?>

