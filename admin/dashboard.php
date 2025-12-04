<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Painel Administrativo';
requireAdmin($pdo);

// Estatísticas gerais
$sql = "SELECT 
            COUNT(DISTINCT u.id) as total_usuarios,
            COUNT(DISTINCT g.id) as total_grupos,
            COUNT(DISTINCT j.id) as total_jogos,
            COUNT(DISTINCT t.id) as total_torneios
        FROM usuarios u
        LEFT JOIN grupos g ON 1=1
        LEFT JOIN jogos j ON 1=1
        LEFT JOIN torneios t ON 1=1
        WHERE u.ativo = 1";
$stmt = executeQuery($pdo, $sql);
$estatisticas = $stmt ? $stmt->fetch() : [];

// Jogos recentes (site)
$sql = "SELECT j.*, g.nome as grupo_nome, u.nome as criado_por_nome
        FROM jogos j
        JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        ORDER BY j.data_criacao DESC
        LIMIT 10";
$stmt = executeQuery($pdo, $sql);
$jogos_recentes = $stmt ? $stmt->fetchAll() : [];

// Torneios recentes (site)
$sql = "SELECT t.*, g.nome as grupo_nome, u.nome as criado_por_nome
        FROM torneios t
        JOIN grupos g ON t.grupo_id = g.id
        LEFT JOIN usuarios u ON t.criado_por = u.id
        ORDER BY t.data_criacao DESC
        LIMIT 5";
$stmt = executeQuery($pdo, $sql);
$torneios_recentes = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-cogs me-2"></i>Painel Administrativo
            </h2>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas Gerais -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x mb-3"></i>
                <h3><?php echo $estatisticas['total_usuarios']; ?></h3>
                <p class="mb-0">Usuários Ativos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x mb-3"></i>
                <h3><?php echo $estatisticas['total_grupos']; ?></h3>
                <p class="mb-0">Grupos Ativos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                <h3><?php echo $estatisticas['total_jogos']; ?></h3>
                <p class="mb-0">Jogos Criados</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-trophy fa-3x mb-3"></i>
                <h3><?php echo $estatisticas['total_torneios']; ?></h3>
                <p class="mb-0">Torneios Criados</p>
            </div>
        </div>
    </div>
</div>

<!-- (Seção de Meus Grupos removida para o painel do site) -->

<!-- Ações Rápidas -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3">
            <i class="fas fa-bolt me-2"></i>Ações Rápidas
        </h4>
        <div class="row">
            <div class="col-md-3 mb-3">
                <a href="usuarios.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                    <i class="fas fa-users fa-3x mb-3"></i>
                    <span>Gerenciar Usuários</span>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="grupos_jogos.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                    <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                    <span>Grupos e Jogos</span>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="../tools/estatisticas.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                    <span>Estatísticas</span>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="../tools/backup.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-4">
                    <i class="fas fa-database fa-3x mb-3"></i>
                    <span>Backup</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Jogos Recentes -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Jogos Recentes
                </h5>
                <a href="gerenciar_jogos.php" class="btn btn-sm btn-outline-primary">
                    Ver Todos
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($jogos_recentes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum jogo criado ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Grupo</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos_recentes as $jogo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($jogo['titulo']); ?></td>
                                        <td><?php echo htmlspecialchars($jogo['grupo_nome']); ?></td>
                                        <td><?php echo formatarData($jogo['data_jogo']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $jogo['status'] === 'Aberto' ? 'success' : ($jogo['status'] === 'Fechado' ? 'danger' : 'info'); ?>">
                                                <?php echo $jogo['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Torneios Recentes -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Torneios Recentes
                </h5>
                <a href="gerenciar_torneios.php" class="btn btn-sm btn-outline-primary">
                    Ver Todos
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($torneios_recentes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum torneio criado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($torneios_recentes as $torneio): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($torneio['nome']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($torneio['grupo_nome']); ?></small>
                            </div>
                            <span class="badge bg-<?php echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 'warning'; ?>">
                                <?php echo $torneio['status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
