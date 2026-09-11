<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/campaign_helpers.php';

function campaignRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    campaignRespond(['ok' => false, 'error' => 'usuario nao logado'], 401);
}

$userId = (int) $_SESSION['user_id'];
$user = getUserById($pdo, $userId);
if ($userId <= 0 || !$user) {
    campaignRespond(['ok' => false, 'error' => 'sessao invalida'], 401);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        campaignRespond([
            'ok' => true,
            'state' => volleyballCampaignGetState($pdo, $userId, volleyballCampaignDefaultTeamName($user)),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        campaignRespond(['ok' => false, 'error' => 'metodo nao permitido'], 405);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        campaignRespond(['ok' => false, 'error' => 'dados invalidos'], 400);
    }

    $csrf = (string) ($data['csrf'] ?? '');
    if ($csrf === '' || !isset($_SESSION['volei_campaign_csrf']) ||
        !hash_equals((string) $_SESSION['volei_campaign_csrf'], $csrf)) {
        campaignRespond(['ok' => false, 'error' => 'sessao expirada, recarregue a pagina'], 403);
    }

    $action = (string) ($data['action'] ?? '');
    volleyballCampaignGetState($pdo, $userId, volleyballCampaignDefaultTeamName($user));

    if ($action === 'rename') {
        $teamName = trim((string) ($data['teamName'] ?? ''));
        $teamName = preg_replace('/[\x00-\x1F\x7F]/u', '', $teamName) ?? '';
        $length = mb_strlen($teamName);
        if ($length < 2 || $length > 30) {
            campaignRespond(['ok' => false, 'error' => 'use um nome entre 2 e 30 caracteres'], 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE jogo_volei_progresso SET nome_time = ?, atualizado_em = CURRENT_TIMESTAMP WHERE usuario_id = ?'
        );
        $stmt->execute([$teamName, $userId]);

        campaignRespond([
            'ok' => true,
            'state' => volleyballCampaignGetState($pdo, $userId, $teamName),
        ]);
    }

    if ($action === 'start') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT adversario_atual FROM jogo_volei_progresso WHERE usuario_id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Progresso nao encontrado.');
        }

        $opponentIndex = max(0, min(VOLLEYBALL_CAMPAIGN_LAST_OPPONENT, (int) $row['adversario_atual']));
        $matchToken = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            'UPDATE jogo_volei_progresso
             SET partida_token = ?, partida_adversario = ?, partida_iniciada_em = CURRENT_TIMESTAMP,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE usuario_id = ?'
        );
        $stmt->execute([$matchToken, $opponentIndex, $userId]);
        $pdo->commit();

        campaignRespond([
            'ok' => true,
            'matchToken' => $matchToken,
            'opponentIndex' => $opponentIndex,
        ]);
    }

    if ($action === 'result') {
        $winner = (string) ($data['winner'] ?? '');
        $playerSets = filter_var($data['playerSets'] ?? null, FILTER_VALIDATE_INT);
        $opponentSets = filter_var($data['opponentSets'] ?? null, FILTER_VALIDATE_INT);
        $matchToken = (string) ($data['matchToken'] ?? '');

        $validScore = in_array($winner, ['PLAYER', 'OPPONENT'], true)
            && $playerSets !== false && $opponentSets !== false
            && $playerSets >= 0 && $playerSets <= 3
            && $opponentSets >= 0 && $opponentSets <= 3
            && (($winner === 'PLAYER' && $playerSets === 3 && $opponentSets < 3)
                || ($winner === 'OPPONENT' && $opponentSets === 3 && $playerSets < 3));

        if (!$validScore || !preg_match('/^[a-f0-9]{64}$/', $matchToken)) {
            campaignRespond(['ok' => false, 'error' => 'resultado invalido'], 422);
        }

        $legacyPointsColumn = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'pontos_jogo_volei'")->fetch(PDO::FETCH_ASSOC);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT adversario_atual, partida_token, partida_adversario, campanha_concluida
             FROM jogo_volei_progresso
             WHERE usuario_id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['partida_token'] || !hash_equals((string) $row['partida_token'], $matchToken)) {
            $pdo->rollBack();
            campaignRespond(['ok' => false, 'error' => 'partida ja contabilizada ou expirada'], 409);
        }

        $currentOpponent = max(0, min(VOLLEYBALL_CAMPAIGN_LAST_OPPONENT, (int) $row['adversario_atual']));
        if ((int) $row['partida_adversario'] !== $currentOpponent) {
            $pdo->rollBack();
            campaignRespond(['ok' => false, 'error' => 'adversario da partida nao confere'], 409);
        }

        $playerWon = $winner === 'PLAYER';
        $nextOpponent = $playerWon
            ? min(VOLLEYBALL_CAMPAIGN_LAST_OPPONENT, $currentOpponent + 1)
            : $currentOpponent;
        $completed = (bool) $row['campanha_concluida']
            || ($playerWon && $currentOpponent === VOLLEYBALL_CAMPAIGN_LAST_OPPONENT);

        $stmt = $pdo->prepare(
            'UPDATE jogo_volei_progresso
             SET adversario_atual = ?,
                 vitorias = vitorias + ?, derrotas = derrotas + ?,
                 campanha_concluida = ?, partida_token = NULL, partida_adversario = NULL,
                 ultima_partida_em = CURRENT_TIMESTAMP, atualizado_em = CURRENT_TIMESTAMP
             WHERE usuario_id = ?'
        );
        $stmt->execute([
            $nextOpponent,
            $playerWon ? 1 : 0,
            $playerWon ? 0 : 1,
            $completed ? 1 : 0,
            $userId,
        ]);

        if ($playerWon && $legacyPointsColumn) {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET pontos_jogo_volei = COALESCE(pontos_jogo_volei, 0) + 1 WHERE id = ? AND ativo = 1'
            );
            $stmt->execute([$userId]);
        }

        $pdo->commit();
        campaignRespond([
            'ok' => true,
            'state' => volleyballCampaignGetState($pdo, $userId, volleyballCampaignDefaultTeamName($user)),
        ]);
    }

    campaignRespond(['ok' => false, 'error' => 'acao invalida'], 400);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro na campanha do jogo de volei: ' . $error->getMessage());
    campaignRespond(['ok' => false, 'error' => 'nao foi possivel salvar a campanha'], 500);
}
