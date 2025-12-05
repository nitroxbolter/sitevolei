<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Quadras';
requireAdmin($pdo);

$quadra_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quadra = null;

// Buscar quadra se estiver editando
if ($quadra_id > 0) {
    $sql = "SELECT * FROM quadras WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $quadra_id, PDO::PARAM_INT);
    $stmt->execute();
    $quadra = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $nome = trim($_POST['nome'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $valor_hora = !empty($_POST['valor_hora']) ? (float)$_POST['valor_hora'] : 0;
        $descricao = trim($_POST['descricao'] ?? '');
        $tipo = $_POST['tipo'] ?? 'quadra';
        $localizacao = trim($_POST['localizacao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $erros = [];
        
        if (empty($nome)) {
            $erros[] = 'Nome é obrigatório.';
        }
        
        if (empty($endereco)) {
            $erros[] = 'Endereço é obrigatório.';
        }
        
        // Processar upload de foto
        $foto = $quadra['foto'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/arquivos/quadras/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $nomeArquivo = uniqid() . '_' . time() . '_' . basename($_FILES['foto']['name']);
            $caminhoCompleto = $uploadDir . $nomeArquivo;
            
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
            $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            
            if (in_array($extensao, $extensoesPermitidas)) {
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoCompleto)) {
                    // Remover foto antiga se existir
                    if ($quadra && !empty($quadra['foto'])) {
                        $fotoAntiga = '../../' . ltrim($quadra['foto'], '/');
                        if (file_exists($fotoAntiga)) {
                            unlink($fotoAntiga);
                        }
                    }
                    $foto = 'assets/arquivos/quadras/' . $nomeArquivo;
                } else {
                    $erros[] = 'Erro ao fazer upload da foto.';
                }
            } else {
                $erros[] = 'Formato de arquivo não permitido. Use: JPG, JPEG, PNG ou GIF.';
            }
        }
        
        if (empty($erros)) {
            try {
                if ($quadra_id > 0) {
                    // Atualizar
                    if ($foto) {
                        $sql = "UPDATE quadras SET nome = ?, endereco = ?, valor_hora = ?, descricao = ?, foto = ?, tipo = ?, localizacao = ?, ativo = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nome, $endereco, $valor_hora, $descricao ?: null, $foto, $tipo, $localizacao ?: null, $ativo, $quadra_id]);
                    } else {
                        $sql = "UPDATE quadras SET nome = ?, endereco = ?, valor_hora = ?, descricao = ?, tipo = ?, localizacao = ?, ativo = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nome, $endereco, $valor_hora, $descricao ?: null, $tipo, $localizacao ?: null, $ativo, $quadra_id]);
                    }
                    $_SESSION['mensagem'] = 'Quadra atualizada com sucesso!';
                } else {
                    // Inserir
                    if ($foto) {
                        $sql = "INSERT INTO quadras (nome, endereco, valor_hora, descricao, foto, tipo, localizacao, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nome, $endereco, $valor_hora, $descricao ?: null, $foto, $tipo, $localizacao ?: null, $ativo]);
                    } else {
                        $sql = "INSERT INTO quadras (nome, endereco, valor_hora, descricao, tipo, localizacao, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nome, $endereco, $valor_hora, $descricao ?: null, $tipo, $localizacao ?: null, $ativo]);
                    }
                    $_SESSION['mensagem'] = 'Quadra cadastrada com sucesso!';
                }
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: ../../informacoes.php?secao=quadras');
                exit();
            } catch (PDOException $e) {
                error_log("Erro ao salvar quadra: " . $e->getMessage());
                $_SESSION['mensagem'] = 'Erro ao salvar quadra.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } else {
            $_SESSION['mensagem'] = implode('<br>', $erros);
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } elseif ($acao === 'excluir') {
        // Buscar foto antes de excluir
        if ($quadra && !empty($quadra['foto'])) {
            $fotoAntiga = '../../' . ltrim($quadra['foto'], '/');
            if (file_exists($fotoAntiga)) {
                unlink($fotoAntiga);
            }
        }
        
        $sql = "DELETE FROM quadras WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quadra_id]);
        
        $_SESSION['mensagem'] = 'Quadra excluída com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: ../../informacoes.php?secao=quadras');
        exit();
    }
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-volleyball-ball me-2"></i><?php echo $quadra_id > 0 ? 'Editar Quadra' : 'Cadastrar Quadra'; ?>
            </h2>
            <a href="../../informacoes.php?secao=quadras" class="btn btn-outline-secondary">
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
                <h5 class="mb-0">Dados da Quadra</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($quadra['nome'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="endereco" class="form-label">Endereço *</label>
                        <textarea class="form-control" id="endereco" name="endereco" rows="3" required><?php echo htmlspecialchars($quadra['endereco'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor_hora" class="form-label">Valor por Hora (R$) *</label>
                        <input type="number" class="form-control" id="valor_hora" name="valor_hora" 
                               step="0.01" min="0" value="<?php echo $quadra['valor_hora'] ?? '0'; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($quadra['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="foto" class="form-label">Foto</label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        <?php if ($quadra && !empty($quadra['foto'])): ?>
                            <?php
                            $imagem = $quadra['foto'];
                            if (strpos($imagem, '/') !== 0) {
                                $imagem = '/' . $imagem;
                            }
                            ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($imagem); ?>" alt="Foto atual" style="max-width: 200px; max-height: 200px;" class="img-thumbnail">
                                <p class="text-muted small mt-2">Foto atual (deixe em branco para manter)</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="quadra" <?php echo ($quadra['tipo'] ?? 'quadra') === 'quadra' ? 'selected' : ''; ?>>Quadra</option>
                            <option value="areia" <?php echo ($quadra['tipo'] ?? '') === 'areia' ? 'selected' : ''; ?>>Areia</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="localizacao" class="form-label">Localização (Link do Google Maps)</label>
                        <input type="url" class="form-control" id="localizacao" name="localizacao" 
                               value="<?php echo htmlspecialchars($quadra['localizacao'] ?? ''); ?>" 
                               placeholder="https://maps.google.com/...">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?php echo ($quadra['ativo'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if ($quadra_id > 0): ?>
                            <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                                <i class="fas fa-trash me-1"></i>Excluir
                            </button>
                        <?php endif; ?>
                        <a href="../../informacoes.php?secao=quadras" class="btn btn-secondary">
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

<?php if ($quadra_id > 0): ?>
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="acao" value="excluir">
</form>

<script>
function confirmarExclusao() {
    if (confirm('Tem certeza que deseja excluir esta quadra?')) {
        document.getElementById('formExcluir').submit();
    }
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>

