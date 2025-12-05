<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit();
}

$sistema_id = isset($_POST['sistema_id']) ? (int)$_POST['sistema_id'] : 0;
$campo = isset($_POST['campo']) ? trim($_POST['campo']) : '';
$valor = isset($_POST['valor']) ? trim($_POST['valor']) : '';

if ($sistema_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sistema inválido.']);
    exit();
}

// Verificar se o campo é permitido
$campos_permitidos = ['data_final', 'quantidade_jogos'];
if (!in_array($campo, $campos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Campo não permitido.']);
    exit();
}

// Buscar sistema
$sql = "SELECT sp.*, g.administrador_id 
        FROM sistemas_pontuacao sp 
        JOIN grupos g ON g.id = sp.grupo_id 
        WHERE sp.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$sistema_id]);
$sistema = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sistema) {
    echo json_encode(['success' => false, 'message' => 'Sistema não encontrado.']);
    exit();
}

// Verificar permissão (admin do grupo ou admin geral)
$usuario_id = (int)$_SESSION['user_id'];
$sou_admin_grupo = ((int)$sistema['administrador_id'] === $usuario_id);
if (!$sou_admin_grupo && !isAdmin($pdo, $usuario_id)) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para editar.']);
    exit();
}

try {
    // Validar e processar valor
    if ($campo === 'data_final') {
        // Validar formato de data
        $data = DateTime::createFromFormat('Y-m-d', $valor);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Data inválida.']);
            exit();
        }
        
        // Verificar se data final é posterior à data inicial
        $data_inicial = new DateTime($sistema['data_inicial']);
        if ($data < $data_inicial) {
            echo json_encode(['success' => false, 'message' => 'Data final deve ser posterior à data inicial.']);
            exit();
        }
        
        $valor_final = $data->format('Y-m-d');
    } elseif ($campo === 'quantidade_jogos') {
        $valor_final = (int)$valor;
        if ($valor_final < 1) {
            echo json_encode(['success' => false, 'message' => 'Total de jogos deve ser maior que zero.']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Campo inválido.']);
        exit();
    }
    
    // Atualizar no banco
    $sql = "UPDATE sistemas_pontuacao SET {$campo} = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$valor_final, $sistema_id]);
    
    echo json_encode(['success' => true, 'message' => 'Campo atualizado com sucesso!']);
} catch (PDOException $e) {
    error_log("Erro ao editar sistema de pontuação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar.']);
}

