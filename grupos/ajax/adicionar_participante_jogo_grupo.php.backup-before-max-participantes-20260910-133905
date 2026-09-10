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
$usuario_id = (int)($_POST['usuario_id'] ?? 0);

if ($jogo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jogo inválido.']);
    exit();
}

// Se não foi fornecido usuario_id, usar o usuário logado (caso de auto-inscrição)
if ($usuario_id <= 0) {
    $usuario_id = (int)$_SESSION['user_id'];
}

if ($usuario_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Usuário inválido.']);
    exit();
}

// Verificar se o jogo existe e se a lista está aberta
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

// Verificar permissões
$usuario_logado_id = (int)$_SESSION['user_id'];
$e_admin_grupo = ((int)$jogo['administrador_id'] === $usuario_logado_id);
$e_admin_site = isAdmin($pdo, $usuario_logado_id);

// Se o usuário logado está tentando adicionar a si mesmo
if ($usuario_id === $usuario_logado_id) {
    // Verificar se é membro do grupo
    $sql = "SELECT id FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$jogo['grupo_id'], $usuario_logado_id]);
    $e_membro = $stmt ? ($stmt->fetch() !== false) : false;
    
    if (!$e_membro && !$e_admin_grupo && !$e_admin_site) {
        echo json_encode(['success' => false, 'message' => 'Você não é membro deste grupo.']);
        exit();
    }
} else {
    // Se está tentando adicionar outro usuário, precisa ser admin
    if (!$e_admin_grupo && !$e_admin_site) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem adicionar outros membros.']);
        exit();
    }
    
    // Verificar se o usuário sendo adicionado é membro do grupo
    $sql = "SELECT id FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$jogo['grupo_id'], $usuario_id]);
    $usuario_e_membro = $stmt ? ($stmt->fetch() !== false) : false;
    
    if (!$usuario_e_membro) {
        echo json_encode(['success' => false, 'message' => 'O usuário selecionado não é membro deste grupo.']);
        exit();
    }
}

// Verificar se a lista está aberta
if ($jogo['lista_aberta'] != 1 || $jogo['status'] !== 'Lista Aberta') {
    echo json_encode(['success' => false, 'message' => 'A lista de participantes está fechada.']);
    exit();
}

// Verificar se já está inscrito
$sql = "SELECT id FROM grupo_jogo_participantes WHERE jogo_id = ? AND usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Este usuário já está inscrito neste jogo.']);
    exit();
}

// Adicionar participante
$sql = "INSERT INTO grupo_jogo_participantes (jogo_id, usuario_id) VALUES (?, ?)";
$result = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Você entrou no jogo com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao entrar no jogo.']);
}
?>

