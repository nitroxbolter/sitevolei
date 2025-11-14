<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Estatísticas do Sistema';
requireLogin();

// Verificar se é administrador
$sql = "SELECT COUNT(*) as is_admin FROM grupos WHERE administrador_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$result = $stmt ? $stmt->fetch() : false;

if (!$result || $result['is_admin'] == 0) {
    header('Location: ../dashboard.php');
    exit();
}

// Estatísticas gerais
$sql = "SELECT 
            COUNT(DISTINCT u.id) as total_usuarios,
            COUNT(DISTINCT g.id) as total_grupos,
            COUNT(DISTINCT j.id) as total_jogos,
            COUNT(DISTINCT t.id) as total_torneios,
            COUNT(DISTINCT CASE WHEN u.data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as usuarios_30_dias,
            COUNT(DISTINCT CASE WHEN j.data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN j.id END) as jogos_30_dias
        FROM usuarios u
        LEFT JOIN grupos g ON 1=1
        LEFT JOIN jogos j ON 1=1
        LEFT JOIN torneios t ON 1=1
        WHERE u.ativo = 1";
$stmt = executeQuery($pdo, $sql);
$estatisticas_gerais = $stmt ? $stmt->fetch() : [];

// Usuários por nível
$sql = "SELECT nivel, COUNT(*) as total FROM usuarios WHERE ativo = 1 GROUP BY nivel ORDER BY total DESC";
$stmt = executeQuery($pdo, $sql);
$usuarios_por_nivel = $stmt ? $stmt->fetchAll() : [];

// Usuários por posição
$sql = "SELECT posicao_preferida, COUNT(*) as total FROM usuarios WHERE ativo = 1 GROUP BY posicao_preferida ORDER BY total DESC";
$stmt = executeQuery($pdo, $sql);
$usuarios_por_posicao = $stmt ? $stmt->fetchAll() : [];

// Jogos por status
$sql = "SELECT status, COUNT(*) as total FROM jogos GROUP BY status ORDER BY total DESC";
$stmt = executeQuery($pdo, $sql);
$jogos_por_status = $stmt ? $stmt->fetchAll() : [];

// Torneios por status
$sql = "SELECT status, COUNT(*) as total FROM torneios GROUP BY status ORDER BY total DESC";
$stmt = executeQuery($pdo, $sql);
$torneios_por_status = $stmt ? $stmt->fetchAll() : [];

// Top 10 usuários por reputação
$sql = "SELECT nome, reputacao, posicao_preferida, nivel 
        FROM usuarios 
        WHERE ativo = 1 
        ORDER BY reputacao DESC 
        LIMIT 10";
$stmt = executeQuery($pdo, $sql);
$top_usuarios = $stmt ? $stmt->fetchAll() : [];

// Atividade mensal (últimos 12 meses)
$sql = "SELECT 
            DATE_FORMAT(data_cadastro, '%Y-%m') as mes,
            COUNT(*) as usuarios
        FROM usuarios 
        WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_cadastro, '%Y-%m')
        ORDER BY mes";
$stmt = executeQuery($pdo, $sql);
$atividade_mensal = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-chart-bar me-2"></i>Estatísticas do Sistema
            </h2>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Imprimir Relatório
            </button>
        </div>
    </div>
</div>

<!-- Estatísticas Principais -->
<div class="row mb-4">
    <div class="col-md-2 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['total_usuarios']; ?></h3>
                <p class="mb-0">Usuários</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['total_grupos']; ?></h3>
                <p class="mb-0">Grupos</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['total_jogos']; ?></h3>
                <p class="mb-0">Jogos</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-trophy fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['total_torneios']; ?></h3>
                <p class="mb-0">Torneios</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-plus fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['usuarios_30_dias']; ?></h3>
                <p class="mb-0">Últimos 30 dias</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <i class="fas fa-calendar fa-2x mb-2"></i>
                <h3><?php echo $estatisticas_gerais['jogos_30_dias']; ?></h3>
                <p class="mb-0">Jogos 30 dias</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Usuários por Nível -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>Usuários por Nível
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($usuarios_por_nivel as $nivel): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo $nivel['nivel']; ?></span>
                        <div class="d-flex align-items-center">
                            <div class="progress me-2" style="width: 100px; height: 20px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo ($nivel['total'] / $estatisticas_gerais['total_usuarios']) * 100; ?>%">
                                </div>
                            </div>
                            <span class="badge bg-primary"><?php echo $nivel['total']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Usuários por Posição -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-volleyball-ball me-2"></i>Usuários por Posição
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($usuarios_por_posicao as $posicao): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo $posicao['posicao_preferida']; ?></span>
                        <div class="d-flex align-items-center">
                            <div class="progress me-2" style="width: 100px; height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo ($posicao['total'] / $estatisticas_gerais['total_usuarios']) * 100; ?>%">
                                </div>
                            </div>
                            <span class="badge bg-success"><?php echo $posicao['total']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Jogos por Status -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Jogos por Status
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($jogos_por_status as $status): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo $status['status']; ?></span>
                        <span class="badge bg-<?php echo $status['status'] === 'Aberto' ? 'success' : ($status['status'] === 'Fechado' ? 'danger' : 'info'); ?>">
                            <?php echo $status['total']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Torneios por Status -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Torneios por Status
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($torneios_por_status as $status): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo $status['status']; ?></span>
                        <span class="badge bg-<?php echo $status['status'] === 'Inscrições Abertas' ? 'success' : ($status['status'] === 'Em Andamento' ? 'warning' : 'info'); ?>">
                            <?php echo $status['total']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Usuários -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-medal me-2"></i>Top 10 Usuários por Reputação
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th>Nome</th>
                                <th>Reputação</th>
                                <th>Posição</th>
                                <th>Nível</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_usuarios as $index => $usuario): ?>
                                <tr>
                                    <td>
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-medal text-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'bronze'); ?>"></i>
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>º
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $usuario['reputacao']; ?> pts</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $usuario['posicao_preferida']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $usuario['nivel'] === 'Profissional' ? 'danger' : ($usuario['nivel'] === 'Avançado' ? 'warning' : ($usuario['nivel'] === 'Intermediário' ? 'info' : 'secondary')); ?>">
                                            <?php echo $usuario['nivel']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .card-header .btn {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
