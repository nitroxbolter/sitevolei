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

// Buscar grupo Ouro A
$sql_grupo_ouro_a = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro A' LIMIT 1";
$stmt_grupo = executeQuery($pdo, $sql_grupo_ouro_a, [$torneio_id]);
$grupo_ouro_a = $stmt_grupo ? $stmt_grupo->fetch() : null;

if (!$grupo_ouro_a) {
    echo json_encode(['success' => false, 'message' => 'Grupo Ouro A não encontrado.']);
    exit();
}

$grupo_ouro_a_id = (int)$grupo_ouro_a['id'];

// Verificar se todas as partidas do Ouro A estão finalizadas
$sql_check_partidas = "SELECT COUNT(*) as total, 
                      SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                      FROM torneio_partidas 
                      WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'
                      AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
$stmt_check = executeQuery($pdo, $sql_check_partidas, [$torneio_id, $grupo_ouro_a_id]);
$info_partidas = $stmt_check ? $stmt_check->fetch() : ['total' => 0, 'finalizadas' => 0];

if ($info_partidas['total'] > 0 && $info_partidas['finalizadas'] < $info_partidas['total']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Nem todas as partidas do grupo Ouro A estão finalizadas. Finalize todas as partidas antes de gerar a semi-final.'
    ]);
    exit();
}

// Verificar se já existe semi-final do Ouro A
// Primeiro buscar o grupo de chaves do Ouro A
$sql_grupo_chaves_check = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro A - Chaves' LIMIT 1";
$stmt_grupo_chaves_check = executeQuery($pdo, $sql_grupo_chaves_check, [$torneio_id]);
$grupo_chaves_check = $stmt_grupo_chaves_check ? $stmt_grupo_chaves_check->fetch() : null;

$tem_semifinal = false;
if ($grupo_chaves_check) {
    $grupo_chaves_check_id = (int)$grupo_chaves_check['id'];
    $sql_check_semifinal = "SELECT COUNT(*) as total FROM torneio_partidas 
                           WHERE torneio_id = ? AND fase = '2ª Fase' 
                           AND tipo_fase = 'Semi-Final' 
                           AND grupo_id = ?";
    $stmt_check_semifinal = executeQuery($pdo, $sql_check_semifinal, [$torneio_id, $grupo_chaves_check_id]);
    $tem_semifinal = $stmt_check_semifinal ? (int)$stmt_check_semifinal->fetch()['total'] > 0 : false;
}

if ($tem_semifinal) {
    echo json_encode(['success' => false, 'message' => 'A semi-final do Ouro A já foi gerada.']);
    exit();
}

// Verificar se a coluna grupo_id existe em torneio_partidas
$columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
$tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;

// Verificar se a coluna tipo_fase existe em torneio_partidas
$columnsQuery_tipo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
$tem_tipo_fase = $columnsQuery_tipo && $columnsQuery_tipo->rowCount() > 0;

// Verificar se a coluna grupo_id existe em torneio_classificacao
$columnsQuery_class = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
$tem_grupo_id_classificacao = $columnsQuery_class && $columnsQuery_class->rowCount() > 0;

if (!$tem_grupo_id_classificacao) {
    echo json_encode(['success' => false, 'message' => 'A coluna grupo_id não existe em torneio_classificacao.']);
    exit();
}

$pdo->beginTransaction();

try {
    // Buscar os 2 mais pontuados do Ouro A
    $sql_classificacao = "SELECT tc.time_id, tt.id, tt.nome, tt.cor
                          FROM torneio_classificacao tc
                          JOIN torneio_times tt ON tt.id = tc.time_id
                          WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                          ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                          LIMIT 2";
    $stmt_class = executeQuery($pdo, $sql_classificacao, [$torneio_id, $grupo_ouro_a_id]);
    $classificacao = $stmt_class ? $stmt_class->fetchAll(PDO::FETCH_ASSOC) : [];
    
    if (count($classificacao) < 2) {
        throw new Exception("É necessário ter pelo menos 2 times no grupo Ouro A para gerar a semi-final.");
    }
    
    $primeiro_lugar = (int)$classificacao[0]['time_id'];
    $segundo_lugar = (int)$classificacao[1]['time_id'];
    
    // Buscar ou criar grupo de chaves do Ouro A
    $sql_grupo_chaves = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
    $stmt_grupo_chaves = executeQuery($pdo, $sql_grupo_chaves, [$torneio_id, "2ª Fase - Ouro A - Chaves"]);
    $grupo_chaves_existente = $stmt_grupo_chaves ? $stmt_grupo_chaves->fetch() : null;
    
    if ($grupo_chaves_existente) {
        $grupo_chaves_id = (int)$grupo_chaves_existente['id'];
    } else {
        $sql_insert_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
        executeQuery($pdo, $sql_insert_grupo, [$torneio_id, "2ª Fase - Ouro A - Chaves", 150]);
        $grupo_chaves_id = (int)$pdo->lastInsertId();
    }
    
    // Criar partida da semi-final: 1º lugar vs 2º lugar
    if ($tem_grupo_id_partidas) {
        if ($tem_tipo_fase) {
            $sql_semifinal = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada', 'Semi-Final')";
            executeQuery($pdo, $sql_semifinal, [$torneio_id, $primeiro_lugar, $segundo_lugar, $grupo_chaves_id]);
        } else {
            $sql_semifinal = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, 1, 'Agendada')";
            executeQuery($pdo, $sql_semifinal, [$torneio_id, $primeiro_lugar, $segundo_lugar, $grupo_chaves_id]);
        }
    } else {
        throw new Exception("A coluna grupo_id não existe em torneio_partidas.");
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Semi-final do Ouro A gerada com sucesso! Partida criada entre os 2 mais pontuados.",
        'time1' => $classificacao[0]['nome'],
        'time2' => $classificacao[1]['nome']
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerar semi-final do Ouro A: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao gerar semi-final: ' . $e->getMessage()
    ]);
}
?>

