<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Início';

// Obter próximos jogos e torneios
$proximos_jogos = getProximosJogos($pdo, 6);
$torneios_ativos = getTorneiosAtivos($pdo, 3);
$ranking = getRankingJogadores($pdo, 5);

// Estatísticas gerais
// Total de jogadores ativos
$sql_jogadores = "SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1";
$stmt_jogadores = executeQuery($pdo, $sql_jogadores);
$total_jogadores = $stmt_jogadores ? (int)$stmt_jogadores->fetch()['total'] : 0;

// Total de grupos ativos
$sql_grupos = "SELECT COUNT(*) as total FROM grupos WHERE ativo = 1";
$stmt_grupos = executeQuery($pdo, $sql_grupos);
$total_grupos = $stmt_grupos ? (int)$stmt_grupos->fetch()['total'] : 0;

// Total de jogos (todos, independente de status)
$sql_jogos = "SELECT COUNT(*) as total FROM jogos";
$stmt_jogos = executeQuery($pdo, $sql_jogos);
$total_jogos = $stmt_jogos ? (int)$stmt_jogos->fetch()['total'] : 0;

// Total de torneios (todos, independente de status)
$sql_torneios = "SELECT COUNT(*) as total FROM torneios";
$stmt_torneios = executeQuery($pdo, $sql_torneios);
$total_torneios = $stmt_torneios ? (int)$stmt_torneios->fetch()['total'] : 0;

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3 d-flex align-items-center">
                    <img src="assets/arquivos/logo.png" alt="Logo Comunidade do Vôlei" class="me-3" style="height:80px; width:auto; vertical-align:middle;">
                    <span class="text-nowrap">Comunidade do Vôlei</span>
                </h1>
                <p class="lead mb-4">
                    Conecte-se com jogadores e grupos de vôlei em Santa Maria. 
                    Encontre jogos, participe de torneios e faça parte da nossa comunidade!
                </p>
        <div class="d-flex gap-3">
            <a href="dashboard_guest.php" class="btn btn-light btn-lg">
                <i class="fas fa-eye me-2"></i>Explorar como Visitante
            </a>
            <?php if (!isLoggedIn()): ?>
                <a href="auth/login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Entrar
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-tachometer-alt me-2"></i>Meu Dashboard
                </a>
            <?php endif; ?>
        </div>
            </div>
            
        </div>
    </div>
</section>

<!-- Próximos Jogos -->
<section class="mb-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">
                    <i class="fas fa-calendar-alt me-2"></i>Próximos Jogos
                </h2>
            </div>
        </div>
        
        <?php if (empty($proximos_jogos)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum jogo agendado no momento.
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($proximos_jogos as $jogo): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-volleyball-ball me-2"></i>
                                    <?php echo htmlspecialchars($jogo['titulo']); ?>
                                </h5>
                                <p class="card-text">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>Grupo:</strong> <?php echo htmlspecialchars($jogo['grupo_nome']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Data:</strong> <?php echo formatarData($jogo['data_jogo']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <strong>Local:</strong> <?php echo htmlspecialchars($jogo['local']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>Vagas:</strong> <?php echo $jogo['vagas_disponiveis']; ?>/<?php echo $jogo['max_jogadores']; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo tempoRestante($jogo['data_jogo']); ?>
                                    </small>
                                    <?php if (isLoggedIn()): ?>
                                        <a href="jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-primary btn-sm">
                                            Ver Detalhes
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Torneios Ativos -->
<?php if (!empty($torneios_ativos)): ?>
<section class="mb-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">
                    <i class="fas fa-trophy me-2"></i>Torneios Ativos
                </h2>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($torneios_ativos as $torneio): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                <?php echo htmlspecialchars($torneio['nome']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <i class="fas fa-users me-2"></i>
                                <strong>Grupo:</strong> <?php echo htmlspecialchars($torneio['grupo_nome']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-calendar me-2"></i>
                                <strong>Início:</strong> <?php echo formatarData($torneio['data_inicio']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-user-friends me-2"></i>
                                <strong>Participantes:</strong> <?php echo $torneio['max_participantes']; ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 'info'; ?>">
                                    <?php echo $torneio['status']; ?>
                                </span>
                                <?php if (isLoggedIn()): ?>
                                    <a href="torneios/torneio.php?id=<?php echo $torneio['id']; ?>" class="btn btn-warning btn-sm">
                                        Ver Detalhes
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Ranking de Jogadores -->
<?php if (!empty($ranking)): ?>
<section class="mb-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">
                    <i class="fas fa-medal me-2"></i>Top Jogadores
                </h2>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Posição</th>
                                        <th>Jogador</th>
                                        <th>Posição</th>
                                        <th>Reputação</th>
                                        <th>Jogos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ranking as $index => $jogador): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index < 3): ?>
                                                    <i class="fas fa-medal text-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'bronze'); ?>"></i>
                                                <?php else: ?>
                                                    <?php echo $index + 1; ?>º
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($jogador['nome']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $jogador['posicao_preferida']; ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $repTop = (int)$jogador['reputacao']; 
                                                    $repTopClass = 'bg-danger';
                                                    $repTopStyle = '';
                                                    if ($repTop > 75) { $repTopClass = 'bg-success'; }
                                                    elseif ($repTop > 50) { $repTopClass = 'bg-warning text-dark'; }
                                                    elseif ($repTop > 25) { $repTopClass = 'text-dark'; $repTopStyle = 'background-color:#fd7e14;'; }
                                                ?>
                                                <span class="badge <?php echo $repTopClass; ?>" style="<?php echo $repTopStyle; ?>"><?php echo $repTop; ?> pts</span>
                                            </td>
                                            <td><?php echo $jogador['jogos_confirmados']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Estatísticas -->
<section class="bg-light py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <div class="card border-0 bg-primary text-white">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h3 class="card-title"><?php echo $total_jogadores; ?></h3>
                        <p class="card-text">Jogadores Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h3 class="card-title"><?php echo $total_grupos; ?></h3>
                        <p class="card-text">Grupos Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                        <h3 class="card-title"><?php echo $total_jogos; ?></h3>
                        <p class="card-text">Jogos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-trophy fa-3x mb-3"></i>
                        <h3 class="card-title"><?php echo $total_torneios; ?></h3>
                        <p class="card-text">Torneios</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
