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
$torneio_id = (int)($_GET['torneio_id'] ?? 0);

if ($usuario_id <= 0 || $torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit();
}

if (!podeGerenciarTorneio($pdo, $torneio_id, $_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

$sql_vinculo = "SELECT 1
                FROM usuarios u
                WHERE u.id = ?
                  AND (
                    EXISTS (
                        SELECT 1 FROM torneio_participantes tp
                        WHERE tp.torneio_id = ? AND tp.usuario_id = u.id
                    )
                    OR EXISTS (
                        SELECT 1 FROM torneio_solicitacoes ts
                        WHERE ts.torneio_id = ? AND ts.usuario_id = u.id
                    )
                  )";
$stmt_vinculo = executeQuery($pdo, $sql_vinculo, [$usuario_id, $torneio_id, $torneio_id]);
if (!$stmt_vinculo || !$stmt_vinculo->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não vinculado a este torneio.']);
    exit();
}

// Retorna somente os dados necessários para o organizador contatar o participante.
$sql = "SELECT id, nome, email, telefone, foto_perfil, nivel, reputacao, data_cadastro
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

