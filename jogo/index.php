<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

$voleiDebugAdmin = false;
if (isLoggedIn()) {
    $voleiDebugUser = getUserById($pdo, (int) $_SESSION['user_id']);
    $voleiDebugAdmin = $voleiDebugUser && strcasecmp((string) ($voleiDebugUser['email'] ?? ''), 'admin@gmail.com') === 0;
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
    <div id="difficulty-modal" class="difficulty-modal" role="dialog" aria-modal="true" aria-labelledby="difficulty-title">
        <div class="difficulty-dialog">
            <span class="difficulty-kicker">NOVA PARTIDA</span>
            <h2 id="difficulty-title">Escolha a dificuldade</h2>
            <div class="difficulty-options" role="group" aria-label="Dificuldade da partida">
                <button type="button" data-difficulty="easy">Fácil</button>
                <button type="button" data-difficulty="medium">Médio</button>
                <button type="button" data-difficulty="advanced">Avançado</button>
            </div>
        </div>
    </div>
    <div id="game-wrapper">
        <header>
            <a class="home-link" href="/" aria-label="Voltar para o site">‹</a>
            <h1>SPIKE VOLLEYBALL</h1>
            <div id="game-ui">
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
    </script>
    <script src="game.js?v=<?php echo time(); ?>"></script>
</body>
</html>
