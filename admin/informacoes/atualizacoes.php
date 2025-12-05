<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Atualizações';
requireAdmin($pdo);

$atualizacao_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$atualizacao = null;

// Buscar atualização se estiver editando
if ($atualizacao_id > 0) {
    $sql = "SELECT * FROM atualizacoes_site WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $atualizacao_id, PDO::PARAM_INT);
    $stmt->execute();
    $atualizacao = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $versao = trim($_POST['versao'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $erros = [];
        
        if (empty($titulo)) {
            $erros[] = 'Título é obrigatório.';
        }
        
        // Se não tiver versão, gerar automaticamente
        if (empty($versao)) {
            // Buscar última versão
            $sql_ultima_versao = "SELECT versao FROM atualizacoes_site WHERE versao IS NOT NULL ORDER BY id DESC LIMIT 1";
            $stmt_versao = executeQuery($pdo, $sql_ultima_versao);
            $ultima_versao = $stmt_versao ? $stmt_versao->fetch() : null;
            
            if ($ultima_versao && !empty($ultima_versao['versao'])) {
                // Incrementar versão (ex: 1.0.0 -> 1.0.1)
                $versao_array = explode('.', $ultima_versao['versao']);
                $ultimo_numero = (int)end($versao_array);
                $ultimo_numero++;
                $versao_array[count($versao_array) - 1] = $ultimo_numero;
                $versao = implode('.', $versao_array);
            } else {
                // Primeira versão
                $versao = '1.0.0';
            }
        }
        
        if (empty($erros)) {
            try {
                if ($atualizacao_id > 0) {
                    // Atualizar
                    $sql = "UPDATE atualizacoes_site SET titulo = ?, versao = ?, descricao = ?, ativo = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$titulo, $versao, $descricao ?: null, $ativo, $atualizacao_id]);
                    $_SESSION['mensagem'] = 'Atualização editada com sucesso!';
                } else {
                    // Inserir
                    $sql = "INSERT INTO atualizacoes_site (titulo, versao, descricao, ativo, data_publicacao) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$titulo, $versao, $descricao ?: null, $ativo]);
                    $_SESSION['mensagem'] = 'Atualização cadastrada com sucesso!';
                }
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: ../../informacoes.php?secao=atualizacoes');
                exit();
            } catch (PDOException $e) {
                error_log("Erro ao salvar atualização: " . $e->getMessage());
                $_SESSION['mensagem'] = 'Erro ao salvar atualização.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } else {
            $_SESSION['mensagem'] = implode('<br>', $erros);
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } elseif ($acao === 'excluir') {
        $sql = "DELETE FROM atualizacoes_site WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$atualizacao_id]);
        
        $_SESSION['mensagem'] = 'Atualização excluída com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: ../../informacoes.php?secao=atualizacoes');
        exit();
    }
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-sync-alt me-2"></i><?php echo $atualizacao_id > 0 ? 'Editar Atualização' : 'Cadastrar Atualização'; ?>
            </h2>
            <a href="../../informacoes.php?secao=atualizacoes" class="btn btn-outline-secondary">
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
                <h5 class="mb-0">Dados da Atualização</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" 
                               value="<?php echo htmlspecialchars($atualizacao['titulo'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="versao" class="form-label">Versão</label>
                        <input type="text" class="form-control" id="versao" name="versao" 
                               value="<?php echo htmlspecialchars($atualizacao['versao'] ?? ''); ?>" 
                               placeholder="Ex: 1.0.0 (deixe em branco para gerar automaticamente)">
                        <small class="form-text text-muted">Deixe em branco para gerar automaticamente baseado na última versão</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição *</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="6" required><?php echo htmlspecialchars($atualizacao['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?php echo ($atualizacao['ativo'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if ($atualizacao_id > 0): ?>
                            <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                                <i class="fas fa-trash me-1"></i>Excluir
                            </button>
                        <?php endif; ?>
                        <a href="../../informacoes.php?secao=atualizacoes" class="btn btn-secondary">
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

<?php if ($atualizacao_id > 0): ?>
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="acao" value="excluir">
</form>

<script>
function confirmarExclusao() {
    if (confirm('Tem certeza que deseja excluir esta atualização?')) {
        document.getElementById('formExcluir').submit();
    }
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>

