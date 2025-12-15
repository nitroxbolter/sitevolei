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

// Verificar se a coluna grupo_id existe em torneio_partidas
$columnsQuery_grupo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
$tem_grupo_id_partidas = $columnsQuery_grupo && $columnsQuery_grupo->rowCount() > 0;

// Verificar se a coluna tipo_fase existe em torneio_partidas
$columnsQuery_tipo = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'tipo_fase'");
$tem_tipo_fase = $columnsQuery_tipo && $columnsQuery_tipo->rowCount() > 0;

// Buscar grupos da 2ª fase (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)
$sql_grupos_2fase = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' AND nome NOT LIKE '%Chaves%' ORDER BY ordem ASC";
$stmt_grupos = executeQuery($pdo, $sql_grupos_2fase, [$torneio_id]);
$grupos_2fase = $stmt_grupos ? $stmt_grupos->fetchAll() : [];

if (empty($grupos_2fase)) {
    echo json_encode(['success' => false, 'message' => 'Os grupos da 2ª fase não foram encontrados.']);
    exit();
}

// Verificar se todas as partidas todos contra todos estão finalizadas
foreach ($grupos_2fase as $grupo) {
    $grupo_id = (int)$grupo['id'];
    $sql_check_partidas = "SELECT COUNT(*) as total, 
                          SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                          FROM torneio_partidas 
                          WHERE torneio_id = ? AND grupo_id = ? AND fase = '2ª Fase'";
    $stmt_check = executeQuery($pdo, $sql_check_partidas, [$torneio_id, $grupo_id]);
    $info_partidas = $stmt_check ? $stmt_check->fetch() : ['total' => 0, 'finalizadas' => 0];
    
    if ($info_partidas['total'] > 0 && $info_partidas['finalizadas'] < $info_partidas['total']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Nem todas as partidas do grupo ' . htmlspecialchars($grupo['nome']) . ' estão finalizadas.'
        ]);
        exit();
    }
}

// Verificar se já existem semi-finais
$sql_check_semifinais = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ? AND fase = '2ª Fase' AND tipo_fase = 'Semi-Final'";
$stmt_check_semifinais = executeQuery($pdo, $sql_check_semifinais, [$torneio_id]);
$tem_semifinais = $stmt_check_semifinais ? (int)$stmt_check_semifinais->fetch()['total'] > 0 : false;

if ($tem_semifinais) {
    echo json_encode(['success' => false, 'message' => 'As semi-finais já foram geradas.']);
    exit();
}

// Mapear grupos para quadras
$grupos_para_quadras = [];
$quadras_grupos = ['Ouro A' => 1, 'Ouro B' => 2, 'Prata A' => 3, 'Prata B' => 4, 'Bronze A' => 5, 'Bronze B' => 6];

foreach ($grupos_2fase as $grupo) {
    $nome_grupo = str_replace('2ª Fase - ', '', $grupo['nome']);
    if (isset($quadras_grupos[$nome_grupo])) {
        $grupos_para_quadras[$grupo['id']] = $quadras_grupos[$nome_grupo];
    }
}

// Agrupar grupos por série
$series = [
    'Ouro' => ['A' => null, 'B' => null],
    'Prata' => ['A' => null, 'B' => null],
    'Bronze' => ['A' => null, 'B' => null]
];

foreach ($grupos_2fase as $grupo) {
    $nome_grupo = str_replace('2ª Fase - ', '', $grupo['nome']);
    foreach ($series as $serie => &$subgrupos) {
        if (strpos($nome_grupo, $serie . ' A') !== false) {
            $subgrupos['A'] = $grupo;
        } elseif (strpos($nome_grupo, $serie . ' B') !== false) {
            $subgrupos['B'] = $grupo;
        }
    }
}

$pdo->beginTransaction();

