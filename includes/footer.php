        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-volleyball-ball me-2"></i>Comunidade do Vôlei</h5>
                    <p class="mb-0">Conectando jogadores e grupos de vôlei em Santa Maria</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        <i class="fas fa-envelope me-1"></i>
                        eduardogaier@gmail.com
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-phone me-1"></i>
                        (55) 991773439
                    </p>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Comunidade do Vôlei. Todos os direitos reservados.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JS Customizado -->
    <?php
    // Detectar profundidade da subpasta para calcular caminho correto
    $script_path = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_path);
    // Remover barra inicial se existir e normalizar
    $dir_clean = trim($script_dir, '/\\');
    // Contar quantos níveis de profundidade temos (separadores / ou \)
    $depth = 0;
    if (!empty($dir_clean)) {
        // Contar separadores de diretório e adicionar 1 para cada nível
        // Exemplo: "torneios/admin" tem 1 separador, então depth = 2 (torneios e admin)
        $separadores = substr_count($dir_clean, '/') + substr_count($dir_clean, '\\');
        $depth = $separadores + 1; // +1 porque cada separador indica um nível adicional
    }
    // Construir caminho relativo baseado na profundidade
    $js_path = $depth > 0 ? str_repeat('../', $depth) . 'assets/js/main.js' : 'assets/js/main.js';
    ?>
    <script src="<?php echo htmlspecialchars($js_path); ?>"></script>
    
    <?php if (isset($js_extra)): ?>
        <?php foreach ($js_extra as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
