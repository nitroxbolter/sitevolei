<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$usuario_id = (int)($_GET['usuario_id'] ?? 0);

if ($usuario_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Usuário inválido.']);
    exit();
}

// Buscar detalhes do usuário
$sql = "SELECT id, nome, email, telefone, foto_perfil, nivel, genero, data_aniversario, reputacao, data_cadastro
        FROM usuarios
        WHERE id = ?";
$stmt = executeQuery($pdo, $sql, [$usuario_id]);
$usuario = $stmt ? $stmt->fetch() : false;

if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    exit();
}

echo json_encode(['success' => true, 'usuario' => $usuario]);
?>