try {
    $partidas_inseridas = 0;
    
    // Verificar se a coluna grupo_id existe em torneio_classificacao
    $columnsQuery_class = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
    $tem_grupo_id_classificacao = $columnsQuery_class && $columnsQuery_class->rowCount() > 0;
    
    if (!$tem_grupo_id_classificacao) {
        throw new Exception("A coluna grupo_id não existe em torneio_classificacao.");
    }
    
    // Gerar semi-finais para cada série
    foreach ($series as $serie_nome => $subgrupos) {
        $grupo_a = $subgrupos['A'];
        $grupo_b = $subgrupos['B'];
        
        if (!$grupo_a || !$grupo_b) {
            continue; // Pular se não tiver ambos os grupos
        }
        
        $grupo_a_id = (int)$grupo_a['id'];
        $grupo_b_id = (int)$grupo_b['id'];
        $quadra_serie = $grupos_para_quadras[$grupo_a_id] ?? 1;
        
        // Buscar 1º e 2º lugares de cada grupo
        $sql_class_a = "SELECT tc.time_id, tt.id, tt.nome, tt.cor
                       FROM torneio_classificacao tc
                       JOIN torneio_times tt ON tt.id = tc.time_id
                       WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                       ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                       LIMIT 2";
        $stmt_class_a = executeQuery($pdo, $sql_class_a, [$torneio_id, $grupo_a_id]);
        $classificacao_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $sql_class_b = "SELECT tc.time_id, tt.id, tt.nome, tt.cor
                       FROM torneio_classificacao tc
                       JOIN torneio_times tt ON tt.id = tc.time_id
                       WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                       ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                       LIMIT 2";
        $stmt_class_b = executeQuery($pdo, $sql_class_b, [$torneio_id, $grupo_b_id]);
        $classificacao_b = $stmt_class_b ? $stmt_class_b->fetchAll(PDO::FETCH_ASSOC) : [];
        
        if (count($classificacao_a) < 2 || count($classificacao_b) < 2) {
            continue; // Pular se não tiver pelo menos 2 times em cada grupo
        }
        
        $primeiro_a = (int)$classificacao_a[0]['time_id'];
        $segundo_a = (int)$classificacao_a[1]['time_id'];
        $primeiro_b = (int)$classificacao_b[0]['time_id'];
        $segundo_b = (int)$classificacao_b[1]['time_id'];
        
        // Buscar ou criar grupo de chaves da série
        $sql_grupo_chaves = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = ?";
        $stmt_grupo_chaves = executeQuery($pdo, $sql_grupo_chaves, [$torneio_id, "2ª Fase - $serie_nome - Chaves"]);
        $grupo_chaves_existente = $stmt_grupo_chaves ? $stmt_grupo_chaves->fetch() : null;
        
        if ($grupo_chaves_existente) {
            $grupo_chaves_id = (int)$grupo_chaves_existente['id'];
        } else {
            $sql_insert_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
            executeQuery($pdo, $sql_insert_grupo, [$torneio_id, "2ª Fase - $serie_nome - Chaves", 200]);
            $grupo_chaves_id = (int)$pdo->lastInsertId();
        }
        
        // Semi-final 1: 1º A vs 2º B (cruzado)
        if ($tem_grupo_id_partidas) {
            if ($tem_tipo_fase) {
                $sql_semi1 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, ?, 'Agendada', 'Semi-Final')";
                executeQuery($pdo, $sql_semi1, [$torneio_id, $primeiro_a, $segundo_b, $grupo_chaves_id, $quadra_serie]);
            } else {
                $sql_semi1 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, ?, 'Agendada')";
                executeQuery($pdo, $sql_semi1, [$torneio_id, $primeiro_a, $segundo_b, $grupo_chaves_id, $quadra_serie]);
            }
            $partidas_inseridas++;
        }
        
        // Semi-final 2: 1º B vs 2º A (cruzado)
        if ($tem_grupo_id_partidas) {
            if ($tem_tipo_fase) {
                $sql_semi2 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status, tipo_fase) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, ?, 'Agendada', 'Semi-Final')";
                executeQuery($pdo, $sql_semi2, [$torneio_id, $primeiro_b, $segundo_a, $grupo_chaves_id, $quadra_serie]);
            } else {
                $sql_semi2 = "INSERT INTO torneio_partidas (torneio_id, time1_id, time2_id, fase, grupo_id, rodada, quadra, status) 
                             VALUES (?, ?, ?, '2ª Fase', ?, 1, ?, 'Agendada')";
                executeQuery($pdo, $sql_semi2, [$torneio_id, $primeiro_b, $segundo_a, $grupo_chaves_id, $quadra_serie]);
            }
            $partidas_inseridas++;
        }
    }
    
    if ($partidas_inseridas === 0) {
        throw new Exception("Nenhuma semi-final foi criada.");
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Semi-finais geradas com sucesso! Total de " . $partidas_inseridas . " partidas criadas.",
        'total_partidas' => $partidas_inseridas
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao gerar semi-finais: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao gerar semi-finais: ' . $e->getMessage()
    ]);
}
?>

