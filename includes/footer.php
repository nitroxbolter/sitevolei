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
                        contato@comunidadevolei.com.br
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-phone me-1"></i>
                        (55) 99999-9999
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
    // Detectar se estamos em uma subpasta (como admin/)
    // Se o arquivo que incluiu o footer está em uma subpasta, ajustar o caminho
    $script_path = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_path);
    // Remove barras iniciais e finais, se o resultado não estiver vazio, estamos em uma subpasta
    $dir_clean = trim($script_dir, '/');
    $is_subdir = !empty($dir_clean) && $script_dir !== '/';
    $js_path = $is_subdir ? '../assets/js/main.js' : 'assets/js/main.js';
    ?>
    <script src="<?php echo htmlspecialchars($js_path); ?>"></script>
    
    <?php if (isset($js_extra)): ?>
        <?php foreach ($js_extra as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
