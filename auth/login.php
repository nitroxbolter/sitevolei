<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Login';

// Se já estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

$erro = '';
$sucesso = '';

// Verificar se foi redirecionado por necessidade de login
if (isset($_GET['msg']) && $_GET['msg'] === 'login_necessario') {
    $erro = 'Você precisa fazer login para acessar esta funcionalidade.';
}

// Processar login
if ($_POST && isset($_POST['acao']) && $_POST['acao'] === 'login') {
    $email = sanitizar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif (!validarEmail($email)) {
        $erro = 'Email inválido.';
    } else {
        $usuario = getUserByEmail($pdo, $email);
        
        if ($usuario && verificarSenha($senha, $usuario['senha'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_nome'] = $usuario['nome'];
            $_SESSION['user_email'] = $usuario['email'];
            
            header('Location: ../dashboard.php');
            exit();
        } else {
            $erro = 'Email ou senha incorretos.';
        }
    }
}

// Processar cadastro
if ($_POST && isset($_POST['acao']) && $_POST['acao'] === 'cadastro') {
    $nome = sanitizar($_POST['nome'] ?? '');
    $usuario_nome = sanitizar($_POST['usuario'] ?? '');
    $cpf = ''; // Temporariamente removido do cadastro
    $telefone = sanitizar($_POST['telefone'] ?? '');
    $email = sanitizar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $nivel = $_POST['nivel'] ?? '';
    $genero = sanitizar($_POST['genero'] ?? '');
    $disponibilidade = sanitizar($_POST['disponibilidade'] ?? '');
    $aceita_termos = isset($_POST['aceita_termos']);
    
    // Validações
    if (empty($nome) || empty($usuario_nome) || empty($telefone) || empty($email) || empty($senha) || empty($confirmar_senha) || empty($genero)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!validarEmail($email)) {
        $erro = 'Email inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } elseif (!$aceita_termos) {
        $erro = 'Você deve aceitar os termos de uso.';
    } elseif (getUserByEmail($pdo, $email)) {
        $erro = 'Este email já está cadastrado.';
    } else {
        // Verificar se usuário já existe
        $sql_check = "SELECT id FROM usuarios WHERE usuario = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$usuario_nome]);
        if ($stmt_check && $stmt_check->fetch()) {
            $erro = 'Nome de usuário já cadastrado.';
        } else {
            // Formatar telefone (remover caracteres não numéricos, manter apenas números)
            $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
            $cpf_limpo = null; // CPF temporariamente removido
            
            // Validar gênero
            if (!in_array($genero, ['Masculino', 'Feminino'])) {
                $erro = 'Gênero inválido.';
            } else {
                // Cadastrar usuário (começa com 100 pontos de reputação)
                $senha_hash = hashSenha($senha);
                $sql = "INSERT INTO usuarios (nome, usuario, cpf, telefone, email, senha, nivel, genero, disponibilidade, reputacao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 100)";
                
                if (executeQuery($pdo, $sql, [$nome, $usuario_nome, $cpf_limpo, $telefone_limpo, $email, $senha_hash, $nivel, $genero, $disponibilidade])) {
                    $sucesso = 'Cadastro realizado com sucesso! Agora você pode fazer login.';
                    // Limpar formulário
                    unset($_POST);
                } else {
                    $erro = 'Erro ao cadastrar usuário. Tente novamente.';
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<style>
.login-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem 0;
}

.login-card {
    max-width: 450px;
    width: 100%;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.login-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px 20px 0 0;
    padding: 2rem;
    text-align: center;
    color: white;
}

.login-body {
    padding: 2rem;
    background: white;
}

.form-floating {
    margin-bottom: 1rem;
}

.btn-login {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.btn-login:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
}

.divider::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 100%;
    height: 1px;
    background: #e9ecef;
}

.divider span {
    background: white;
    padding: 0 1rem;
    position: relative;
    color: #6c757d;
}

.modal-content {
    border-radius: 15px;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
}

.input-group-text {
    background: #f8f9fa;
}
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-volleyball-ball fa-3x mb-3"></i>
            <h2 class="mb-0">Comunidade do Vôlei</h2>
            <p class="mb-0 mt-2">Entre com sua conta</p>
        </div>
        
        <div class="login-body">
            <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($erro); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($sucesso); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulário de Login -->
            <form method="POST" id="formLogin">
                <input type="hidden" name="acao" value="login">
                
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Email" value="<?php echo isset($_POST['email']) && $_POST['acao'] === 'login' ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="senha" name="senha" 
                           placeholder="Senha" required>
                    <label for="senha"><i class="fas fa-lock me-2"></i>Senha</label>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
                    <label class="form-check-label" for="lembrar">
                        Lembrar de mim
                    </label>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-login btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Entrar
                    </button>
                </div>
                
                <div class="divider">
                    <span>ou</span>
                </div>
                
                <div class="d-grid">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cadastroModal">
                        <i class="fas fa-user-plus me-2"></i>Criar Nova Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Cadastro -->
<div class="modal fade" id="cadastroModal" tabindex="-1" aria-labelledby="cadastroModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cadastroModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Cadastrar-se
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formCadastro">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="cadastro">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome_cadastro" class="form-label">
                                <i class="fas fa-user me-1"></i>Nome Completo *
                            </label>
                            <input type="text" class="form-control" id="nome_cadastro" name="nome" 
                                   value="<?php echo isset($_POST['nome']) && $_POST['acao'] === 'cadastro' ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="usuario_cadastro" class="form-label">
                                <i class="fas fa-at me-1"></i>Nome de Usuário *
                            </label>
                            <input type="text" class="form-control" id="usuario_cadastro" name="usuario" 
                                   value="<?php echo isset($_POST['usuario']) && $_POST['acao'] === 'cadastro' ? htmlspecialchars($_POST['usuario']) : ''; ?>" 
                                   pattern="[a-zA-Z0-9_]+" title="Apenas letras, números e underscore" required>
                            <small class="form-text text-muted">Apenas letras, números e underscore</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="telefone_cadastro" class="form-label">
                                <i class="fas fa-phone me-1"></i>Telefone *
                            </label>
                            <input type="text" class="form-control" id="telefone_cadastro" name="telefone" 
                                   placeholder="(00) 00000-0000"
                                   value="<?php echo isset($_POST['telefone']) && $_POST['acao'] === 'cadastro' ? htmlspecialchars($_POST['telefone']) : ''; ?>" 
                                   maxlength="15" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email_cadastro" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email *
                            </label>
                            <input type="email" class="form-control" id="email_cadastro" name="email" 
                                   value="<?php echo isset($_POST['email']) && $_POST['acao'] === 'cadastro' ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nivel_cadastro" class="form-label">
                                <i class="fas fa-star me-1"></i>Nível *
                            </label>
                            <select class="form-select" id="nivel_cadastro" name="nivel" required>
                                <option value="">Selecione seu nível</option>
                                <option value="Iniciante" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Iniciante') ? 'selected' : ''; ?>>Iniciante</option>
                                <option value="Intermediário" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Intermediário') ? 'selected' : ''; ?>>Intermediário</option>
                                <option value="Avançado" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Avançado') ? 'selected' : ''; ?>>Avançado</option>
                                <option value="Profissional" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'Profissional') ? 'selected' : ''; ?>>Profissional</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="genero_cadastro" class="form-label">
                                <i class="fas fa-venus-mars me-1"></i>Gênero *
                            </label>
                            <select class="form-select" id="genero_cadastro" name="genero" required>
                                <option value="">Selecione seu gênero</option>
                                <option value="Masculino" <?php echo (isset($_POST['genero']) && $_POST['genero'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Feminino" <?php echo (isset($_POST['genero']) && $_POST['genero'] === 'Feminino') ? 'selected' : ''; ?>>Feminino</option>
                            </select>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>É importante preencher para fins de montar torneios e times na funcionalidade do sistema.
                            </small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="disponibilidade_cadastro" class="form-label">
                                <i class="fas fa-clock me-1"></i>Disponibilidade
                            </label>
                            <textarea class="form-control" id="disponibilidade_cadastro" name="disponibilidade" rows="2" 
                                      placeholder="Ex: Finais de semana, manhãs..."><?php echo isset($_POST['disponibilidade']) && $_POST['acao'] === 'cadastro' ? htmlspecialchars($_POST['disponibilidade']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="senha_cadastro" class="form-label">
                                <i class="fas fa-lock me-1"></i>Senha *
                            </label>
                            <input type="password" class="form-control" id="senha_cadastro" name="senha" required>
                            <small class="form-text text-muted">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirmar_senha_cadastro" class="form-label">
                                <i class="fas fa-lock me-1"></i>Confirmar Senha *
                            </label>
                            <input type="password" class="form-control" id="confirmar_senha_cadastro" name="confirmar_senha" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="aceita_termos" name="aceita_termos" required>
                        <label class="form-check-label" for="aceita_termos">
                            Aceito os <a href="#" data-bs-toggle="modal" data-bs-target="#termosModal">termos de uso</a> *
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar
                    </button>
                </div>
            </form>
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

<script>
// Máscara para Telefone
document.getElementById('telefone_cadastro').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length <= 10) {
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
        }
        e.target.value = value;
    }
});

// Validar confirmação de senha
document.getElementById('confirmar_senha_cadastro').addEventListener('blur', function() {
    const senha = document.getElementById('senha_cadastro').value;
    const confirmar = this.value;
    
    if (senha !== confirmar) {
        this.setCustomValidity('As senhas não coincidem');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});

// Manter modal aberto em caso de erro
<?php if ($erro && isset($_POST['acao']) && $_POST['acao'] === 'cadastro'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var cadastroModal = new bootstrap.Modal(document.getElementById('cadastroModal'));
    cadastroModal.show();
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>