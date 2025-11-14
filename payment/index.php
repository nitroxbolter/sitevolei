<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Pagamentos';
requireLogin();

// Obter histórico de pagamentos do usuário
$sql = "SELECT * FROM pagamentos WHERE usuario_id = ? ORDER BY data_criacao DESC";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$pagamentos = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-credit-card me-2"></i>Pagamentos
            </h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoPagamentoModal">
                <i class="fas fa-plus me-2"></i>Novo Pagamento
            </button>
        </div>
    </div>
</div>

<!-- Resumo de Pagamentos -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h4>0</h4>
                <p class="mb-0">Pagamentos Aprovados</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h4>0</h4>
                <p class="mb-0">Pendentes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x mb-2"></i>
                <h4>0</h4>
                <p class="mb-0">Cancelados</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4>R$ 0,00</h4>
                <p class="mb-0">Total Pago</p>
            </div>
        </div>
    </div>
</div>

<!-- Histórico de Pagamentos -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>Histórico de Pagamentos
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($pagamentos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhum pagamento encontrado</h5>
                <p class="text-muted">Seus pagamentos aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td>#<?php echo $pagamento['id']; ?></td>
                                <td><?php echo htmlspecialchars($pagamento['descricao']); ?></td>
                                <td>R$ <?php echo number_format($pagamento['valor'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $pagamento['status'] === 'Aprovado' ? 'success' : ($pagamento['status'] === 'Pendente' ? 'warning' : 'danger'); ?>">
                                        <?php echo $pagamento['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo formatarData($pagamento['data_criacao']); ?></td>
                                <td>
                                    <a href="detalhes.php?id=<?php echo $pagamento['id']; ?>" class="btn btn-sm btn-primary">
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

<!-- Modal Novo Pagamento -->
<div class="modal fade" id="novoPagamentoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Novo Pagamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="processar_pagamento.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_pagamento" class="form-label">Tipo de Pagamento *</label>
                            <select class="form-select" id="tipo_pagamento" name="tipo_pagamento" required>
                                <option value="">Selecione o tipo</option>
                                <option value="mensalidade">Mensalidade do Grupo</option>
                                <option value="torneio">Inscrição em Torneio</option>
                                <option value="evento">Evento Especial</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="valor" class="form-label">Valor (R$) *</label>
                            <input type="number" class="form-control" id="valor" name="valor" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição *</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3" 
                                  placeholder="Descreva o motivo do pagamento..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="metodo_pagamento" class="form-label">Método de Pagamento *</label>
                        <select class="form-select" id="metodo_pagamento" name="metodo_pagamento" required>
                            <option value="">Selecione o método</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão de Crédito</option>
                            <option value="debito">Cartão de Débito</option>
                            <option value="boleto">Boleto Bancário</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-credit-card me-2"></i>Processar Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
