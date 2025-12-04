<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Cadastro';

// Se já estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

$erro = '';
$sucesso = '';

if ($_POST) {
    $nome = sanitizar($_POST['nome']);
    $usuario_nome = sanitizar($_POST['usuario'] ?? '');
    $cpf = sanitizar($_POST['cpf'] ?? '');
    $telefone = sanitizar($_POST['telefone'] ?? '');
    $email = sanitizar($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $nivel = $_POST['nivel'];
    $disponibilidade = sanitizar($_POST['disponibilidade']);
    $data_aniversario = isset($_POST['data_aniversario']) && !empty($_POST['data_aniversario']) ? $_POST['data_aniversario'] : null;
    
    // Validações
    if (empty($nome) || empty($usuario_nome) || empty($cpf) || empty($telefone) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!validarEmail($email)) {
        $erro = 'Email inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } elseif (getUserByEmail($pdo, $email)) {
        $erro = 'Este email já está cadastrado.';
    } else {
        // Verificar se usuário ou CPF já existem
        $sql_check = "SELECT id FROM usuarios WHERE usuario = ? OR cpf = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$usuario_nome, $cpf]);
        if ($stmt_check && $stmt_check->fetch()) {
            $erro = 'Nome de usuário ou CPF já cadastrado.';
        } else {
            // Normalizar CPF e telefone (apenas números)
            $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
            $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);

            // Verificar se campo data_aniversario existe na tabela
            try {
                $sql_check = "SHOW COLUMNS FROM usuarios LIKE 'data_aniversario'";
                $stmt_check = $pdo->query($sql_check);
                $aniversario_exists = $stmt_check && $stmt_check->rowCount() > 0;
                
                if (!$aniversario_exists) {
                    $sql_add = "ALTER TABLE usuarios ADD COLUMN data_aniversario DATE DEFAULT NULL AFTER disponibilidade";
                    $pdo->exec($sql_add);
                    $aniversario_exists = true;
                }
            } catch (Exception $e) {
                $aniversario_exists = false;
            }
            
            // Cadastrar usuário com reputação inicial 100
            $senha_hash = hashSenha($senha);
            
            if ($aniversario_exists && $data_aniversario) {
                $sql = "INSERT INTO usuarios (nome, usuario, cpf, telefone, email, senha, nivel, disponibilidade, data_aniversario, reputacao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 100)";
                $params = [$nome, $usuario_nome, $cpf_limpo, $telefone_limpo, $email, $senha_hash, $nivel, $disponibilidade, $data_aniversario];
            } else {
                $sql = "INSERT INTO usuarios (nome, usuario, cpf, telefone, email, senha, nivel, disponibilidade, reputacao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100)";
                $params = [$nome, $usuario_nome, $cpf_limpo, $telefone_limpo, $email, $senha_hash, $nivel, $disponibilidade];
            }
            
            if (executeQuery($pdo, $sql, $params)) {
                $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';
                // Limpar formulário
                $_POST = [];
            } else {
                $erro = 'Erro ao cadastrar usuário. Tente novamente.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-success text-white text-center">
                <h4 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>Cadastrar-se
                </h4>
            </div>
            <div class="card-body p-4">
                <?php if ($erro): ?>
                    <?php echo exibirMensagem('erro', $erro); ?>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <?php echo exibirMensagem('sucesso', $sucesso); ?>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome" class="form-label">
                                <i class="fas fa-user me-1"></i>Nome Completo *
                            </label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="usuario" class="form-label">
                                <i class="fas fa-id-badge me-1"></i>Usuário *
                            </label>
                            <input type="text" class="form-control" id="usuario" name="usuario" 
                                   value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email *
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cpf" class="form-label">
                                <i class="fas fa-id-card me-1"></i>CPF *
                            </label>
                            <input type="text" class="form-control" id="cpf" name="cpf" 
                                   value="<?php echo isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="senha" class="form-label">
                                <i class="fas fa-lock me-1"></i>Senha *
                            </label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                            <div class="form-text">Mínimo 6 caracteres</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirmar_senha" class="form-label">
                                <i class="fas fa-lock me-1"></i>Confirmar Senha *
                            </label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefone" class="form-label">
                                <i class="fas fa-phone me-1"></i>Telefone *
                            </label>
                            <input type="text" class="form-control" id="telefone" name="telefone" 
                                   value="<?php echo isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="nivel" class="form-label">
                                <i class="fas fa-star me-1"></i>Nível *
                            </label>
                            <select class="form-select" id="nivel" name="nivel" required>
                                <option value="">Selecione seu nível</option>
                                <option value="Iniciante" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Iniciante') ? 'selected' : ''; ?>>Iniciante</option>
                                <option value="Intermediário" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Intermediário') ? 'selected' : ''; ?>>Intermediário</option>
                                <option value="Avançado" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Avançado') ? 'selected' : ''; ?>>Avançado</option>
                                <option value="Profissional" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Profissional') ? 'selected' : ''; ?>>Profissional</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_aniversario" class="form-label">
                                <i class="fas fa-birthday-cake me-1"></i>Data de Aniversário
                            </label>
                            <input type="date" class="form-control" id="data_aniversario" name="data_aniversario" 
                                   value="<?php echo isset($_POST['data_aniversario']) ? htmlspecialchars($_POST['data_aniversario']) : ''; ?>">
                            <div class="form-text">Opcional - para exibir aniversariantes no grupo</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="disponibilidade" class="form-label">
                                <i class="fas fa-clock me-1"></i>Disponibilidade
                            </label>
                            <textarea class="form-control" id="disponibilidade" name="disponibilidade" rows="3" 
                                      placeholder="Ex: Finais de semana, manhãs, noites..."><?php echo isset($_POST['disponibilidade']) ? htmlspecialchars($_POST['disponibilidade']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="termos" required>
                        <label class="form-check-label" for="termos">
                            Aceito os <a href="#" data-bs-toggle="modal" data-bs-target="#termosModal">termos de uso</a> *
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Cadastrar
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-2">Já tem uma conta?</p>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Entrar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Termos de Uso -->
<div class="modal fade" id="termosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Termos de Uso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Aceitação dos Termos</h6>
                <p>Ao utilizar a plataforma Comunidade do Vôlei, você concorda com estes termos de uso.</p>
                
                <h6>2. Uso da Plataforma</h6>
                <p>A plataforma é destinada exclusivamente para organização de jogos e torneios de vôlei.</p>
                
                <h6>3. Responsabilidades do Usuário</h6>
                <p>O usuário é responsável por manter suas informações atualizadas e por confirmar presença nos jogos.</p>
                
                <h6>4. Sistema de Reputação</h6>
                <p>A reputação é calculada com base na pontualidade e participação nos jogos.</p>
                
                <h6>5. Privacidade</h6>
                <p>Suas informações pessoais são protegidas e não serão compartilhadas com terceiros.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
