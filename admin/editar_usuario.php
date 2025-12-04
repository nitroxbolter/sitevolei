<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Editar Usuário';
requireAdmin($pdo);

// Debug: verificar ID recebido
$id_get = $_GET['id'] ?? null;
error_log("DEBUG editar_usuario.php: ID recebido via GET = " . var_export($id_get, true));
error_log("DEBUG editar_usuario.php: \$_GET completo = " . print_r($_GET, true));
error_log("DEBUG editar_usuario.php: \$_SESSION['user_id'] = " . ($_SESSION['user_id'] ?? 'não definido'));

$usuario_id = (int)($id_get ?? 0);
error_log("DEBUG editar_usuario.php: usuario_id após cast = $usuario_id");

if (!$usuario_id) {
    $_SESSION['mensagem'] = 'ID do usuário não fornecido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: usuarios.php');
    exit();
}

// Buscar dados do usuário usando prepared statement com bindParam
try {
    $sql = "SELECT * FROM usuarios WHERE id = ? LIMIT 1";
    error_log("DEBUG editar_usuario.php: Preparando query: $sql com ID = $usuario_id");
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Verificar quantas linhas foram retornadas
    $rowCount = $stmt->rowCount();
    error_log("DEBUG editar_usuario.php: rowCount = $rowCount");
    
    // Buscar o resultado
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        error_log("DEBUG editar_usuario.php: Usuário retornado - ID={$usuario['id']}, Nome={$usuario['nome']}");
        
        // Verificar se o ID corresponde
        if ($usuario['id'] != $usuario_id) {
            error_log("ERRO CRÍTICO: Query retornou ID errado! Esperado: $usuario_id, Retornado: {$usuario['id']}");
            
            // Tentar novamente com query direta e verificar se o usuário existe
            $sql_direct = "SELECT id, nome FROM usuarios WHERE id = " . (int)$usuario_id;
            error_log("DEBUG editar_usuario.php: Tentando query direta: $sql_direct");
            $stmt_direct = $pdo->query($sql_direct);
            $usuario_direct = $stmt_direct ? $stmt_direct->fetch(PDO::FETCH_ASSOC) : null;
            error_log("DEBUG editar_usuario.php: Resultado query direta: " . ($usuario_direct ? "ID={$usuario_direct['id']}, Nome={$usuario_direct['nome']}" : "NULL"));
            
            if ($usuario_direct && $usuario_direct['id'] == $usuario_id) {
                // Se a query direta funcionou, buscar todos os dados
                $sql_full = "SELECT * FROM usuarios WHERE id = " . (int)$usuario_id . " LIMIT 1";
                $stmt_full = $pdo->query($sql_full);
                $usuario = $stmt_full ? $stmt_full->fetch(PDO::FETCH_ASSOC) : null;
                error_log("DEBUG editar_usuario.php: Usando resultado da query direta completa");
            } else {
                $usuario = null;
            }
        }
    } else {
        error_log("DEBUG editar_usuario.php: Nenhum usuário encontrado para ID = $usuario_id");
    }
} catch (PDOException $e) {
    error_log("DEBUG editar_usuario.php: Erro na query: " . $e->getMessage());
    $usuario = null;
}

if (!$usuario) {
    error_log("DEBUG editar_usuario.php: Usuário não encontrado para ID = $usuario_id");
    $_SESSION['mensagem'] = 'Usuário não encontrado. ID: ' . $usuario_id;
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: usuarios.php');
    exit();
}

// Debug: verificar se está buscando o usuário correto
error_log("DEBUG editar_usuario.php: Usuário encontrado - ID: {$usuario['id']}, Nome: {$usuario['nome']}, Email: {$usuario['email']}");
if ($usuario['id'] != $usuario_id) {
    error_log("ERRO: Usuário buscado (ID: {$usuario['id']}) não corresponde ao ID solicitado ($usuario_id)");
}

