<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Grupo';

$grupo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

$sql = "SELECT g.*, u.nome AS admin_nome
        FROM grupos g
        LEFT JOIN usuarios u ON u.id = g.administrador_id
        WHERE g.id = ? AND g.ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Membros ativos (resumo)
$sql = "SELECT u.id, u.nome, u.nivel, u.foto_perfil, COALESCE(u.reputacao,0) AS reputacao
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1
        ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$membros = $stmt ? $stmt->fetchAll() : [];

// Verificar se o usuário logado é membro do grupo
$usuario_e_membro = false;
$usuario_e_admin = false;
if (isLoggedIn()) {
    $usuario_id = (int)$_SESSION['user_id'];
    $usuario_e_admin = ((int)$grupo['administrador_id'] === $usuario_id);
    
    $sql = "SELECT id FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
    $usuario_e_membro = $stmt ? ($stmt->fetch() !== false) : false;
}

// Verificar se existe sistema de pontuação ativo
$tem_sistema_pontuacao = false;
if ($usuario_e_membro || $usuario_e_admin) {
    $sql = "SELECT id FROM sistemas_pontuacao WHERE grupo_id = ? AND ativo = 1 LIMIT 1";
    $stmt = executeQuery($pdo, $sql, [$grupo_id]);
    $tem_sistema_pontuacao = $stmt ? ($stmt->fetch() !== false) : false;
}

include 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0 d-flex align-items-center gap-2">
            <?php if (!empty($grupo['logo_id'])): ?>
                <img src="<?php echo 'assets/arquivos/logosgrupos/'.(int)$grupo['logo_id'].'.png'; ?>" alt="Logo do grupo" class="rounded-circle" width="48" height="48">
            <?php endif; ?>
            <span><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($grupo['nome']); ?></span>
        </h2>
        <div class="d-flex gap-2">
            <?php if ($tem_sistema_pontuacao && ($usuario_e_membro || $usuario_e_admin)): ?>
                <a href="pontuacao_classificacao.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-info">
                    <i class="fas fa-trophy me-1"></i>Classificação
                </a>
            <?php endif; ?>
            <?php if ($usuario_e_admin): ?>
                <a href="admin/sistema_pontuacao.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-warning">
                    <i class="fas fa-cog me-1"></i>Gerenciar Pontuação
                </a>
            <?php endif; ?>
            <?php if ($usuario_e_membro && !$usuario_e_admin): ?>
                <button class="btn btn-outline-danger" onclick="sairDoGrupo(<?php echo $grupo_id; ?>)">
                    <i class="fas fa-sign-out-alt me-1"></i>Sair do Grupo
                </button>
            <?php endif; ?>
            <a href="grupos.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <strong>Informações</strong>
            </div>
            <div class="card-body">
                <p class="mb-2"><i class="fas fa-user me-2"></i><strong>Admin:</strong> <?php echo htmlspecialchars($grupo['admin_nome']); ?></p>
                <p class="mb-2"><i class="fas fa-phone me-2"></i><strong>Contato:</strong> <?php echo htmlspecialchars($grupo['contato'] ?? 'Não informado'); ?></p>
                <p class="mb-2"><i class="fas fa-table-tennis me-2"></i><strong>Modalidade:</strong> <?php echo htmlspecialchars($grupo['modalidade'] ?? 'Não informado'); ?></p>
                <p class="mb-2"><i class="fas fa-star me-2"></i><strong>Nível:</strong> <?php echo htmlspecialchars($grupo['nivel'] ?? 'Não informado'); ?></p>
                <p class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><strong>Local:</strong> <?php echo htmlspecialchars($grupo['local_principal']); ?></p>
                <?php if (!empty($grupo['descricao'])): ?>
                <p class="mb-0"><i class="fas fa-info-circle me-2"></i><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($grupo['descricao'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Membros</strong>
                <span class="badge bg-secondary"><?php echo count($membros); ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($membros)): ?>
                    <div class="text-muted">Nenhum membro no momento.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($membros as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <?php 
                                    $avatar = $m['foto_perfil'] ?: 'assets/arquivos/logo.png';
                                    $rep = (int)($m['reputacao'] ?? 0);
                                    $repBadgeClass = 'bg-danger';
                                    $repStyle = '';
                                    if ($rep > 75) {
                                        $repBadgeClass = 'bg-success';
                                    } elseif ($rep > 50) {
                                        $repBadgeClass = 'bg-warning text-dark';
                                    } elseif ($rep > 25) {
                                        $repBadgeClass = 'text-dark';
                                        $repStyle = 'background-color:#fd7e14;';
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="rounded-circle" width="28" height="28" style="object-fit:cover;">
                                    <strong><?php echo htmlspecialchars($m['nome']); ?></strong>
                                    <span class="badge ms-1 <?php echo $repBadgeClass; ?>" style="<?php echo $repStyle; ?>"><?php echo (int)$m['reputacao']; ?> pts</span>
                                </div>
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($m['nivel']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function sairDoGrupo(grupoId) {
    if (confirm('Tem certeza que deseja sair deste grupo?')) {
        $.ajax({
            url: 'ajax/sair_grupo.php',
            method: 'POST',
            data: { grupo_id: grupoId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'grupos.php';
                    }, 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao processar solicitação', 'danger');
            }
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>


