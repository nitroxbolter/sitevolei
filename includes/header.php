<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo) ? $titulo . ' - ' : ''; ?>Comunidade do Vôlei</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS Customizado -->
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
    $css_path = $depth > 0 ? str_repeat('../', $depth) . 'assets/css/style.css' : 'assets/css/style.css';
    ?>
    <link href="<?php echo htmlspecialchars($css_path); ?>" rel="stylesheet">
    <!-- jQuery (necessário antes de scripts inline em páginas) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <?php if (isset($css_extra)): ?>
        <?php foreach ($css_extra as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <span class="navbar-brand">
                <img src="/assets/arquivos/logo.png" alt="Logo Comunidade do Vôlei" class="me-2" style="height:28px; width:auto;">
                Comunidade do Vôlei
            </span>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">
                            <i class="fas fa-home me-1"></i>Início
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isLoggedIn() ? '/dashboard.php' : '/dashboard_guest.php'; ?>">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/grupos/grupos.php">
                                <i class="fas fa-users me-1"></i>Grupos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/jogos/jogos.php">
                                <i class="fas fa-calendar-alt me-1"></i>Jogos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/torneios/torneios.php">
                                <i class="fas fa-trophy me-1"></i>Torneios
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/loja.php">
                            <i class="fas fa-shopping-cart me-1"></i>Loja
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/informacoes.php">
                            <i class="fas fa-info-circle me-1"></i>Informações
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Verificar se $pdo está disponível
                        $pdo_disponivel = isset($pdo) && $pdo instanceof PDO;
                        
                        // Se $pdo não estiver disponível, tentar incluir db_connect novamente
                        if (!$pdo_disponivel) {
                            // Tentar diferentes caminhos possíveis
                            $possible_paths = [
                                dirname(__DIR__) . '/includes/db_connect.php',
                                __DIR__ . '/db_connect.php',
                                '../includes/db_connect.php',
                                '../../includes/db_connect.php'
                            ];
                            foreach ($possible_paths as $db_connect_path) {
                                if (file_exists($db_connect_path)) {
                                    require_once $db_connect_path;
                                    $pdo_disponivel = isset($pdo) && $pdo instanceof PDO;
                                    break;
                                }
                            }
                        }
                        
                        // Garantir que temos um user_id válido na sessão
                        $user_id_session = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                        $usuario = false;
                        
                        if ($user_id_session > 0 && $pdo_disponivel) {
                            $usuario = getUserById($pdo, $user_id_session);
                            
                            // Se não encontrou o usuário (pode estar inativo), tentar buscar sem filtro de ativo
                            if (!$usuario || !is_array($usuario)) {
                                $sql_fallback = "SELECT * FROM usuarios WHERE id = ?";
                                $stmt_fallback = executeQuery($pdo, $sql_fallback, [$user_id_session]);
                                if ($stmt_fallback) {
                                    $usuario = $stmt_fallback->fetch();
                                    $stmt_fallback->closeCursor();
                                }
                            }
                        }
                        
                        // Se ainda não encontrou, usar valores padrão
                        if (!$usuario || !is_array($usuario)) {
                            $usuario = ['nome' => 'Usuário', 'reputacao' => 0];
                        }
                        // Contagem de notificações não lidas (se tabela existir)
                        $notifCount = 0;
                        try {
                            $stmtNotif = executeQuery($pdo, "SELECT COUNT(*) AS qt FROM notificacoes WHERE usuario_id = ? AND lida = 0", [$_SESSION['user_id']]);
                            if ($stmtNotif) { $rowN = $stmtNotif->fetch(); $notifCount = (int)($rowN['qt'] ?? 0); }
                        } catch (Exception $e) { /* tabela pode não existir */ }
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário'); ?>
                                <?php 
                                    $repHeader = (int)($usuario['reputacao'] ?? 0);
                                    $repHeaderClass = 'bg-danger';
                                    $repHeaderStyle = '';
                                    if ($repHeader > 75) { $repHeaderClass = 'bg-success'; }
                                    elseif ($repHeader > 50) { $repHeaderClass = 'bg-warning text-dark'; }
                                    elseif ($repHeader > 25) { $repHeaderClass = 'text-dark'; $repHeaderStyle = 'background-color:#fd7e14;'; }
                                ?>
                                <span class="badge ms-1 <?php echo $repHeaderClass; ?>" style="<?php echo $repHeaderStyle; ?>"><?php echo $repHeader; ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/perfil.php">
                                    <i class="fas fa-user-circle me-2"></i>Meu Perfil
                                </a></li>
                                <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="/notificacoes.php">
                                    <span><i class="fas fa-bell me-2"></i>Notificações</span>
                                    <?php if ($notifCount > 0): ?>
                                        <span class="badge bg-danger"><?php echo $notifCount; ?></span>
                                    <?php endif; ?>
                                </a></li>
                                <?php if (isAdmin($pdo, $_SESSION['user_id'])): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/admin/dashboard.php">
                                        <i class="fas fa-cogs me-2"></i>Painel Administrativo
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Sair
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Entrar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Cadastrar
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container mt-5 pt-5">
            <?php if (isset($_SESSION['mensagem'])): ?>
                <div class="alert alert-<?php echo $_SESSION['tipo_mensagem']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['mensagem']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php
                unset($_SESSION['mensagem']);
                unset($_SESSION['tipo_mensagem']);
                ?>
            <?php endif; ?>
