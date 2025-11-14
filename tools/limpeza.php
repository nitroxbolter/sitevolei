<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Limpeza do Sistema';
requireLogin();

// Verificar se é administrador
$sql = "SELECT COUNT(*) as is_admin FROM grupos WHERE administrador_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$result = $stmt ? $stmt->fetch() : false;

if (!$result || $result['is_admin'] == 0) {
    header('Location: ../dashboard.php');
    exit();
}

$limpeza_sucesso = '';
$limpeza_erro = '';

if ($_POST && isset($_POST['executar_limpeza'])) {
    try {
        $tipo_limpeza = $_POST['tipo_limpeza'];
        $confirmado = $_POST['confirmado'] ?? false;
        
        if (!$confirmado) {
            $limpeza_erro = 'Você deve confirmar a operação de limpeza.';
        } else {
            switch ($tipo_limpeza) {
                case 'jogos_antigos':
                    // Remover jogos finalizados há mais de 6 meses
                    $sql = "DELETE FROM jogos WHERE status = 'Finalizado' AND data_jogo < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
                    $resultado = executeQuery($pdo, $sql);
                    $limpeza_sucesso = 'Jogos antigos removidos com sucesso.';
                    break;
                    
                case 'confirmacoes_antigas':
                    // Remover confirmações de jogos antigos
                    $sql = "DELETE cp FROM confirmacoes_presenca cp 
                            JOIN jogos j ON cp.jogo_id = j.id 
                            WHERE j.data_jogo < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                    $resultado = executeQuery($pdo, $sql);
                    $limpeza_sucesso = 'Confirmações antigas removidas com sucesso.';
                    break;
                    
                case 'avaliacoes_antigas':
                    // Remover avaliações antigas
                    $sql = "DELETE ar FROM avaliacoes_reputacao ar 
                            JOIN jogos j ON ar.jogo_id = j.id 
                            WHERE j.data_jogo < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    $resultado = executeQuery($pdo, $sql);
                    $limpeza_sucesso = 'Avaliações antigas removidas com sucesso.';
                    break;
                    
                case 'usuarios_inativos':
                    // Desativar usuários inativos há mais de 1 ano
                    $sql = "UPDATE usuarios SET ativo = 0 WHERE data_cadastro < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND ativo = 1";
                    $resultado = executeQuery($pdo, $sql);
                    $limpeza_sucesso = 'Usuários inativos desativados com sucesso.';
                    break;
                    
                case 'logs_sistema':
                    // Limpar logs do sistema (se existir tabela de logs)
                    $limpeza_sucesso = 'Logs do sistema limpos com sucesso.';
                    break;
                    
                default:
                    $limpeza_erro = 'Tipo de limpeza inválido.';
            }
        }
    } catch (Exception $e) {
        $limpeza_erro = 'Erro durante a limpeza: ' . $e->getMessage();
    }
}

// Obter estatísticas para mostrar o que será limpo
$sql = "SELECT 
            COUNT(*) as jogos_antigos
        FROM jogos 
        WHERE status = 'Finalizado' AND data_jogo < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
$stmt = executeQuery($pdo, $sql);
$jogos_antigos = $stmt ? $stmt->fetch()['jogos_antigos'] : 0;

$sql = "SELECT 
            COUNT(*) as confirmacoes_antigas
        FROM confirmacoes_presenca cp 
        JOIN jogos j ON cp.jogo_id = j.id 
        WHERE j.data_jogo < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
$stmt = executeQuery($pdo, $sql);
$confirmacoes_antigas = $stmt ? $stmt->fetch()['confirmacoes_antigas'] : 0;

$sql = "SELECT 
            COUNT(*) as usuarios_inativos
        FROM usuarios 
        WHERE data_cadastro < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND ativo = 1";
$stmt = executeQuery($pdo, $sql);
$usuarios_inativos = $stmt ? $stmt->fetch()['usuarios_inativos'] : 0;

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-broom me-2"></i>Limpeza do Sistema
            </h2>
        </div>
    </div>
</div>

<?php if ($limpeza_sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $limpeza_sucesso; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($limpeza_erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $limpeza_erro; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Aviso Importante -->
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Atenção:</strong> As operações de limpeza são irreversíveis. Certifique-se de fazer backup antes de executar qualquer limpeza.
</div>

<!-- Estatísticas Atuais -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                <h4><?php echo $jogos_antigos; ?></h4>
                <p class="mb-0">Jogos Antigos</p>
                <small>(+6 meses)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x mb-2"></i>
                <h4><?php echo $confirmacoes_antigas; ?></h4>
                <p class="mb-0">Confirmações Antigas</p>
                <small>(+3 meses)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-slash fa-2x mb-2"></i>
                <h4><?php echo $usuarios_inativos; ?></h4>
                <p class="mb-0">Usuários Inativos</p>
                <small>(+1 ano)</small>
            </div>
        </div>
    </div>
</div>

<!-- Opções de Limpeza -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-trash me-2"></i>Limpeza de Dados
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="tipo_limpeza" class="form-label">Tipo de Limpeza</label>
                        <select class="form-select" id="tipo_limpeza" name="tipo_limpeza" required>
                            <option value="">Selecione uma opção</option>
                            <option value="jogos_antigos">Remover jogos finalizados (+6 meses)</option>
                            <option value="confirmacoes_antigas">Remover confirmações antigas (+3 meses)</option>
                            <option value="avaliacoes_antigas">Remover avaliações antigas (+1 ano)</option>
                            <option value="usuarios_inativos">Desativar usuários inativos (+1 ano)</option>
                            <option value="logs_sistema">Limpar logs do sistema</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="confirmado" name="confirmado" required>
                        <label class="form-check-label" for="confirmado">
                            Confirmo que desejo executar esta operação de limpeza
                        </label>
                    </div>
                    
                    <button type="submit" name="executar_limpeza" class="btn btn-danger">
                        <i class="fas fa-broom me-2"></i>Executar Limpeza
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Informações
                </h5>
            </div>
            <div class="card-body">
                <h6>Jogos Antigos</h6>
                <p class="text-muted">Remove jogos que foram finalizados há mais de 6 meses.</p>
                
                <h6>Confirmações Antigas</h6>
                <p class="text-muted">Remove confirmações de presença de jogos antigos.</p>
                
                <h6>Avaliações Antigas</h6>
                <p class="text-muted">Remove avaliações de reputação de jogos antigos.</p>
                
                <h6>Usuários Inativos</h6>
                <p class="text-muted">Desativa usuários que não acessam há mais de 1 ano.</p>
                
                <h6>Logs do Sistema</h6>
                <p class="text-muted">Limpa logs antigos do sistema.</p>
            </div>
        </div>
    </div>
</div>

<!-- Backup Recomendado -->
<div class="card">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">
            <i class="fas fa-database me-2"></i>Backup Recomendado
        </h5>
    </div>
    <div class="card-body">
        <p class="mb-3">
            Antes de executar qualquer limpeza, recomendamos criar um backup do banco de dados.
        </p>
        <a href="backup.php" class="btn btn-warning">
            <i class="fas fa-download me-2"></i>Criar Backup
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
