<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }

$jogo_id = (int)($_GET['jogo_id'] ?? 0);
$usuario_id = (int)($_GET['usuario_id'] ?? 0);
if ($jogo_id <= 0 || $usuario_id <= 0) { echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit(); }

// Apenas o criador do jogo pode consultar detalhes dos solicitantes
$stmt = executeQuery($pdo, "SELECT criado_por FROM jogos WHERE id = ?", [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : null;
if (!$jogo || (int)$jogo['criado_por'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['success'=>false,'message'=>'Sem permissão']);
    exit();
}

$stmtU = executeQuery($pdo, "SELECT id, nome, email, telefone, nivel, reputacao, foto_perfil FROM usuarios WHERE id = ?", [$usuario_id]);
$u = $stmtU ? $stmtU->fetch() : null;
if (!$u) { echo json_encode(['success'=>false,'message'=>'Usuário não encontrado']); exit(); }

echo json_encode(['success'=>true,'usuario'=>$u]);
exit();
?>


