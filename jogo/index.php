<?php
/**
 * Spike Volleyball Game - Main Entry Point
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spike Volleyball - O Jogo</title>
    <link rel="stylesheet" href="style.css">
    <!-- Phaser 3 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/phaser@3.60.0/dist/phaser-arcade-physics.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="game-wrapper">
        <header>
            <h1>SPIKE VOLLEYBALL</h1>
            <div id="game-ui">
                <div class="score-container">
                    <span id="player-score">0</span> - <span id="opponent-score">0</span>
                </div>
            </div>
        </header>
        
        <main id="game-container"></main>
        
        <footer>
            <div class="controls-guide">
                <div class="control-item"><span>L</span> Levantar (Segure/Solte)</div>
                <div class="control-item"><span>A</span> Bater (No timing)</div>
                <div class="control-item"><span>Setas</span> Movimentação</div>
            </div>
        </footer>
    </div>

    <script src="game.js?v=<?php echo time(); ?>"></script>
</body>
</html>
