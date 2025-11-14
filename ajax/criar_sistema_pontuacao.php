<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

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
$nome = trim($_POST['nome'] ?? '');
$data_inicial = $_POST['data_inicial'] ?? '';
$data_final = $_POST['data_final'] ?? '';
$quantidade_jogos = (int)($_POST['quantidade_jogos'] ?? 0);

// Validar dados
if ($grupo_id <= 0 || $nome === '' || $data_inicial === '' || $data_final === '' || $quantidade_jogos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit();
}

// Validar datas
if (strtotime($data_inicial) > strtotime($data_final)) {
    echo json_encode(['success' => false, 'message' => 'A data final deve ser posterior à data inicial.']);
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT administrador_id FROM grupos WHERE id = ?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    echo json_encode(['success' => false, 'message' => 'Grupo não encontrado.']);
    exit();
}

$sou_admin = ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Apenas o administrador do grupo pode criar sistemas de pontuação.']);
    exit();
}

// Verificar se já existe sistema ativo
$sql = "SELECT id FROM sistemas_pontuacao WHERE grupo_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Já existe um sistema de pontuação ativo para este grupo.']);
    exit();
}

// Criar sistema
$sql = "INSERT INTO sistemas_pontuacao (grupo_id, nome, data_inicial, data_final, quantidade_jogos) VALUES (?, ?, ?, ?, ?)";
$result = executeQuery($pdo, $sql, [$grupo_id, $nome, $data_inicial, $data_final, $quantidade_jogos]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Sistema de pontuação criado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar sistema de pontuação.']);
}
?>

