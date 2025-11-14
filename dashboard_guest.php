<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Dashboard - Visitante';

// Obter grupos públicos (para visitantes verem)
$sql = "SELECT g.*, u.nome as admin_nome, COUNT(gm.id) as total_membros
        FROM grupos g
        LEFT JOIN usuarios u ON g.administrador_id = u.id
        LEFT JOIN grupo_membros gm ON g.id = gm.grupo_id AND gm.ativo = 1
        WHERE g.ativo = 1
        GROUP BY g.id
        ORDER BY g.data_criacao DESC
        LIMIT 6";
$stmt = executeQuery($pdo, $sql);
$grupos = $stmt ? $stmt->fetchAll() : [];

// Obter jogos públicos
$sql = "SELECT j.*, g.nome as grupo_nome, u.nome as criado_por_nome,
               COUNT(cp.id) as total_confirmacoes
        FROM jogos j
        LEFT JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id
        WHERE j.status = 'Aberto' AND j.data_jogo >= NOW()
        GROUP BY j.id
        ORDER BY j.data_jogo ASC
        LIMIT 6";
$stmt = executeQuery($pdo, $sql);
$jogos = $stmt ? $stmt->fetchAll() : [];

// Obter torneios públicos
$sql = "SELECT t.*, g.nome as grupo_nome, u.nome as criado_por_nome,
               COUNT(tp.id) as total_participantes
        FROM torneios t
        LEFT JOIN grupos g ON t.grupo_id = g.id
        LEFT JOIN usuarios u ON t.criado_por = u.id
        LEFT JOIN torneio_participantes tp ON t.id = tp.torneio_id
        WHERE t.status = 'Inscrições Abertas'
        GROUP BY t.id
        ORDER BY t.data_inicio ASC
        LIMIT 4";
$stmt = executeQuery($pdo, $sql);
$torneios = $stmt ? $stmt->fetchAll() : [];

include 'includes/header.php';
?>

<div class="row">
    <!-- Sidebar Visitante -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>Visitante
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                    <i class="fas fa-user fa-3x text-white"></i>
                </div>
                
                <h5>Visitante</h5>
                <p class="text-muted">Você está navegando como visitante</p>
                
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="badge bg-secondary me-2">
                        <i class="fas fa-eye me-1"></i>Somente Visualização
                    </span>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Fazer Login
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cadastroModal">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar-se
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Card de Benefícios -->
        <div class="card mt-3">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-star me-2"></i>Benefícios do Cadastro
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Criar e gerenciar grupos
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Organizar jogos
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Participar de torneios
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Sistema de reputação
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        Confirmar presença
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="col-md-9">
        <!-- Avisos -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bullhorn me-2"></i>Avisos Gerais
                </h5>
            </div>
            <div class="card-body">
                <?php
                $sql = "SELECT * FROM avisos WHERE tipo = 'Geral' AND ativo = 1 ORDER BY data_criacao DESC LIMIT 3";
                $stmt = executeQuery($pdo, $sql);
                $avisos = $stmt ? $stmt->fetchAll() : [];
                
                if (empty($avisos)):
                ?>
                    <div class="text-center py-3">
                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Nenhum aviso no momento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($avisos as $aviso): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><?php echo htmlspecialchars($aviso['titulo']); ?></h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($aviso['conteudo'])); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo formatarData($aviso['data_criacao']); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grupos Públicos -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Grupos Disponíveis
                </h5>
                <small class="text-muted">Faça login para participar</small>
            </div>
            <div class="card-body">
                <?php if (empty($grupos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum grupo encontrado.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($grupos as $grupo): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($grupo['nome']); ?></h6>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($grupo['descricao'], 0, 100)) . (strlen($grupo['descricao']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($grupo['local_principal']); ?>
                                            </small>
                                            <span class="badge bg-info">
                                                <?php echo $grupo['total_membros']; ?> membros
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <div class="d-grid">
                                            <button class="btn btn-outline-primary btn-sm" onclick="mostrarLoginNecessario()">
                                                <i class="fas fa-sign-in-alt me-1"></i>Fazer Login para Participar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jogos Próximos -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Próximos Jogos
                </h5>
                <small class="text-muted">Faça login para confirmar presença</small>
            </div>
            <div class="card-body">
                <?php if (empty($jogos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum jogo agendado.</p>
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
                                    <th>Vagas</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($jogo['titulo']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($jogo['grupo_nome']); ?></td>
                                        <td>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo formatarData($jogo['data_jogo']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($jogo['local']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $jogo['vagas_disponiveis'] > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $jogo['vagas_disponiveis']; ?> vagas
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm" onclick="mostrarLoginNecessario()">
                                                <i class="fas fa-sign-in-alt me-1"></i>Login
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Torneios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Torneios em Andamento
                </h5>
                <small class="text-muted">Faça login para participar</small>
            </div>
            <div class="card-body">
                <?php if (empty($torneios)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum torneio ativo.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($torneios as $torneio): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-trophy text-warning me-2"></i>
                                            <?php echo htmlspecialchars($torneio['nome']); ?>
                                        </h6>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($torneio['descricao'], 0, 100)) . (strlen($torneio['descricao']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatarData($torneio['data_inicio']); ?>
                                            </small>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $torneio['total_participantes']; ?>/<?php echo $torneio['max_participantes']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <div class="d-grid">
                                            <button class="btn btn-outline-warning btn-sm" onclick="mostrarLoginNecessario()">
                                                <i class="fas fa-sign-in-alt me-1"></i>Fazer Login para Participar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Login Necessário -->
<div class="modal fade" id="loginNecessarioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-lock me-2"></i>Login Necessário
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-user-lock fa-4x text-muted mb-3"></i>
                    <h5>Faça login para acessar esta funcionalidade</h5>
                    <p class="text-muted">Você precisa estar logado para participar de grupos, confirmar presença em jogos e muito mais!</p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Fazer Login
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cadastroModal">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar-se
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarLoginNecessario() {
    var modal = new bootstrap.Modal(document.getElementById('loginNecessarioModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
