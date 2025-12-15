<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Jogos do Grupo';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Verificar se o grupo existe e se o usuário é membro
$sql = "SELECT g.*, u.nome AS admin_nome
        FROM grupos g
        LEFT JOIN usuarios u ON u.id = g.administrador_id
        WHERE g.id = ? AND g.ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Verificar se o usuário logado é membro do grupo
$usuario_e_membro = false;
$usuario_e_admin = false;
if (isLoggedIn()) {
    $usuario_id = (int)$_SESSION['user_id'];
    $usuario_e_admin = ((int)$grupo['administrador_id'] === $usuario_id);
    
    $sql = "SELECT id FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
    if ($stmt) {
        $result = $stmt->fetch();
        $usuario_e_membro = $result !== false;
        $stmt->closeCursor(); // Finalizar a query
    } else {
        $usuario_e_membro = false;
    }
}

if (!$usuario_e_membro && !$usuario_e_admin) {
    $_SESSION['mensagem'] = 'Você não tem permissão para acessar este grupo.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Verificar se as tabelas existem, se não, criar
try {
    $stmt_check = executeQuery($pdo, "SELECT 1 FROM grupo_jogos LIMIT 1", []);
    if ($stmt_check) {
        $stmt_check->fetchAll(); // Finalizar a query
    }
} catch (Exception $e) {
    // Tabelas não existem, criar
    $sql_file = dirname(__DIR__) . '/../sql/grupo_jogos_tables.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        // Executar cada comando separadamente para evitar erros
        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
        foreach ($statements as $statement) {
            if (!empty($statement) && stripos($statement, 'CREATE TABLE') !== false) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $ex) {
                    // Ignorar erros individuais (tabela pode já existir)
                }
            }
        }
    }
}

// Buscar jogos do grupo
$sql = "SELECT gj.*, u.nome AS criado_por_nome,
               COUNT(DISTINCT gjp.id) AS total_participantes,
               COUNT(DISTINCT gjt.id) AS total_times
        FROM grupo_jogos gj
        LEFT JOIN usuarios u ON u.id = gj.criado_por
        LEFT JOIN grupo_jogo_participantes gjp ON gjp.jogo_id = gj.id
        LEFT JOIN grupo_jogo_times gjt ON gjt.jogo_id = gj.id
        WHERE gj.grupo_id = ?
        GROUP BY gj.id
        ORDER BY gj.data_criacao DESC";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$jogos = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0">
            <i class="fas fa-volleyball-ball me-2"></i>Jogos do Grupo: <?php echo htmlspecialchars($grupo['nome']); ?>
        </h2>
        <div class="d-flex gap-2">
            <?php if ($usuario_e_admin): ?>
                <a href="admin/jogo_grupo.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Novo Jogo
                </a>
            <?php endif; ?>
            <a href="grupo.php?id=<?php echo $grupo_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if (empty($jogos)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-volleyball-ball fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Nenhum jogo criado ainda.</p>
                    <?php if ($usuario_e_admin): ?>
                        <a href="admin/jogo_grupo.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Criar Primeiro Jogo
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($jogos as $jogo): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong><?php echo htmlspecialchars($jogo['nome']); ?></strong>
                                <span class="badge bg-<?php 
                                    echo $jogo['status'] === 'Finalizado' || $jogo['status'] === 'Arquivado' ? 'secondary' : 
                                        ($jogo['status'] === 'Em Andamento' ? 'warning' : 
                                        ($jogo['status'] === 'Lista Fechada' ? 'info' : 'success')); 
                                ?>">
                                    <?php echo htmlspecialchars($jogo['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($jogo['data_jogo'])); ?>
                                </p>
                                <?php if ($jogo['local']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <strong>Local:</strong> <?php echo htmlspecialchars($jogo['local']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($jogo['modalidade']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-users me-2"></i>
                                        <strong>Modalidade:</strong> <?php echo htmlspecialchars($jogo['modalidade']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-2">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>Participantes:</strong> <?php echo (int)$jogo['total_participantes']; ?>
                                </p>
                                <?php if ($jogo['total_times'] > 0): ?>
                                    <p class="mb-0">
                                        <i class="fas fa-users-cog me-2"></i>
                                        <strong>Times:</strong> <?php echo (int)$jogo['total_times']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex gap-2">
                                    <a href="jogo_grupo.php?id=<?php echo $jogo['id']; ?>&grupo_id=<?php echo $grupo_id; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </a>
                                    <?php if ($usuario_e_admin && $jogo['status'] !== 'Arquivado'): ?>
                                        <a href="admin/jogo_grupo.php?id=<?php echo $jogo['id']; ?>&grupo_id=<?php echo $grupo_id; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit me-1"></i>Gerenciar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

