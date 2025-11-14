<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$usuario_id = (int)($_POST['usuario_id'] ?? 0);
if ($jogo_id <= 0 || $usuario_id <= 0) { echo json_encode(['success'=>false,'message'=>'Parâmetros inválidos']); exit(); }

// Verificar se solicitante é criador
$stmt = executeQuery($pdo, "SELECT criado_por FROM jogos WHERE id = ?", [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : null;
if (!$jogo || (int)$jogo['criado_por'] !== (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Sem permissão']); exit(); }

// Rejeitar (marca Ausente ou remove?) Vamos apenas atualizar para 'Pendente'->'Ausente'
$ok = executeQuery($pdo, "UPDATE confirmacoes_presenca SET status='Ausente' WHERE jogo_id = ? AND usuario_id = ?", [$jogo_id, $usuario_id]);

echo json_encode(['success'=>(bool)$ok]);
exit();
?>


