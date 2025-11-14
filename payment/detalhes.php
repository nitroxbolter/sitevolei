<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Detalhes do Pagamento';
requireLogin();

$pagamento_id = (int)$_GET['id'];

// Obter detalhes do pagamento
$sql = "SELECT p.*, u.nome as usuario_nome 
        FROM pagamentos p
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.id = ? AND p.usuario_id = ?";
$stmt = executeQuery($pdo, $sql, [$pagamento_id, $_SESSION['user_id']]);
$pagamento = $stmt ? $stmt->fetch() : false;

if (!$pagamento) {
    header('Location: index.php');
    exit();
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>Detalhes do Pagamento
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>ID do Pagamento:</strong>
                        <p><?php echo $pagamento['pagamento_id']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong>
                        <p>
                            <span class="badge bg-<?php echo $pagamento['status'] === 'Aprovado' ? 'success' : ($pagamento['status'] === 'Pendente' ? 'warning' : 'danger'); ?> fs-6">
                                <?php echo $pagamento['status']; ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Valor:</strong>
                        <p class="h5 text-primary">R$ <?php echo number_format($pagamento['valor'], 2, ',', '.'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Método de Pagamento:</strong>
                        <p><?php echo ucfirst($pagamento['metodo_pagamento']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Tipo:</strong>
                        <p><?php echo ucfirst($pagamento['tipo_pagamento']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Data de Criação:</strong>
                        <p><?php echo formatarData($pagamento['data_criacao']); ?></p>
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Descrição:</strong>
                        <p><?php echo htmlspecialchars($pagamento['descricao']); ?></p>
                    </div>
                </div>

                <?php if ($pagamento['status'] === 'Pendente'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Pagamento Pendente:</strong> Este pagamento ainda está sendo processado.
                    </div>
                <?php elseif ($pagamento['status'] === 'Aprovado'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Pagamento Aprovado:</strong> Seu pagamento foi processado com sucesso!
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Pagamento Cancelado:</strong> Este pagamento foi cancelado ou rejeitado.
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                    <?php if ($pagamento['status'] === 'Pendente'): ?>
                        <button class="btn btn-primary" onclick="atualizarStatus()">
                            <i class="fas fa-sync me-2"></i>Atualizar Status
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function atualizarStatus() {
    $.ajax({
        url: 'atualizar_status.php',
        method: 'POST',
        data: { pagamento_id: <?php echo $pagamento['id']; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao atualizar status', 'danger');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
