<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Backup do Sistema';
requireLogin();

// Verificar se é administrador
$sql = "SELECT COUNT(*) as is_admin FROM grupos WHERE administrador_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$result = $stmt ? $stmt->fetch() : false;

if (!$result || $result['is_admin'] == 0) {
    header('Location: ../dashboard.php');
    exit();
}

$backup_sucesso = '';
$backup_erro = '';

if ($_POST && isset($_POST['criar_backup'])) {
    try {
        $backup_dir = '../backups/';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Comando mysqldump (ajuste conforme sua configuração)
        $command = "mysqldump -u root -p comunidade_volei > " . $backup_file;
        
        // Executar backup
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $backup_sucesso = 'Backup criado com sucesso: ' . basename($backup_file);
        } else {
            $backup_erro = 'Erro ao criar backup. Verifique as configurações do MySQL.';
        }
    } catch (Exception $e) {
        $backup_erro = 'Erro: ' . $e->getMessage();
    }
}

// Listar backups existentes
$backup_dir = '../backups/';
$backups = [];
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'nome' => $file,
                'tamanho' => filesize($backup_dir . $file),
                'data' => filemtime($backup_dir . $file)
            ];
        }
    }
    // Ordenar por data (mais recente primeiro)
    usort($backups, function($a, $b) {
        return $b['data'] - $a['data'];
    });
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-database me-2"></i>Backup do Sistema
            </h2>
        </div>
    </div>
</div>

<?php if ($backup_sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $backup_sucesso; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($backup_erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $backup_erro; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Criar Backup -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-plus me-2"></i>Criar Novo Backup
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Crie um backup completo do banco de dados. Isso incluirá todos os dados de usuários, grupos, jogos e torneios.
        </p>
        <form method="POST">
            <button type="submit" name="criar_backup" class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Criar Backup
            </button>
        </form>
    </div>
</div>

<!-- Lista de Backups -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Backups Existentes
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($backups)): ?>
            <div class="text-center py-4">
                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                <p class="text-muted">Nenhum backup encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome do Arquivo</th>
                            <th>Tamanho</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-file-code me-2"></i>
                                    <?php echo htmlspecialchars($backup['nome']); ?>
                                </td>
                                <td><?php echo formatarTamanho($backup['tamanho']); ?></td>
                                <td><?php echo date('d/m/Y H:i:s', $backup['data']); ?></td>
                                <td>
                                    <a href="../backups/<?php echo $backup['nome']; ?>" 
                                       class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="confirmarExclusao('<?php echo $backup['nome']; ?>')">
                                        <i class="fas fa-trash me-1"></i>Excluir
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

<script>
function confirmarExclusao(nomeArquivo) {
    if (confirm('Tem certeza que deseja excluir o backup "' + nomeArquivo + '"?')) {
        window.location.href = 'excluir_backup.php?arquivo=' + encodeURIComponent(nomeArquivo);
    }
}
</script>

<?php
// Função para formatar tamanho do arquivo
function formatarTamanho($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

include '../includes/footer.php';
?>
