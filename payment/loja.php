<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Loja';

// Buscar produtos ativos
$sql = "SELECT * FROM loja_produtos WHERE ativo = 1 ORDER BY data_criacao DESC";
$stmt = executeQuery($pdo, $sql);
$produtos = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <h2>
            <i class="fas fa-shopping-cart me-2"></i>Loja
        </h2>
    </div>
</div>

<?php if (empty($produtos)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Nenhum produto disponível no momento.</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($produtos as $produto): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <?php if ($produto['imagem']): ?>
                        <?php
                        $imagem = $produto['imagem'];
                        // Garantir que o caminho comece com / ou seja relativo
                        if (strpos($imagem, 'http') === 0) {
                            // URL completa, manter como está
                        } elseif (strpos($imagem, '/') === 0) {
                            // Caminho absoluto, manter como está
                        } elseif (strpos($imagem, 'assets/') === 0) {
                            // Caminho relativo com assets/, adicionar /
                            $imagem = '/' . $imagem;
                        } else {
                            // Caminho relativo simples, adicionar /assets/arquivos/produtos/
                            $imagem = '/assets/arquivos/produtos/' . $imagem;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($imagem); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($produto['nome']); ?>"
                             style="height: 200px; width: 100%; object-fit: contain; background-color: #f8f9fa; padding: 10px;">
                    <?php else: ?>
                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="fas fa-image fa-4x text-white"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h5>
                        <?php if (!empty($produto['descricao'])): ?>
                            <p class="card-text flex-grow-1"><?php echo nl2br(htmlspecialchars($produto['descricao'])); ?></p>
                        <?php endif; ?>
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="h4 text-primary mb-0">
                                    R$ <?php echo number_format($produto['valor'], 2, ',', '.'); ?>
                                </span>
                            </div>
                            <button class="btn btn-primary w-100" onclick="comprarProduto(<?php echo $produto['id']; ?>)">
                                <i class="fas fa-shopping-cart me-2"></i>Comprar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function comprarProduto(produtoId) {
    <?php if (!isLoggedIn()): ?>
        showAlert('Você precisa estar logado para comprar produtos.', 'warning');
        setTimeout(function() {
            window.location.href = '/auth/login.php';
        }, 1500);
    <?php else: ?>
        if (confirm('Deseja comprar este produto?')) {
            // Aqui você pode implementar a lógica de compra
            showAlert('Funcionalidade de compra em desenvolvimento.', 'info');
        }
    <?php endif; ?>
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    const container = document.querySelector('.container');
    if (container) {
        const alertDiv = document.createElement('div');
        alertDiv.innerHTML = alertHtml;
        container.insertBefore(alertDiv.firstElementChild, container.firstChild);
    }
}
</script>

<?php include '../includes/footer.php'; ?>

