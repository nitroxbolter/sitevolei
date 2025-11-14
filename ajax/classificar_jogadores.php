<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Método inválido']); exit(); }

$jogo_id = (int)($_POST['jogo_id'] ?? 0);
$itens = $_POST['avaliacoes'] ?? [];
if ($jogo_id <= 0 || !is_array($itens)) { echo json_encode(['success'=>false,'message'=>'Dados inválidos']); exit(); }

// Validar criador e se jogo já ocorreu
$stmt = executeQuery($pdo, "SELECT criado_por, data_jogo FROM jogos WHERE id = ?", [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : null;
if (!$jogo || (int)$jogo['criado_por'] !== (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Sem permissão']); exit(); }
if (strtotime($jogo['data_jogo']) > time()) { echo json_encode(['success'=>false,'message'=>'Avaliação apenas após o jogo']); exit(); }

$okAll = true;
foreach ($itens as $av) {
    $usuario_id = (int)($av['usuario_id'] ?? 0);
    $estrelas = (int)($av['estrelas'] ?? 0); // -5..5
    $motivo = trim($av['motivo'] ?? '');
    $observacoes = trim($av['observacoes'] ?? '');
    if ($usuario_id <= 0 || $usuario_id === (int)$_SESSION['user_id']) { continue; }

    // Tabela de penalidade/bonificação por categoria (prioritário sobre estrelas)
    $motivo_lc = mb_strtolower($motivo);
    $mapPontos = [
        // Negativos
        'nao foi' => -15,
        'não foi' => -15,
        'falta do jogo' => -15,
        'gravidade falta do jogo' => -15,
        'atitude negativa' => -10,
        'desrespeito' => -10,
        'atrasado' => -5,
        'chegou atrasado' => -5,
        'outro_negativo' => -2,
        // Positivos
        'jogador prestativo' => 4,
        'jogador alegre' => 2,
        'jogador normal' => 1,
        'outro_positivo' => 1,
    ];

    $pontos = null;
    foreach ($mapPontos as $chave => $valor) {
        if (strpos($motivo_lc, $chave) !== false) { $pontos = $valor; break; }
    }
    // Se não casou com categoria, usar estrelas (-10..+10)
    if ($pontos === null) {
        $pontos = max(-5, min(5, $estrelas)) * 2;
    }

    // Mapear tipo
    $tipo = ($pontos >= 0) ? 'Bom Jogador' : ((stripos($motivo_lc, 'falta') !== false || stripos($motivo_lc, 'nao foi') !== false || stripos($motivo_lc, 'não foi') !== false) ? 'Ausente' : 'Comportamento');

    // Evitar múltiplas avaliações do mesmo avaliador para o mesmo jogador e jogo
    $jaExiste = false;
    $stChk = executeQuery($pdo, "SELECT id FROM avaliacoes_reputacao WHERE avaliador_id = ? AND avaliado_id = ? AND jogo_id = ? LIMIT 1", [$_SESSION['user_id'], $usuario_id, $jogo_id]);
    if ($stChk && $stChk->fetch()) { $jaExiste = true; }

    $ins = true;
    if (!$jaExiste) {
        // Inserir avaliação
        $ins = executeQuery($pdo, "INSERT INTO avaliacoes_reputacao (avaliador_id, avaliado_id, jogo_id, tipo, pontos, observacoes) VALUES (?, ?, ?, ?, ?, ?)", [
            $_SESSION['user_id'], $usuario_id, $jogo_id, $tipo, $pontos, ($motivo ? ($motivo.($observacoes?(': '.$observacoes):'')) : $observacoes)
        ]);
    }
    if (!$ins) { $okAll = false; }

    // Atualizar reputação do avaliado de forma incremental (piso 0)
    executeQuery($pdo, "UPDATE usuarios SET reputacao = GREATEST(0, COALESCE(reputacao,0) + ?) WHERE id = ?", [$pontos, $usuario_id]);

    // Notificação (para todas as avaliações), se tabela existir
    if (true) {
        $hasNotifs = false;
        $stN = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
        if ($stN && $stN->fetch()) { $hasNotifs = true; }
        if ($hasNotifs) {
            $tituloNotif = ($pontos >= 0) ? 'Avaliação positiva' : 'Penalização em jogo';
            // Buscar título do jogo para mensagem
            $stmtJt = executeQuery($pdo, "SELECT titulo FROM jogos WHERE id = ?", [$jogo_id]);
            $jt = $stmtJt ? ($stmtJt->fetch()['titulo'] ?? '') : '';
            $acaoTxt = ($pontos >= 0) ? 'avaliado' : 'penalizado';
            $mensagem = 'Você foi ' . $acaoTxt . ' em ' . ($jt ?: ('#'.$jogo_id)) . ' (' . ($pontos >= 0 ? ('+' . $pontos) : $pontos) . ' pts) por ' . ($motivo ?: 'motivo não especificado') . '.';
            executeQuery($pdo, "INSERT INTO notificacoes (usuario_id, titulo, mensagem, lida) VALUES (?, ?, ?, 0)", [$usuario_id, $tituloNotif, $mensagem]);
        }
    }
}

echo json_encode(['success'=>$okAll]);
exit();
?>


