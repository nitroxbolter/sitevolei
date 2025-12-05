<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Gerenciar Loja';
requireAdmin($pdo);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'adicionar_produto') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0;
        
        $erros = [];
        
        if (empty($nome)) {
            $erros[] = 'Nome é obrigatório.';
        }
        
        if ($valor <= 0) {
            $erros[] = 'Valor deve ser maior que zero.';
        }
        
        if (empty($erros)) {
            // Upload de imagem
            $imagem = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../assets/arquivos/produtos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($extensao, $extensoesPermitidas)) {
                    $nomeArquivo = uniqid() . '_' . time() . '.' . $extensao;
                    $caminhoCompleto = $uploadDir . $nomeArquivo;
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoCompleto)) {
                        $imagem = 'assets/arquivos/produtos/' . $nomeArquivo;
                    } else {
                        $erros[] = 'Erro ao fazer upload da imagem.';
                    }
                } else {
                    $erros[] = 'Formato de arquivo não permitido. Use: JPG, JPEG, PNG, GIF ou WEBP.';
                }
            }
            
            if (empty($erros)) {
                try {
                    $sql = "INSERT INTO loja_produtos (nome, descricao, valor, imagem) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $descricao ?: null, $valor, $imagem]);
                    
                    $_SESSION['mensagem'] = 'Produto adicionado com sucesso!';
                    $_SESSION['tipo_mensagem'] = 'success';
                    header('Location: loja.php');
                    exit();
                } catch (PDOException $e) {
                    error_log("Erro ao adicionar produto: " . $e->getMessage());
                    $_SESSION['mensagem'] = 'Erro ao adicionar produto.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            } else {
                $_SESSION['mensagem'] = implode('<br>', $erros);
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } else {
            $_SESSION['mensagem'] = implode('<br>', $erros);
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } elseif ($acao === 'editar_produto') {
        $produto_id = (int)($_POST['produto_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $erros = [];
        
        if ($produto_id <= 0) {
            $erros[] = 'ID do produto inválido.';
        }
        
        if (empty($nome)) {
            $erros[] = 'Nome é obrigatório.';
        }
        
        if ($valor <= 0) {
            $erros[] = 'Valor deve ser maior que zero.';
        }
        
        if (empty($erros)) {
            // Buscar produto atual para pegar imagem antiga
            $sql_old = "SELECT imagem FROM loja_produtos WHERE id = ?";
            $stmt_old = $pdo->prepare($sql_old);
            $stmt_old->execute([$produto_id]);
            $produto_old = $stmt_old->fetch(PDO::FETCH_ASSOC);
            $imagem_antiga = $produto_old ? $produto_old['imagem'] : null;
            
            // Upload de nova imagem (se houver)
            $imagem = $imagem_antiga; // Manter imagem antiga por padrão
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../assets/arquivos/produtos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($extensao, $extensoesPermitidas)) {
                    $nomeArquivo = uniqid() . '_' . time() . '.' . $extensao;
                    $caminhoCompleto = $uploadDir . $nomeArquivo;
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoCompleto)) {
                        $imagem = 'assets/arquivos/produtos/' . $nomeArquivo;
                        
                        // Remover imagem antiga se existir
                        if ($imagem_antiga) {
                            $oldPath = '../' . $imagem_antiga;
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                    } else {
                        $erros[] = 'Erro ao fazer upload da imagem.';
                    }
                } else {
                    $erros[] = 'Formato de arquivo não permitido. Use: JPG, JPEG, PNG, GIF ou WEBP.';
                }
            }
            
            if (empty($erros)) {
                try {
                    $sql = "UPDATE loja_produtos SET nome = ?, descricao = ?, valor = ?, imagem = ?, ativo = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $descricao ?: null, $valor, $imagem, $ativo, $produto_id]);
                    
                    $_SESSION['mensagem'] = 'Produto atualizado com sucesso!';
                    $_SESSION['tipo_mensagem'] = 'success';
                    header('Location: loja.php');
                    exit();
                } catch (PDOException $e) {
                    error_log("Erro ao atualizar produto: " . $e->getMessage());
                    $_SESSION['mensagem'] = 'Erro ao atualizar produto.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            } else {
                $_SESSION['mensagem'] = implode('<br>', $erros);
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } else {
            $_SESSION['mensagem'] = implode('<br>', $erros);
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } elseif ($acao === 'remover_produto') {
        $produto_id = (int)($_POST['produto_id'] ?? 0);
        
        if ($produto_id > 0) {
            try {
                // Buscar imagem antes de remover
                $sql_img = "SELECT imagem FROM loja_produtos WHERE id = ?";
                $stmt_img = $pdo->prepare($sql_img);
                $stmt_img->execute([$produto_id]);
                $produto_img = $stmt_img->fetch(PDO::FETCH_ASSOC);
                
                // Remover do banco
                $sql = "DELETE FROM loja_produtos WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$produto_id]);
                
                // Remover imagem do servidor
                if ($produto_img && $produto_img['imagem']) {
                    $imgPath = '../' . $produto_img['imagem'];
                    if (file_exists($imgPath)) {
                        @unlink($imgPath);
                    }
                }
                
                $_SESSION['mensagem'] = 'Produto removido com sucesso!';
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: loja.php');
                exit();
            } catch (PDOException $e) {
                error_log("Erro ao remover produto: " . $e->getMessage());
                $_SESSION['mensagem'] = 'Erro ao remover produto.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        }
    }
}

// Buscar todos os produtos
$sql = "SELECT * FROM loja_produtos ORDER BY data_criacao DESC";
$stmt = executeQuery($pdo, $sql);
$produtos = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-store me-2"></i>Gerenciar Loja
        </h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAdicionarProduto">
                <i class="fas fa-plus me-1"></i>Adicionar Produto
            </button>
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
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Produtos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($produtos)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Nenhum produto cadastrado.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Imagem</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td>
                                            <?php if ($produto['imagem']): ?>
                                                <?php
                                                $imagem = $produto['imagem'];
                                                if (strpos($imagem, '/') === 0) {
                                                    // Caminho absoluto
                                                } elseif (strpos($imagem, 'assets/') === 0) {
                                                    $imagem = '/' . $imagem;
                                                } else {
                                                    $imagem = '/assets/arquivos/produtos/' . $imagem;
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($imagem); ?>" 
                                                     alt="<?php echo htmlspecialchars($produto['nome']); ?>"
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div class="bg-secondary d-flex align-items-center justify-content-center" 
                                                     style="width: 60px; height: 60px; border-radius: 4px;">
                                                    <i class="fas fa-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($produto['nome']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $descricao = htmlspecialchars($produto['descricao'] ?? '');
                                            echo strlen($descricao) > 50 ? substr($descricao, 0, 50) . '...' : $descricao;
                                            ?>
                                        </td>
                                        <td>
                                            <strong class="text-primary">
                                                R$ <?php echo number_format($produto['valor'], 2, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($produto['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editarProduto(<?php echo htmlspecialchars(json_encode($produto)); ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="removerProduto(<?php echo $produto['id']; ?>, '<?php echo addslashes($produto['nome']); ?>')">
                                                <i class="fas fa-trash"></i>
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
    </div>
</div>

<!-- Modal Adicionar Produto -->
<div class="modal fade" id="modalAdicionarProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Adicionar Produto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formAdicionarProduto">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="adicionar_produto">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Produto *</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="valor" name="valor" 
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="imagem" class="form-label">Imagem do Produto</label>
                        <input type="file" class="form-control" id="imagem" name="imagem" 
                               accept="image/*">
                        <small class="text-muted">Formatos aceitos: JPG, JPEG, PNG, GIF, WEBP</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Adicionar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Produto -->
<div class="modal fade" id="modalEditarProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Editar Produto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formEditarProduto">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar_produto">
                    <input type="hidden" name="produto_id" id="editar_produto_id">
                    <div class="mb-3">
                        <label for="editar_nome" class="form-label">Nome do Produto *</label>
                        <input type="text" class="form-control" id="editar_nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="editar_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="editar_descricao" name="descricao" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editar_valor" class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="editar_valor" name="valor" 
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="editar_imagem" class="form-label">Nova Imagem (deixe em branco para manter a atual)</label>
                        <input type="file" class="form-control" id="editar_imagem" name="imagem" 
                               accept="image/*">
                        <small class="text-muted">Formatos aceitos: JPG, JPEG, PNG, GIF, WEBP</small>
                        <div id="imagem_atual" class="mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editar_ativo" name="ativo">
                            <label class="form-check-label" for="editar_ativo">
                                Produto Ativo
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProduto(produto) {
    document.getElementById('editar_produto_id').value = produto.id;
    document.getElementById('editar_nome').value = produto.nome;
    document.getElementById('editar_descricao').value = produto.descricao || '';
    document.getElementById('editar_valor').value = produto.valor;
    document.getElementById('editar_ativo').checked = produto.ativo == 1;
    
    // Mostrar imagem atual
    const imagemAtual = document.getElementById('imagem_atual');
    if (produto.imagem) {
        let imgPath = produto.imagem;
        // Ajustar caminho para visualização
        if (imgPath.indexOf('/') === 0) {
            // Caminho absoluto, manter
        } else if (imgPath.indexOf('assets/') === 0) {
            imgPath = '/' + imgPath;
        } else {
            imgPath = '/assets/arquivos/produtos/' + imgPath;
        }
        imagemAtual.innerHTML = '<small class="text-muted">Imagem atual:</small><br>' +
                               '<img src="' + imgPath + '" style="max-width: 200px; max-height: 200px; border-radius: 4px; margin-top: 5px;">';
    } else {
        imagemAtual.innerHTML = '<small class="text-muted">Nenhuma imagem cadastrada</small>';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalEditarProduto'));
    modal.show();
}

function removerProduto(produtoId, nomeProduto) {
    if (confirm('Tem certeza que deseja remover o produto "' + nomeProduto + '"?\n\nEsta ação não pode ser desfeita.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="acao" value="remover_produto">' +
                         '<input type="hidden" name="produto_id" value="' + produtoId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>