// Processar formulário
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'alterar_dados':
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $reputacao = (int)($_POST['reputacao'] ?? 0);
            
            if (empty($nome) || empty($email)) {
                $_SESSION['mensagem'] = 'Nome e email são obrigatórios.';
                $_SESSION['tipo_mensagem'] = 'danger';
            } else {
                // Validar email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['mensagem'] = 'Email inválido.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                } else {
                    // Verificar se email já existe em outro usuário
                    $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
                    $stmt_check = executeQuery($pdo, $sql_check, [$email, $usuario_id]);
                    if ($stmt_check && $stmt_check->fetch()) {
                        $_SESSION['mensagem'] = 'Este email já está cadastrado para outro usuário.';
                        $_SESSION['tipo_mensagem'] = 'danger';
                    } else {
                        // Limpar telefone (apenas números)
                        $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
                        
                        $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, reputacao = ? WHERE id = ?";
                        if (executeQuery($pdo, $sql, [$nome, $email, $telefone_limpo, $reputacao, $usuario_id])) {
                            $_SESSION['mensagem'] = 'Dados do usuário atualizados com sucesso!';
                            $_SESSION['tipo_mensagem'] = 'success';
                            header('Location: usuarios.php');
                            exit();
                        } else {
                            $_SESSION['mensagem'] = 'Erro ao atualizar dados do usuário.';
                            $_SESSION['tipo_mensagem'] = 'danger';
                        }
                    }
                }
            }
            break;
            
        case 'alterar_premium':
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            $premium_expira = !empty($_POST['premium_expira']) ? $_POST['premium_expira'] : null;
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            
            $sql = "UPDATE usuarios SET is_premium = ?, premium_expira_em = ?, is_admin = ? WHERE id = ?";
            if (executeQuery($pdo, $sql, [$is_premium, $premium_expira, $is_admin, $usuario_id])) {
                $_SESSION['mensagem'] = 'Dados do usuário atualizados com sucesso!';
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: usuarios.php');
                exit();
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar dados do usuário.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
            break;
            
        case 'alterar_senha':
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';
            
            if (empty($nova_senha) || empty($confirmar_senha)) {
                $_SESSION['mensagem'] = 'Preencha ambos os campos de senha.';
                $_SESSION['tipo_mensagem'] = 'danger';
            } elseif ($nova_senha !== $confirmar_senha) {
                $_SESSION['mensagem'] = 'As senhas não coincidem.';
                $_SESSION['tipo_mensagem'] = 'danger';
            } elseif (strlen($nova_senha) < 6) {
                $_SESSION['mensagem'] = 'A senha deve ter pelo menos 6 caracteres.';
                $_SESSION['tipo_mensagem'] = 'danger';
            } else {
                $hash = hashSenha($nova_senha);
                $sql = "UPDATE usuarios SET senha = ? WHERE id = ?";
                if (executeQuery($pdo, $sql, [$hash, $usuario_id])) {
                    $_SESSION['mensagem'] = 'Senha atualizada com sucesso!';
                    $_SESSION['tipo_mensagem'] = 'success';
                    header('Location: usuarios.php');
                    exit();
                } else {
                    $_SESSION['mensagem'] = 'Erro ao atualizar senha.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
    }
    
    // Recarregar dados do usuário após atualização
    $sql_reload = "SELECT * FROM usuarios WHERE id = " . (int)$usuario_id . " LIMIT 1";
    $stmt_reload = $pdo->query($sql_reload);
    $usuario = $stmt_reload ? $stmt_reload->fetch(PDO::FETCH_ASSOC) : null;
    error_log("DEBUG editar_usuario.php: Após recarregar - Usuário ID: " . ($usuario['id'] ?? 'NULL'));
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-user-edit me-2"></i>Editar Usuário
            </h2>
            <a href="usuarios.php" class="btn btn-outline-secondary">
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

<!-- Debug: Mostrar informações do ID recebido -->
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <strong>DEBUG:</strong> 
    ID recebido via GET: <code><?php echo htmlspecialchars($_GET['id'] ?? 'não definido'); ?></code> | 
    ID após cast: <code><?php echo $usuario_id; ?></code> | 
    Usuário encontrado: <code>ID: <?php echo $usuario['id']; ?>, Nome: <?php echo htmlspecialchars($usuario['nome']); ?></code>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($usuario['nome']); ?>
                    <small class="ms-2 opacity-75">(ID: <?php echo $usuario['id']; ?>)</small>
                </h5>
            </div>
            <div class="card-body">
                <!-- Dados Pessoais -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="acao" value="alterar_dados">
                    <h6 class="mb-3"><i class="fas fa-user me-2"></i>Dados Pessoais</h6>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" 
                               value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" 
                               placeholder="Ex: (55) 99999-9999">
                    </div>
                    
                    <div class="mb-3">
                        <label for="reputacao" class="form-label">Pontos (Reputação)</label>
                        <input type="number" class="form-control" id="reputacao" name="reputacao" 
                               value="<?php echo (int)($usuario['reputacao'] ?? 0); ?>" min="0">
                        <small class="form-text text-muted">
                            Pontos de reputação do usuário
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar Dados Pessoais
                    </button>
                </form>
                
                <hr>
                
                <!-- Planos e Permissões -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="acao" value="alterar_premium">
                    <h6 class="mb-3"><i class="fas fa-crown me-2"></i>Planos e Permissões</h6>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_premium" 
                                   name="is_premium" <?php echo $usuario['is_premium'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_premium">
                                <i class="fas fa-crown me-1"></i>Usuário Premium
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="premium_expira" class="form-label">Premium Expira em:</label>
                        <input type="datetime-local" class="form-control" id="premium_expira" 
                               name="premium_expira" value="<?php 
                               if (!empty($usuario['premium_expira_em']) && $usuario['premium_expira_em'] !== '0000-00-00 00:00:00' && strtotime($usuario['premium_expira_em']) !== false) {
                                   echo date('Y-m-d\TH:i', strtotime($usuario['premium_expira_em']));
                               }
                               ?>">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_admin" 
                                   name="is_admin" <?php echo $usuario['is_admin'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_admin">
                                <i class="fas fa-shield-alt me-1"></i>Administrador
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar Planos e Permissões
                    </button>
                </form>
                
                <hr>
                
                <!-- Alterar Senha -->
                <form method="POST">
                    <input type="hidden" name="acao" value="alterar_senha">
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Alterar Senha</h6>
                    
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" minlength="6" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-1"></i>Alterar Senha
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

