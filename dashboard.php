<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Dashboard';

// Se não estiver logado, redirecionar para dashboard de visitante
if (!isLoggedIn()) {
    header('Location: dashboard_guest.php');
    exit();
}

$usuario = getUserById($pdo, $_SESSION['user_id']);

// Se usuário não encontrado, redirecionar para login
if (!$usuario) {
    header('Location: auth/login.php');
    exit();
}

$grupos_usuario = getGruposUsuario($pdo, $_SESSION['user_id']);
$proximos_jogos = getProximosJogos($pdo, 5);
// Buscar torneios ativos do usuário (onde é admin ou participante)
$sql_torneios_ativos = "SELECT DISTINCT t.*, g.nome as grupo_nome 
                        FROM torneios t 
                        LEFT JOIN grupos g ON g.id = t.grupo_id
                        LEFT JOIN torneio_participantes tp ON tp.torneio_id = t.id AND tp.usuario_id = ?
                        WHERE t.status IN ('Inscrições Abertas', 'Em Andamento')
                        AND (
                            t.criado_por = ? 
                            OR (g.id IS NOT NULL AND g.administrador_id = ?)
                            OR tp.id IS NOT NULL
                        )
                        ORDER BY t.data_inicio ASC 
                        LIMIT 3";
$stmt_torneios_ativos = executeQuery($pdo, $sql_torneios_ativos, [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$torneios_ativos = $stmt_torneios_ativos ? $stmt_torneios_ativos->fetchAll() : [];

// Contar total de grupos do usuário
$sql_grupos_count = "SELECT COUNT(*) as total FROM grupo_membros gm 
                     JOIN grupos g ON g.id = gm.grupo_id 
                     WHERE gm.usuario_id = ? AND gm.ativo = 1 AND g.ativo = 1";
$stmt_grupos = executeQuery($pdo, $sql_grupos_count, [$_SESSION['user_id']]);
$total_grupos = $stmt_grupos ? (int)$stmt_grupos->fetch()['total'] : 0;

// Contar total de próximos jogos (todos os jogos futuros, não apenas do usuário)
$sql_jogos_count = "SELECT COUNT(*) as total FROM jogos j 
                    JOIN grupos g ON j.grupo_id = g.id 
                    WHERE j.data_jogo > NOW() AND j.status = 'Aberto'";
$stmt_jogos = executeQuery($pdo, $sql_jogos_count);
$total_jogos = $stmt_jogos ? (int)$stmt_jogos->fetch()['total'] : 0;

// Contar total de torneios do usuário (incluindo finalizados):
// 1. Torneios onde o usuário é admin (criador ou admin do grupo)
// 2. Torneios onde o usuário está participando como participante
$sql_torneios_count = "SELECT COUNT(DISTINCT t.id) as total 
                       FROM torneios t 
                       LEFT JOIN grupos g ON g.id = t.grupo_id
                       LEFT JOIN torneio_participantes tp ON tp.torneio_id = t.id AND tp.usuario_id = ?
                       WHERE t.status IN ('Criado', 'Inscrições Abertas', 'Em Andamento', 'Finalizado')
                       AND (
                           t.criado_por = ? 
                           OR (g.id IS NOT NULL AND g.administrador_id = ?)
                           OR tp.id IS NOT NULL
                       )";
$stmt_torneios = executeQuery($pdo, $sql_torneios_count, [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$total_torneios = $stmt_torneios ? (int)$stmt_torneios->fetch()['total'] : 0;

// Obter jogos confirmados do usuário para exibição
$sql = "SELECT j.*, g.nome as grupo_nome, cp.status as confirmacao_status
        FROM jogos j
        JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id AND cp.usuario_id = ?
        WHERE j.data_jogo > NOW() AND j.status = 'Aberto'
        ORDER BY j.data_jogo ASC
        LIMIT 10";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$jogos_usuario = $stmt ? $stmt->fetchAll() : [];

include 'includes/header.php';
?>

<div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>Meu Perfil
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if ($usuario['foto_perfil']): ?>
                    <img src="<?php echo $usuario['foto_perfil']; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Foto do perfil">
                <?php else: ?>
                    <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                        <i class="fas fa-user fa-3x text-white"></i>
                    </div>
                <?php endif; ?>
                
                <h5><?php echo htmlspecialchars($usuario['nome']); ?></h5>
                <p class="text-muted"><?php echo $usuario['email']; ?></p>
                
                <div class="mb-2">
                    <!-- Linha 1: Admin e Premium -->
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <?php if ($usuario['is_admin']): ?>
                            <span class="badge bg-danger me-2">
                                <i class="fas fa-shield-alt me-1"></i>Admin
                            </span>
                        <?php endif; ?>
                        <?php if ($usuario['is_premium']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-crown me-1"></i>Premium
                            </span>
                        <?php endif; ?>
                    </div>
                    <!-- Linha 2: Profissional e Pontos -->
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="badge bg-<?php echo $usuario['nivel'] === 'Profissional' ? 'danger' : ($usuario['nivel'] === 'Avançado' ? 'warning' : ($usuario['nivel'] === 'Intermediário' ? 'info' : 'secondary')); ?> me-2">
                            <?php echo $usuario['nivel']; ?>
                        </span>
                        <?php 
                            $repDash = (int)($usuario['reputacao'] ?? 0); 
                            $repDashClass = 'bg-danger';
                            $repDashStyle = '';
                            if ($repDash > 75) { $repDashClass = 'bg-success'; }
                            elseif ($repDash > 50) { $repDashClass = 'bg-warning text-dark'; }
                            elseif ($repDash > 25) { $repDashClass = 'text-dark'; $repDashStyle = 'background-color:#fd7e14;'; }
                        ?>
                        <span class="badge <?php echo $repDashClass; ?>" style="<?php echo $repDashStyle; ?>">
                            <i class="fas fa-star me-1"></i><?php echo $repDash; ?> pts
                        </span>
                    </div>
                </div>
                
                <a href="perfil.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i>Editar Perfil
                </a>
            </div>
        </div>
        
        <!-- Grupos do Usuário -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>Meus Grupos
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($grupos_usuario)): ?>
                    <p class="text-muted text-center">Você não está em nenhum grupo ainda.</p>
                    <a href="grupos/grupos.php" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-plus me-1"></i>Encontrar Grupos
                    </a>
                <?php else: ?>
                    <?php foreach ($grupos_usuario as $grupo): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($grupo['nome']); ?></strong>
                                <?php if ($grupo['is_admin']): ?>
                                    <span class="badge bg-warning text-dark">Admin</span>
                                <?php endif; ?>
                            </div>
                            <a href="grupos/grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="col-md-9">
        <!-- Estatísticas Rápidas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                        <h4><?php echo $total_jogos; ?></h4>
                        <p class="mb-0">Próximos Jogos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo $total_grupos; ?></h4>
                        <p class="mb-0">Grupos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-trophy fa-2x mb-2"></i>
                        <h4><?php echo $total_torneios; ?></h4>
                        <p class="mb-0">Torneios Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-2x mb-2"></i>
                        <h4><?php echo $usuario['reputacao']; ?></h4>
                        <p class="mb-0">Reputação</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Próximos Jogos -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Próximos Jogos
                </h5>
                <a href="jogos/jogos.php" class="btn btn-sm btn-outline-primary">
                    Ver Todos
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($jogos_usuario)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum jogo agendado no momento.</p>
                        <a href="jogos/jogos.php" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Buscar Jogos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Grupo</th>
                                    <th>Data</th>
                                    <th>Local</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos_usuario as $jogo): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($jogo['titulo']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($jogo['grupo_nome']); ?></td>
                                        <td><?php echo formatarData($jogo['data_jogo']); ?></td>
                                        <td><?php echo htmlspecialchars($jogo['local']); ?></td>
                                        <td>
                                            <?php if ($jogo['confirmacao_status']): ?>
                                                <span class="badge bg-<?php echo $jogo['confirmacao_status'] === 'Confirmado' ? 'success' : ($jogo['confirmacao_status'] === 'Ausente' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $jogo['confirmacao_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pendente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="jogos/jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-sm btn-primary">
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
        
        <!-- Torneios Ativos -->
        <?php if (!empty($torneios_ativos)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Torneios Ativos
                </h5>
                <a href="torneios/torneios.php" class="btn btn-sm btn-outline-primary">
                    Ver Todos
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($torneios_ativos as $torneio): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-trophy me-2"></i>
                                        <?php echo htmlspecialchars($torneio['nome']); ?>
                                    </h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo htmlspecialchars($torneio['grupo_nome']); ?>
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Início: <?php echo formatarData($torneio['data_inicio']); ?>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 'info'; ?>">
                                            <?php echo $torneio['status']; ?>
                                        </span>
                                        <a href="torneios/torneio.php?id=<?php echo $torneio['id']; ?>" class="btn btn-sm btn-warning">
                                            Ver Detalhes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Ações Rápidas -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Ações Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="jogos/jogos.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search fa-2x d-block mb-2"></i>
                            Buscar Jogos
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="grupos/grupos.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-users fa-2x d-block mb-2"></i>
                            Encontrar Grupos
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="torneios/torneios.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-trophy fa-2x d-block mb-2"></i>
                            Ver Torneios
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="perfil.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-user-edit fa-2x d-block mb-2"></i>
                            Editar Perfil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
