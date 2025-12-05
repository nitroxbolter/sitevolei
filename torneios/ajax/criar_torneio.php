<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Debug: Log de entrada
error_log("=== CRIAR TORNEIO DEBUG ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION user_id: " . ($_SESSION['user_id'] ?? 'não definido'));

if (!isLoggedIn()) {
    error_log("Erro: Usuário não está logado");
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Erro: Método não é POST");
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$nome = trim($_POST['nome'] ?? '');
$data_torneio = $_POST['data_torneio'] ?? '';
$tipo = $_POST['tipo'] ?? 'grupo';
$grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
$quantidade_participantes = isset($_POST['quantidade_participantes']) ? (int)$_POST['quantidade_participantes'] : null;
// Inscrições sempre fechadas na criação - podem ser abertas depois no gerenciamento
$inscricoes_abertas = 0;

error_log("Dados processados:");
error_log("  nome: " . $nome);
error_log("  data_torneio: " . $data_torneio);
error_log("  tipo: " . $tipo);
error_log("  grupo_id: " . ($grupo_id ?? 'null'));
error_log("  quantidade_participantes: " . ($quantidade_participantes ?? 'null'));

// Validar dados
if ($nome === '' || $data_torneio === '') {
    error_log("Erro de validação: campos obrigatórios não preenchidos");
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
    exit();
}

// Verificar se já existe um torneio com o mesmo nome em status aberto (não finalizado ou cancelado)
try {
    $sql_check = "SELECT id, nome, status FROM torneios WHERE nome = ? AND status NOT IN ('Finalizado', 'Cancelado')";
    $stmt_check = executeQuery($pdo, $sql_check, [$nome]);
    if ($stmt_check) {
        $torneio_existente = $stmt_check->fetch();
        if ($torneio_existente) {
            error_log("Erro: Torneio com nome '$nome' já existe com status '{$torneio_existente['status']}' (ID: {$torneio_existente['id']})");
            echo json_encode([
                'success' => false, 
                'message' => "Já existe um torneio com o nome '$nome' em andamento (Status: {$torneio_existente['status']}). Escolha outro nome ou finalize o torneio existente."
            ]);
            exit();
        }
    }
} catch (Exception $e) {
    error_log("Erro ao verificar torneio existente: " . $e->getMessage());
    // Continuar mesmo se houver erro na verificação para não bloquear criação
}

if ($tipo === 'grupo' && !$grupo_id) {
    error_log("Erro: tipo grupo mas sem grupo_id");
    echo json_encode(['success' => false, 'message' => 'Selecione um grupo para torneio do grupo.']);
    exit();
}

// Verificar se é admin do grupo (se for torneio do grupo)
if ($tipo === 'grupo' && $grupo_id) {
    $sql = "SELECT id, administrador_id FROM grupos WHERE id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$grupo_id]);
    $grupo = $stmt ? $stmt->fetch() : false;
    if (!$grupo) {
        error_log("Erro: Grupo não encontrado ou inativo (ID: $grupo_id)");
        echo json_encode(['success' => false, 'message' => 'Grupo não encontrado ou inativo.']);
        exit();
    }
    if ((int)$grupo['administrador_id'] !== (int)$_SESSION['user_id'] && !isAdmin($pdo, $_SESSION['user_id'])) {
        error_log("Erro: Usuário não é admin do grupo");
        echo json_encode(['success' => false, 'message' => 'Apenas o administrador do grupo pode criar torneios.']);
        exit();
    }
}

// Se for torneio avulso, garantir que grupo_id seja NULL
if ($tipo === 'avulso') {
    $grupo_id = null;
    error_log("Torneio avulso - grupo_id definido como NULL");
}

// Verificar se a tabela existe e quais colunas tem
try {
    $testQuery = $pdo->query("SHOW TABLES LIKE 'torneios'");
    if (!$testQuery || $testQuery->rowCount() == 0) {
        error_log("ERRO CRÍTICO: Tabela 'torneios' não existe no banco de dados!");
        echo json_encode([
            'success' => false, 
            'message' => 'Erro: Tabela de torneios não encontrada. Execute o script SQL primeiro.',
            'debug' => 'Tabela torneios não existe'
        ]);
        exit();
    }
    
    // Verificar quais colunas existem
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    error_log("Colunas existentes na tabela torneios: " . implode(', ', $columns));
    
    // Usar data_inicio (coluna existente)
    $campo_data = 'data_inicio';
    
    // Verificar se tipo existe
    $tem_tipo = in_array('tipo', $columns);
    if (!$tem_tipo) {
        error_log("Coluna 'tipo' não existe, será NULL");
    }
    
    // Verificar se quantidade_participantes existe, se não, usar max_participantes
    $tem_quantidade = in_array('quantidade_participantes', $columns);
    $tem_max_participantes = in_array('max_participantes', $columns);
    
    if (!$tem_quantidade && $tem_max_participantes) {
        error_log("Usando coluna max_participantes em vez de quantidade_participantes");
        $campo_quantidade = 'max_participantes';
    } elseif ($tem_quantidade) {
        $campo_quantidade = 'quantidade_participantes';
    } else {
        $campo_quantidade = null;
        error_log("Nenhuma coluna de quantidade de participantes encontrada");
    }
    
} catch (Exception $e) {
    error_log("Erro ao verificar tabela: " . $e->getMessage());
    $campo_data = 'data_inicio'; // Fallback
    $tem_tipo = false;
    $campo_quantidade = 'max_participantes'; // Fallback
}

// Criar torneio
try {
    // Montar SQL dinamicamente baseado nas colunas existentes
    $campos = ['nome', $campo_data];
    $valores = [$nome, $data_torneio];
    
    if ($tem_tipo) {
        $campos[] = 'tipo';
        $valores[] = $tipo;
    }
    
    // Incluir grupo_id apenas se a coluna existir
    // Para torneios avulsos, grupo_id deve ser NULL
    // Para torneios do grupo, grupo_id deve ser válido
    if (in_array('grupo_id', $columns)) {
        $campos[] = 'grupo_id';
        // Se for torneio avulso ou grupo_id for inválido, usar NULL
        if ($tipo === 'avulso' || !$grupo_id) {
            $valores[] = null;
            error_log("grupo_id definido como NULL (torneio avulso ou sem grupo)");
        } else {
            $valores[] = $grupo_id;
            error_log("grupo_id definido como: $grupo_id");
        }
    }
    
    // Não incluir quantidade_participantes na criação - será definido no gerenciamento
    // if ($campo_quantidade && $quantidade_participantes !== null) {
    //     $campos[] = $campo_quantidade;
    //     $valores[] = $quantidade_participantes;
    // }
    
    $campos[] = 'criado_por';
    $valores[] = $_SESSION['user_id'];
    
    // Verificar se status existe
    if (in_array('status', $columns)) {
        $campos[] = 'status';
        $valores[] = 'Criado';
    }
    
    // Sempre definir inscricoes_abertas como 0 na criação (podem ser abertas depois no gerenciamento)
    if (in_array('inscricoes_abertas', $columns)) {
        $campos[] = 'inscricoes_abertas';
        $valores[] = 0;
    }
    
    $placeholders = str_repeat('?,', count($campos) - 1) . '?';
    $sql = "INSERT INTO torneios (" . implode(', ', $campos) . ") VALUES ($placeholders)";
    
    error_log("SQL: " . $sql);
    error_log("Campos: " . implode(', ', $campos));
    error_log("Valores: " . print_r($valores, true));
    error_log("Valores detalhados:");
    foreach ($valores as $idx => $val) {
        error_log("  [$idx] " . ($val === null ? 'NULL' : $val) . " (tipo: " . gettype($val) . ")");
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($valores);
    } catch (PDOException $e) {
        error_log("Erro PDO ao executar: " . $e->getMessage());
        error_log("Código do erro: " . $e->getCode());
        error_log("Info do erro: " . print_r($stmt->errorInfo() ?? [], true));
        throw $e; // Re-lançar para ser capturado no catch externo
    }
    
    if ($result) {
        $torneio_id = (int)$pdo->lastInsertId();
        error_log("Sucesso: Torneio criado com ID $torneio_id");
        echo json_encode([
            'success' => true, 
            'message' => 'Torneio criado com sucesso!',
            'torneio_id' => $torneio_id
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Erro ao executar INSERT: " . print_r($errorInfo, true));
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao criar torneio.',
            'debug' => $errorInfo[2] ?? 'Erro desconhecido'
        ]);
    }
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    error_log("Código do erro: " . $e->getCode());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao criar torneio: ' . $e->getMessage(),
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Exception geral: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro inesperado: ' . $e->getMessage(),
        'debug' => $e->getMessage()
    ]);
}
?>

