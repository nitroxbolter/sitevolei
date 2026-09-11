<?php

const VOLLEYBALL_CAMPAIGN_LAST_OPPONENT = 6;

function volleyballCampaignDefaultState(bool $loggedIn = false): array
{
    return [
        'loggedIn' => $loggedIn,
        'teamName' => 'Meu Time',
        'opponentIndex' => 0,
        'wins' => 0,
        'losses' => 0,
        'campaignCompleted' => false,
    ];
}

function volleyballCampaignGetState(PDO $pdo, int $userId, string $defaultTeamName = 'Meu Time'): array
{
    $defaultTeamName = trim($defaultTeamName) ?: 'Meu Time';
    $defaultTeamName = mb_substr($defaultTeamName, 0, 30);

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO jogo_volei_progresso (usuario_id, nome_time) VALUES (?, ?)'
    );
    $stmt->execute([$userId, $defaultTeamName]);

    $stmt = $pdo->prepare(
        'SELECT nome_time, adversario_atual, vitorias, derrotas, campanha_concluida
         FROM jogo_volei_progresso
         WHERE usuario_id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Nao foi possivel carregar o progresso da campanha.');
    }

    return [
        'loggedIn' => true,
        'teamName' => (string) $row['nome_time'],
        'opponentIndex' => max(0, min(VOLLEYBALL_CAMPAIGN_LAST_OPPONENT, (int) $row['adversario_atual'])),
        'wins' => max(0, (int) $row['vitorias']),
        'losses' => max(0, (int) $row['derrotas']),
        'campaignCompleted' => (bool) $row['campanha_concluida'],
    ];
}

function volleyballCampaignDefaultTeamName(array $user): string
{
    $name = trim((string) ($user['nome'] ?? $user['usuario'] ?? ''));
    if ($name === '') {
        return 'Meu Time';
    }

    $firstName = preg_split('/\s+/u', $name, 2)[0] ?? $name;
    return mb_substr('Time ' . $firstName, 0, 30);
}

