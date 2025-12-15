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
$quantidade_grupos = (int)($_POST['quantidade_grupos'] ?? 0);

if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

if ($quantidade_grupos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantidade de grupos inválida.']);
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

// Buscar todos os times do torneio
$sql = "SELECT id, nome, cor, ordem FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

if (count($times) < 2) {
    echo json_encode(['success' => false, 'message' => 'É necessário pelo menos 2 times.']);
    exit();
}

$pdo->beginTransaction();

try {
    // Limpar grupos existentes
    $sql_limpar_grupos = "DELETE FROM torneio_grupo_times WHERE grupo_id IN (SELECT id FROM torneio_grupos WHERE torneio_id = ?)";
    executeQuery($pdo, $sql_limpar_grupos, [$torneio_id]);
    $sql_limpar = "DELETE FROM torneio_grupos WHERE torneio_id = ?";
    executeQuery($pdo, $sql_limpar, [$torneio_id]);
    
    // Função para converter número em letra
    $numeroParaLetra = function($numero) {
        return chr(64 + $numero); // 65 = 'A', 66 = 'B', etc.
    };
    
    // Criar grupos
    $grupos_criados = [];
    for ($g = 1; $g <= $quantidade_grupos; $g++) {
        $letra_grupo = $numeroParaLetra($g);
        $sql_grupo = "INSERT INTO torneio_grupos (torneio_id, nome, ordem) VALUES (?, ?, ?)";
        executeQuery($pdo, $sql_grupo, [$torneio_id, "Grupo " . $letra_grupo, $g]);
        $grupo_id = $pdo->lastInsertId();
        $grupos_criados[] = ['id' => $grupo_id, 'nome' => "Grupo " . $letra_grupo, 'ordem' => $g];
    }
    
    // Embaralhar times
    shuffle($times);
    
    // Dividir times igualmente entre os grupos
    $total_times = count($times);
    $times_por_grupo = floor($total_times / $quantidade_grupos);
    $times_restantes = $total_times % $quantidade_grupos;
    
    $indice_time = 0;
    foreach ($grupos_criados as $grupo) {
        // Calcular quantos times vão para este grupo
        $qtd_times_grupo = $times_por_grupo;
        if ($times_restantes > 0) {
            $qtd_times_grupo++;
            $times_restantes--;
        }
        
        // Adicionar times ao grupo
        for ($t = 0; $t < $qtd_times_grupo && $indice_time < $total_times; $t++) {
            $sql_add_time = "INSERT INTO torneio_grupo_times (grupo_id, time_id) VALUES (?, ?)";
            executeQuery($pdo, $sql_add_time, [$grupo['id'], $times[$indice_time]['id']]);
            $indice_time++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Times sorteados automaticamente nos grupos!'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao sortear grupos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao sortear grupos: ' . $e->getMessage()
    ]);
}
?>

