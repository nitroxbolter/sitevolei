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

$quantidade_times = (int)($jogo['quantidade_times'] ?? 0);
$integrantes_por_time = (int)($jogo['integrantes_por_time'] ?? 0);

if ($quantidade_times <= 0 || $integrantes_por_time <= 0) {
    echo json_encode(['success' => false, 'message' => 'Configure a modalidade primeiro.']);
    exit();
}

// Verificar quantos times já existem
$sql = "SELECT COUNT(*) AS total FROM grupo_jogo_times WHERE jogo_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$times_existentes = $stmt ? (int)$stmt->fetch()['total'] : 0;

$pdo->beginTransaction();
try {
    // Sempre limpar times existentes e seus integrantes primeiro
    // Primeiro remover integrantes
    $sql = "DELETE gjti FROM grupo_jogo_time_integrantes gjti
            INNER JOIN grupo_jogo_times gjt ON gjt.id = gjti.time_id
            WHERE gjt.jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Depois remover todos os times
    $sql = "DELETE FROM grupo_jogo_times WHERE jogo_id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    // Array de cores
    $cores = [
        '#007bff', '#28a745', '#dc3545', '#ffc107',
        '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14',
        '#20c997', '#6610f2', '#343a40', '#6c757d'
    ];
    
    // Criar exatamente a quantidade de times configurada
    $times_criados = 0;
    $ids_criados = [];
    
    for ($i = 1; $i <= $quantidade_times; $i++) {
        $cor = $cores[($i - 1) % count($cores)];
        $nome = 'Time ' . $i;
        
        // Verificar se já existe um time com essa ordem (dupla verificação)
        $sql_check = "SELECT id FROM grupo_jogo_times WHERE jogo_id = ? AND ordem = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$jogo_id, $i]);
        if ($stmt_check && $stmt_check->fetch()) {
            error_log("AVISO: Time com ordem $i já existe para jogo $jogo_id - pulando criação");
            continue;
        }
        
        $sql = "INSERT INTO grupo_jogo_times (jogo_id, nome, cor, ordem) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$jogo_id, $nome, $cor, $i]);
        
        if ($result) {
            $id_criado = (int)$pdo->lastInsertId();
            $ids_criados[] = $id_criado;
            $times_criados++;
            error_log("DEBUG - Time criado: ID=$id_criado, Nome=$nome, Ordem=$i, Jogo=$jogo_id");
        } else {
            $error = $stmt->errorInfo();
            error_log("Erro ao criar time $i para jogo $jogo_id: " . ($error[2] ?? 'Erro desconhecido'));
        }
    }
    
    // Verificar quantos times foram realmente criados e listar todos
    $sql = "SELECT id, nome, ordem FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem ASC, id ASC";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $times_verificados = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $total_criado = count($times_verificados);
    
    // Debug: mostrar todos os IDs criados (também no console do navegador via resposta)
    $debug_info = [
        'ids_criados' => $ids_criados,
        'times_verificados' => $times_verificados,
        'total_criados' => $times_criados,
        'total_verificado' => $total_criado,
        'quantidade_esperada' => $quantidade_times
    ];
    
    // Logs detalhados
    error_log("=== DEBUG CRIAÇÃO DE TIMES ===");
    error_log("Jogo ID: $jogo_id");
    error_log("Quantidade esperada: $quantidade_times");
    error_log("IDs criados durante inserção: " . json_encode($ids_criados));
    error_log("Times verificados no banco após criação: " . json_encode($times_verificados));
    error_log("Total de times criados: $times_criados");
    error_log("Total verificado no banco: $total_criado");
    error_log("=============================");
    
    // Verificar duplicatas
    $nomes = array_column($times_verificados, 'nome');
    $ordens = array_column($times_verificados, 'ordem');
    $nomes_unicos = array_unique($nomes);
    $ordens_unicas = array_unique($ordens);
    
    if (count($nomes) !== count($nomes_unicos)) {
        error_log("ERRO: Times com nomes duplicados encontrados: " . json_encode($nomes));
    }
    if (count($ordens) !== count($ordens_unicas)) {
        error_log("ERRO: Times com ordens duplicadas encontradas: " . json_encode($ordens));
    }
    
    // Atualizar status
    $sql = "UPDATE grupo_jogos SET status = 'Times Criados' WHERE id = ?";
    executeQuery($pdo, $sql, [$jogo_id]);
    
    $pdo->commit();
    
    if ($total_criado != $quantidade_times) {
        error_log("AVISO: Esperado criar {$quantidade_times} times, mas foram criados {$total_criado} times no jogo {$jogo_id}");
    }
    
    $mensagem = $times_existentes > 0 
        ? "Times atualizados com sucesso! {$total_criado} time(s) criado(s)." 
        : "Times criados com sucesso! {$total_criado} time(s) criado(s).";
    
    echo json_encode([
        'success' => true, 
        'message' => $mensagem,
        'times_criados' => $total_criado,
        'times_esperados' => $quantidade_times,
        'ids_criados' => $ids_criados,
        'debug' => $debug_info
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao criar times: ' . $e->getMessage()]);
}
?>

