<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Gerenciar Usuários';
requireAdmin($pdo);

// Processar ações
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    
    switch ($acao) {
        case 'alterar_senha_usuario':
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';
            if (empty($usuario_id) || empty($nova_senha) || empty($confirmar_senha)) {
                $_SESSION['mensagem'] = 'Preencha os campos de senha.';
                $_SESSION['tipo_mensagem'] = 'danger';
                break;
            }
            if (strlen($nova_senha) < 6) {
                $_SESSION['mensagem'] = 'A senha deve ter pelo menos 6 caracteres.';
                $_SESSION['tipo_mensagem'] = 'danger';
                break;
            }
            if ($nova_senha !== $confirmar_senha) {
                $_SESSION['mensagem'] = 'As senhas não coincidem.';
                $_SESSION['tipo_mensagem'] = 'danger';
                break;
            }
            $hash = hashSenha($nova_senha);
            $sql = "UPDATE usuarios SET senha = ? WHERE id = ?";
            if (executeQuery($pdo, $sql, [$hash, $usuario_id])) {
                $_SESSION['mensagem'] = 'Senha do usuário atualizada com sucesso!';
                $_SESSION['tipo_mensagem'] = 'success';
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar senha do usuário.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
            break;
        case 'remover_usuario':
            if ($usuario_id && $usuario_id != $_SESSION['user_id']) {
                $sql = "UPDATE usuarios SET ativo = 0 WHERE id = ?";
                if (executeQuery($pdo, $sql, [$usuario_id])) {
                    $_SESSION['mensagem'] = 'Usuário removido com sucesso!';
                    $_SESSION['tipo_mensagem'] = 'success';
                } else {
                    $_SESSION['mensagem'] = 'Erro ao remover usuário.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
            
        case 'alterar_premium':
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            $premium_expira = $_POST['premium_expira'] ?? null;
            
            $sql = "UPDATE usuarios SET is_premium = ?, premium_expira_em = ? WHERE id = ?";
            if (executeQuery($pdo, $sql, [$is_premium, $premium_expira, $usuario_id])) {
                $_SESSION['mensagem'] = 'Status premium atualizado!';
                $_SESSION['tipo_mensagem'] = 'success';
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar status premium.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
            break;
            
        case 'alterar_admin':
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            
            $sql = "UPDATE usuarios SET is_admin = ? WHERE id = ?";
            if (executeQuery($pdo, $sql, [$is_admin, $usuario_id])) {
                $_SESSION['mensagem'] = 'Status de administrador atualizado!';
                $_SESSION['tipo_mensagem'] = 'success';
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar status de administrador.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
            break;
    }
    
    header('Location: usuarios.php');
    exit();
}

// Obter todos os usuários (excluindo administradores)
$sql = "SELECT u.*, 
               COUNT(g.id) as total_grupos,
               COUNT(j.id) as total_jogos
        FROM usuarios u
        LEFT JOIN grupo_membros gm ON u.id = gm.usuario_id AND gm.ativo = 1
        LEFT JOIN grupos g ON gm.grupo_id = g.id AND g.ativo = 1
        LEFT JOIN jogos j ON g.id = j.grupo_id
        WHERE u.ativo = 1 AND COALESCE(u.is_admin, 0) = 0
        GROUP BY u.id
        ORDER BY u.data_cadastro DESC";
$stmt = executeQuery($pdo, $sql);
$usuarios = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>


<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-users me-2"></i>Gerenciar Usuários
            </h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Lista de Usuários
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Grupos</th>
                        <th>Jogos</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo $usuario['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong>
                                <?php if ($usuario['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-primary">Você</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><?php echo $usuario['telefone'] ? htmlspecialchars($usuario['telefone']) : '-'; ?></td>
                            <td>
                                <?php if ($usuario['is_admin']): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php endif; ?>
                                <?php if ($usuario['is_premium']): ?>
                                    <span class="badge bg-warning text-dark">Premium</span>
                                <?php endif; ?>
                                <?php if (!$usuario['is_admin'] && !$usuario['is_premium']): ?>
                                    <span class="badge bg-secondary">Comum</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $usuario['total_grupos']; ?></td>
                            <td><?php echo $usuario['total_jogos']; ?></td>
                            <td><?php echo formatarData($usuario['data_cadastro'], 'd/m/Y'); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" onclick="confirmarRemocao(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nome']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Remoção -->
<div class="modal fade" id="confirmarRemocaoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Remoção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja remover o usuário <strong id="nomeUsuario"></strong>?</p>
                <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="remover_usuario">
                    <input type="hidden" name="usuario_id" id="usuarioIdRemover">
                    <button type="submit" class="btn btn-danger">Remover Usuário</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarRemocao(usuarioId, nomeUsuario) {
    document.getElementById('usuarioIdRemover').value = usuarioId;
    document.getElementById('nomeUsuario').textContent = nomeUsuario;
    new bootstrap.Modal(document.getElementById('confirmarRemocaoModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
