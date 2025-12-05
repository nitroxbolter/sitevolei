<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Profissionais';
requireAdmin($pdo);

$profissional_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$profissional = null;

// Buscar profissional se estiver editando
if ($profissional_id > 0) {
    $sql = "SELECT * FROM profissionais WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $profissional_id, PDO::PARAM_INT);
    $stmt->execute();
    $profissional = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $modalidade = trim($_POST['modalidade'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $erros = [];
        
        if (empty($nome)) {
            $erros[] = 'Nome é obrigatório.';
        }
        
        // Limpar telefone (apenas números)
        $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
        
        if (empty($erros)) {
            try {
                if ($profissional_id > 0) {
                    // Atualizar
                    $sql = "UPDATE profissionais SET nome = ?, telefone = ?, modalidade = ?, email = ?, descricao = ?, ativo = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $telefone_limpo ?: null, $modalidade ?: null, $email ?: null, $descricao ?: null, $ativo, $profissional_id]);
                    $_SESSION['mensagem'] = 'Profissional atualizado com sucesso!';
                } else {
                    // Inserir
                    $sql = "INSERT INTO profissionais (nome, telefone, modalidade, email, descricao, ativo) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $telefone_limpo ?: null, $modalidade ?: null, $email ?: null, $descricao ?: null, $ativo]);
                    $_SESSION['mensagem'] = 'Profissional cadastrado com sucesso!';
                }
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: ../../informacoes.php?secao=profissionais');
                exit();
            } catch (PDOException $e) {
                error_log("Erro ao salvar profissional: " . $e->getMessage());
                $_SESSION['mensagem'] = 'Erro ao salvar profissional.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } else {
            $_SESSION['mensagem'] = implode('<br>', $erros);
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } elseif ($acao === 'excluir') {
        $sql = "DELETE FROM profissionais WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profissional_id]);
        
        $_SESSION['mensagem'] = 'Profissional excluído com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: ../../informacoes.php?secao=profissionais');
        exit();
    }
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-user-tie me-2"></i><?php echo $profissional_id > 0 ? 'Editar Profissional' : 'Cadastrar Profissional'; ?>
            </h2>
            <a href="../../informacoes.php?secao=profissionais" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['mensagem'])): ?>
    <div class="alert alert-<?php echo $_SESSION['tipo_mensagem']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['mensagem']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php 
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
    ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Dados do Profissional</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($profissional['nome'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" 
                               value="<?php echo htmlspecialchars($profissional['telefone'] ?? ''); ?>" 
                               placeholder="(00) 00000-0000">
                        <small class="form-text text-muted">Apenas números serão salvos</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modalidade" class="form-label">Modalidade</label>
                        <input type="text" class="form-control" id="modalidade" name="modalidade" 
                               value="<?php echo htmlspecialchars($profissional['modalidade'] ?? ''); ?>" 
                               placeholder="Ex: Treinador, Preparador Físico, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($profissional['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($profissional['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?php echo ($profissional['ativo'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if ($profissional_id > 0): ?>
                            <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                                <i class="fas fa-trash me-1"></i>Excluir
                            </button>
                        <?php endif; ?>
                        <a href="../../informacoes.php?secao=profissionais" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($profissional_id > 0): ?>
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="acao" value="excluir">
</form>

<script>
function confirmarExclusao() {
    if (confirm('Tem certeza que deseja excluir este profissional?')) {
        document.getElementById('formExcluir').submit();
    }
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>

