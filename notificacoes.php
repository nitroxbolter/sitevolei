<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Notificações';
requireLogin();

// Buscar notificações (se tabela existir)
$notificacoes = [];
$tabelaExiste = false;
try {
    $st = executeQuery($pdo, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes'");
    if ($st && $st->fetch()) { $tabelaExiste = true; }
} catch (Exception $e) {}

if ($tabelaExiste) {
    // Marcar como lidas
    executeQuery($pdo, "UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?", [$_SESSION['user_id']]);
    // Listar
    $stmtN = executeQuery($pdo, "SELECT id, titulo, mensagem, lida, criada_em FROM notificacoes WHERE usuario_id = ? ORDER BY criada_em DESC", [$_SESSION['user_id']]);
    $notificacoes = $stmtN ? $stmtN->fetchAll() : [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bell me-2"></i>Notificações</h2>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!$tabelaExiste): ?>
            <div class="alert alert-info">Sistema de notificações ainda não está habilitado.</div>
        <?php elseif (empty($notificacoes)): ?>
            <div class="text-center text-muted py-4">Sem notificações.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notificacoes as $n): ?>
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?php echo htmlspecialchars($n['titulo']); ?></div>
                        <div><?php echo nl2br(htmlspecialchars($n['mensagem'])); ?></div>
                        <small class="text-muted"><?php echo formatarData($n['criada_em'], 'd/m/Y H:i'); ?></small>
                    </div>
                    <?php if (!$n['lida']): ?><span class="badge bg-danger">Nova</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
  </div>

<?php include 'includes/footer.php'; ?>


