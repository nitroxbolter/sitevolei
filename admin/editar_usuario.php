<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Editar Usuário';
requireAdmin($pdo);

// Verificar ID recebido
$id_get = isset($_GET['id']) ? $_GET['id'] : null;
$usuario_id = 0;
if ($id_get !== null) {
    $usuario_id = (int)$id_get;
    if ($usuario_id == 0 && $id_get !== '0' && $id_get !== 0) {
        $usuario_id = 0;
    }
}

if (!$usuario_id) {
    $_SESSION['mensagem'] = 'ID do usuário não fornecido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: usuarios.php');
    exit();
}

// Buscar dados do usuário no banco de dados
// IMPORTANTE: Usar $usuario_editado ao invés de $usuario para evitar conflito com a variável global
// definida no header.php que contém os dados do usuário logado
$usuario_editado = null;
if ($usuario_id > 0) {
    try {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        $sql = "SELECT id, nome, usuario, cpf, telefone, email, nivel, genero, reputacao, is_premium, premium_expira_em FROM usuarios WHERE id = :usuario_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $usuario_editado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuário: " . $e->getMessage());
        $usuario_editado = null;
    }
}

if (!$usuario_editado) {
    $_SESSION['mensagem'] = 'Usuário não encontrado. ID: ' . $usuario_id;
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: usuarios.php');
    exit();
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'alterar_senha') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    $erros = [];
    
    if (empty($nova_senha) || empty($confirmar_senha)) {
        $erros[] = 'Preencha ambos os campos de senha.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $erros[] = 'As senhas não coincidem.';
    } elseif (strlen($nova_senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    
    if (empty($erros)) {
        try {
            $hash = hashSenha($nova_senha);
            $sql = "UPDATE usuarios SET senha = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$hash, $usuario_id]);
            
            if ($result) {
                $_SESSION['mensagem'] = 'Senha atualizada com sucesso!';
                $_SESSION['tipo_mensagem'] = 'success';
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar senha.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } catch (PDOException $e) {
            error_log("Erro ao atualizar senha: " . $e->getMessage());
            $_SESSION['mensagem'] = 'Erro ao atualizar senha.';
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } else {
        $_SESSION['mensagem'] = implode('<br>', $erros);
        $_SESSION['tipo_mensagem'] = 'danger';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    $nome = trim($_POST['nome'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nivel = trim($_POST['nivel'] ?? '');
    $genero = trim($_POST['genero'] ?? '');
    $reputacao = (int)($_POST['reputacao'] ?? 0);
    $status_plano = trim($_POST['status_plano'] ?? 'comum');
    
    // Definir premium baseado no status
    $is_premium = ($status_plano === 'premium') ? 1 : 0;
    
    // Se mudou para premium, adicionar 30 dias automaticamente
    $premium_expira = null;
    if ($is_premium) {
        // Verificar se já tem premium ativo e não expirado
        $premium_atual = $usuario_editado['is_premium'] ?? 0;
        $data_expiracao_atual = $usuario_editado['premium_expira_em'] ?? null;
        
        // Se já tem premium e ainda não expirou, manter a data atual
        if ($premium_atual && $data_expiracao_atual && $data_expiracao_atual !== '0000-00-00 00:00:00') {
            $data_expiracao_timestamp = strtotime($data_expiracao_atual);
            $data_atual = time();
            
            // Se ainda não expirou, manter a data atual
            if ($data_expiracao_timestamp > $data_atual) {
                $premium_expira = $data_expiracao_atual;
            } else {
                // Se expirou ou está mudando de comum para premium, adicionar 30 dias a partir de hoje
                $premium_expira = date('Y-m-d H:i:s', strtotime('+30 days'));
            }
        } else {
            // Se não tem premium ou está mudando de comum para premium, adicionar 30 dias a partir de hoje
            $premium_expira = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
    }
    
    // Validações
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = 'Nome é obrigatório.';
    }
    
    if (empty($usuario)) {
        $erros[] = 'Usuário (login) é obrigatório.';
    } else {
        // Verificar se usuário já existe em outro registro
        $sql_check_usuario = "SELECT id FROM usuarios WHERE usuario = ? AND id != ?";
        $stmt_check_usuario = executeQuery($pdo, $sql_check_usuario, [$usuario, $usuario_id]);
        if ($stmt_check_usuario && $stmt_check_usuario->fetch()) {
            $erros[] = 'Este nome de usuário já está cadastrado para outro usuário.';
        }
    }
    
    if (empty($email)) {
        $erros[] = 'Email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido.';
    } else {
        // Verificar se email já existe em outro usuário
        $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$email, $usuario_id]);
        if ($stmt_check && $stmt_check->fetch()) {
            $erros[] = 'Este email já está cadastrado para outro usuário.';
        }
    }
    
    // Limpar CPF (apenas números)
    $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
    
    // Limpar telefone (apenas números)
    $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
    
    if (empty($erros)) {
        try {
            $sql = "UPDATE usuarios SET nome = ?, usuario = ?, cpf = ?, telefone = ?, email = ?, nivel = ?, genero = ?, reputacao = ?, is_premium = ?, premium_expira_em = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $nome,
                $usuario,
                $cpf_limpo ?: null,
                $telefone_limpo ?: null,
                $email,
                $nivel ?: null,
                $genero ?: null,
                $reputacao,
                $is_premium,
                $premium_expira ?: null,
                $usuario_id
            ]);
            
            if ($result) {
                $_SESSION['mensagem'] = 'Dados do usuário atualizados com sucesso!';
                $_SESSION['tipo_mensagem'] = 'success';
                
                // Recarregar dados do usuário
                $sql_reload = "SELECT id, nome, usuario, cpf, telefone, email, nivel, genero, reputacao, is_premium, premium_expira_em FROM usuarios WHERE id = ? LIMIT 1";
                $stmt_reload = $pdo->prepare($sql_reload);
                $stmt_reload->bindValue(1, $usuario_id, PDO::PARAM_INT);
                $stmt_reload->execute();
                $usuario_editado = $stmt_reload->fetch(PDO::FETCH_ASSOC);
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar dados do usuário.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        } catch (PDOException $e) {
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            $_SESSION['mensagem'] = 'Erro ao atualizar dados do usuário: ' . $e->getMessage();
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    } else {
        $_SESSION['mensagem'] = implode('<br>', $erros);
        $_SESSION['tipo_mensagem'] = 'danger';
    }
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

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($usuario_editado['nome']); ?>
                    <small class="ms-2 opacity-75">(ID: <?php echo $usuario_editado['id']; ?>)</small>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <h6 class="mb-3"><i class="fas fa-user me-2"></i>Dados Pessoais</h6>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($usuario_editado['nome'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuário (Login) *</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" 
                               value="<?php echo htmlspecialchars($usuario_editado['usuario'] ?? ''); ?>" required>
                        <small class="form-text text-muted">Nome de usuário para login no sistema</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" 
                               value="<?php echo htmlspecialchars($usuario_editado['cpf'] ?? ''); ?>" 
                               placeholder="000.000.000-00">
                        <small class="form-text text-muted">Apenas números serão salvos</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" 
                               value="<?php echo htmlspecialchars($usuario_editado['telefone'] ?? ''); ?>" 
                               placeholder="(00) 00000-0000">
                        <small class="form-text text-muted">Apenas números serão salvos</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($usuario_editado['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível</label>
                        <select class="form-select" id="nivel" name="nivel">
                            <option value="">Selecione...</option>
                            <option value="Iniciante" <?php echo ($usuario_editado['nivel'] ?? '') === 'Iniciante' ? 'selected' : ''; ?>>Iniciante</option>
                            <option value="Intermediário" <?php echo ($usuario_editado['nivel'] ?? '') === 'Intermediário' ? 'selected' : ''; ?>>Intermediário</option>
                            <option value="Avançado" <?php echo ($usuario_editado['nivel'] ?? '') === 'Avançado' ? 'selected' : ''; ?>>Avançado</option>
                            <option value="Profissional" <?php echo ($usuario_editado['nivel'] ?? '') === 'Profissional' ? 'selected' : ''; ?>>Profissional</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="genero" class="form-label">Gênero</label>
                        <select class="form-select" id="genero" name="genero">
                            <option value="">Selecione...</option>
                            <option value="Masculino" <?php echo ($usuario_editado['genero'] ?? '') === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Feminino" <?php echo ($usuario_editado['genero'] ?? '') === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo ($usuario_editado['genero'] ?? '') === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                            <option value="Prefiro não informar" <?php echo ($usuario_editado['genero'] ?? '') === 'Prefiro não informar' ? 'selected' : ''; ?>>Prefiro não informar</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reputacao" class="form-label">Reputação (Pontos)</label>
                        <input type="number" class="form-control" id="reputacao" name="reputacao" 
                               value="<?php echo (int)($usuario_editado['reputacao'] ?? 0); ?>" min="0">
                        <small class="form-text text-muted">Pontos de reputação do usuário</small>
                    </div>
                    
                    <hr class="my-3">
                    
                    <h6 class="mb-3"><i class="fas fa-crown me-2"></i>Status do Plano</h6>
                    
                    <div class="mb-3">
                        <label for="status_plano" class="form-label">Plano do Usuário</label>
                        <select class="form-select" id="status_plano" name="status_plano">
                            <option value="comum" <?php echo ($usuario_editado['is_premium'] ?? 0) == 0 ? 'selected' : ''; ?>>Comum</option>
                            <option value="premium" <?php echo ($usuario_editado['is_premium'] ?? 0) == 1 ? 'selected' : ''; ?>>Premium</option>
                        </select>
                        <small class="form-text text-muted">
                            Ao selecionar Premium e salvar, automaticamente será adicionado 30 dias de premium. 
                            <?php 
                            if (!empty($usuario_editado['premium_expira_em']) && $usuario_editado['premium_expira_em'] !== '0000-00-00 00:00:00' && strtotime($usuario_editado['premium_expira_em']) !== false) {
                                $data_expiracao = strtotime($usuario_editado['premium_expira_em']);
                                $data_atual = time();
                                if ($data_expiracao > $data_atual) {
                                    $dias_restantes = ceil(($data_expiracao - $data_atual) / (60 * 60 * 24));
                                    echo "Premium atual expira em: " . date('d/m/Y', $data_expiracao) . " ({$dias_restantes} dias restantes)";
                                }
                            }
                            ?>
                        </small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <!-- Alterar Senha -->
                <form method="POST">
                    <input type="hidden" name="acao" value="alterar_senha">
                    
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Alterar Senha</h6>
                    
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                               minlength="6" placeholder="Mínimo 6 caracteres">
                        <small class="form-text text-muted">A senha deve ter pelo menos 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                               minlength="6" placeholder="Digite a senha novamente">
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-1"></i>Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
