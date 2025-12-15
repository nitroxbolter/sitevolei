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

$grupo_id = (int)($_POST['grupo_id'] ?? 0);
$nome = sanitizar($_POST['nome'] ?? '');
$descricao = sanitizar($_POST['descricao'] ?? '');
$data_jogo = $_POST['data_jogo'] ?? '';
$local = sanitizar($_POST['local'] ?? '');

if ($grupo_id <= 0 || empty($nome) || empty($data_jogo)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
    exit();
}

// Verificar se o usuário é admin do grupo
$sql = "SELECT administrador_id FROM grupos WHERE id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;

if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo não encontrado.']);
    exit();
}

$sou_admin = ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Criar jogo
$sql = "INSERT INTO grupo_jogos (grupo_id, nome, descricao, data_jogo, local, status, lista_aberta, criado_por) 
        VALUES (?, ?, ?, ?, ?, 'Lista Aberta', 1, ?)";
$result = executeQuery($pdo, $sql, [$grupo_id, $nome, $descricao, $data_jogo, $local, $_SESSION['user_id']]);

if ($result) {
    $jogo_id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'message' => 'Jogo criado com sucesso!', 'jogo_id' => $jogo_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar jogo.']);
}
?>

