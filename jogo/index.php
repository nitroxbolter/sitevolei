<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/campaign_helpers.php';

$voleiDebugAdmin = false;
$campaignState = volleyballCampaignDefaultState(isLoggedIn());
if (isLoggedIn()) {
    $voleiDebugUser = getUserById($pdo, (int) $_SESSION['user_id']);
    $voleiDebugAdmin = $voleiDebugUser && strcasecmp((string) ($voleiDebugUser['email'] ?? ''), 'admin@gmail.com') === 0;
    if ($voleiDebugUser) {
        try {
            $campaignState = volleyballCampaignGetState(
                $pdo,
                (int) $_SESSION['user_id'],
                volleyballCampaignDefaultTeamName($voleiDebugUser)
            );
        } catch (Throwable $error) {
            error_log('Erro ao carregar campanha de volei: ' . $error->getMessage());
        }
    }
}

if (!isset($_SESSION['volei_campaign_csrf'])) {
    $_SESSION['volei_campaign_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Spike Volleyball - O Jogo</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <!-- Phaser 3 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/phaser@3.60.0/dist/phaser-arcade-physics.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="rotate-device" aria-hidden="true">
        <div class="rotate-device-content">
            <strong>Gire o celular</strong>
            <span>O jogo usa a tela deitada para mostrar todos os comandos.</span>
        </div>
    </div>
    <div id="campaign-modal" class="campaign-modal" role="dialog" aria-modal="true" aria-labelledby="campaign-title">
        <div class="campaign-dialog">
            <section class="campaign-main">
                <span class="campaign-kicker">CAMPANHA MUNDOVOLEI</span>
                <h2 id="campaign-title">Próximo desafio</h2>

                <div class="campaign-team-editor">
                    <label for="player-team-name">Seu time</label>
                    <div class="campaign-team-edit-row">
                        <input id="player-team-name" type="text" minlength="2" maxlength="30" autocomplete="off"
                               <?php echo $campaignState['loggedIn'] ? '' : 'readonly'; ?>>
                        <?php if ($campaignState['loggedIn']): ?>
                            <button id="save-team-name" type="button">Salvar</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$campaignState['loggedIn']): ?>
                        <small>Entre na sua conta para salvar o nome e o progresso.</small>
                    <?php endif; ?>
                    <p id="team-name-feedback" class="campaign-feedback" aria-live="polite"></p>
                </div>

                <div class="campaign-stats" aria-label="Estatísticas da campanha">
                    <span><strong id="campaign-wins">0</strong> vitórias</span>
                    <span><strong id="campaign-losses">0</strong> derrotas</span>
                </div>

                <div class="campaign-current-opponent">
                    <span>Adversário atual</span>
                    <strong id="campaign-opponent-name">México</strong>
                    <em id="campaign-difficulty">Fácil</em>
                </div>

                <button id="start-campaign-match" class="campaign-start" type="button">Jogar contra México</button>
                <p id="campaign-feedback" class="campaign-feedback" aria-live="polite"></p>
            </section>

            <section class="campaign-route" aria-labelledby="campaign-route-title">
                <h3 id="campaign-route-title">Caminho dos adversários</h3>
                <ol id="campaign-opponents"></ol>
            </section>
        </div>
    </div>
    <div id="game-wrapper">
        <header>
            <a class="home-link" href="/" aria-label="Voltar para o site">‹</a>
            <h1>SPIKE VOLLEYBALL</h1>
            <div id="game-ui">
                <div class="matchup-names" aria-label="Times da partida">
                    <span id="player-team-label">Meu Time</span>
                    <b>×</b>
                    <span id="opponent-team-label">México</span>
                </div>
                <div class="score-container">
                    <div class="sets-container" aria-label="Sets">
                        <span>Sets</span>
                        <strong><span id="player-sets">0</span> - <span id="opponent-sets">0</span></strong>
                    </div>
                    <div class="points-container" aria-label="Pontos">
                        <span id="player-score">0</span> - <span id="opponent-score">0</span>
                    </div>
                </div>
            </div>
            <?php if ($voleiDebugAdmin): ?>
                <button id="debug-log-button" type="button" aria-label="Ver debug do jogo">DEBUG LOG</button>
            <?php endif; ?>
            <button id="fullscreen-button" type="button" aria-label="Tela cheia">TELA CHEIA</button>
        </header>
        
        <main id="game-container"></main>
        
        <footer>
            <div class="controls-guide">
                <div class="control-item"><span>L</span> Levantar (Segure/Solte)</div>
                <div class="control-item"><span>Ç</span> Manchete / ataque</div>
                <div class="control-item"><span>Espaço</span> Pular</div>
                <div class="control-item"><span>Setas/WASD</span> Movimentação</div>
            </div>
        </footer>
    </div>

    <?php if ($voleiDebugAdmin): ?>
        <div id="debug-log-modal" class="debug-log-modal" aria-hidden="true">
            <div class="debug-log-window" role="dialog" aria-modal="true" aria-labelledby="debug-log-title">
                <div class="debug-log-header">
                    <h2 id="debug-log-title">Log debug do jogo</h2>
                    <button id="debug-log-close" type="button" aria-label="Fechar log">×</button>
                </div>
                <pre id="debug-log-content">Nenhum log iniciado ainda.</pre>
                <div class="debug-log-actions">
                    <button id="debug-log-clear" type="button">Limpar log</button>
                    <button id="debug-log-refresh" type="button">Atualizar</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.VOLEI_DEBUG_ADMIN = <?php echo $voleiDebugAdmin ? 'true' : 'false'; ?>;
        window.VOLEI_CAMPAIGN = <?php echo json_encode($campaignState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        window.VOLEI_CAMPAIGN_CSRF = <?php echo json_encode($_SESSION['volei_campaign_csrf'], JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    </script>
    <script src="game.js?v=<?php echo time(); ?>"></script>
</body>
</html>
