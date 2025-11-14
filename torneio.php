<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Detalhes do Torneio';

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$torneio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($torneio_id <= 0) {
    $_SESSION['mensagem'] = 'Torneio inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: torneios.php');
    exit();
}

// Carregar torneio
$sql = "SELECT t.*, g.nome AS grupo_nome, g.local_principal AS grupo_local, u.nome AS criado_por_nome
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        LEFT JOIN usuarios u ON u.id = t.criado_por
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;

if (!$torneio) {
    $_SESSION['mensagem'] = 'Torneio não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: torneios.php');
    exit();
}

// Buscar participantes
$sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id
        WHERE tp.torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

// Buscar times
$sql = "SELECT * FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times = $stmt ? $stmt->fetchAll() : [];

// Obter quantidade de times configurada
$quantidade_times = (int)($torneio['quantidade_times'] ?? 0);

// Corrigir ordens duplicadas e agrupar por ordem (manter apenas um time por ordem)
if (count($times) > 0) {
    $timesPorOrdem = [];
    foreach ($times as $time) {
        $ordem = (int)$time['ordem'];
        // Se já existe um time com esta ordem, manter apenas o primeiro (mais antigo)
        if (!isset($timesPorOrdem[$ordem])) {
            $timesPorOrdem[$ordem] = $time;
        }
    }
    // Reordenar por ordem
    ksort($timesPorOrdem);
    
    // Filtrar apenas os times dentro da quantidade configurada
    if ($quantidade_times > 0) {
        $timesFiltrados = [];
        for ($i = 1; $i <= $quantidade_times; $i++) {
            if (isset($timesPorOrdem[$i])) {
                $timesFiltrados[] = $timesPorOrdem[$i];
            }
        }
        $times = $timesFiltrados;
    } else {
        $times = array_values($timesPorOrdem);
    }
}

// Buscar integrantes dos times (ordenados por ordem de inserção)
foreach ($times as &$time) {
    $sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
            FROM torneio_time_integrantes tti
            JOIN torneio_participantes tp ON tp.id = tti.participante_id
            LEFT JOIN usuarios u ON u.id = tp.usuario_id
            WHERE tti.time_id = ?
            ORDER BY tti.id ASC";
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
}
unset($time); // Importante: remover referência após o loop

include 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i><?php echo htmlspecialchars($torneio['nome']); ?>
        </h2>
        <div>
            <a href="torneios.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <?php
            $sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
            $sou_admin_grupo = false;
            if ($torneio['grupo_id']) {
                $sql = "SELECT administrador_id FROM grupos WHERE id = ?";
                $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
                $grupo = $stmt ? $stmt->fetch() : false;
                $sou_admin_grupo = $grupo && ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
            }
            if ($sou_criador || $sou_admin_grupo || isAdmin($pdo, $_SESSION['user_id'])):
            ?>
                <a href="admin/gerenciar_torneio.php?id=<?php echo $torneio_id; ?>" class="btn btn-primary">
                    <i class="fas fa-cog me-1"></i>Gerenciar
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Informações do Torneio -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Torneio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-calendar me-2"></i>Data:</strong><br>
                        <?php 
                        $dataTorneio = $torneio['data_inicio'] ?? '';
                        echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'Não definida';
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-users me-2"></i>Quantidade de Jogadores:</strong><br>
                        <?php 
                        $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
                        echo (int)count($participantes);
                        if ($maxParticipantes > 0) {
                            echo ' / ' . (int)$maxParticipantes;
                        }
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Local:</strong><br>
                        <?php 
                        // Tentar obter local do grupo primeiro, depois do torneio
                        $local = 'Não definido';
                        if (!empty($torneio['grupo_local'])) {
                            $local = $torneio['grupo_local'];
                        } elseif (!empty($torneio['local'])) {
                            $local = $torneio['local'];
                        }
                        echo htmlspecialchars($local);
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-tag me-2"></i>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 
                                ($torneio['status'] === 'Em Andamento' ? 'warning' : 
                                ($torneio['status'] === 'Criado' ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo htmlspecialchars($torneio['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-users me-2"></i>Grupo:</strong><br>
                        <?php echo htmlspecialchars($torneio['grupo_nome'] ?: 'Torneio Avulso'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-user me-2"></i>Criado por:</strong><br>
                        <?php echo htmlspecialchars($torneio['criado_por_nome']); ?>
                    </div>
                </div>
                <?php if (!empty($torneio['descricao'])): ?>
                    <div class="mt-3">
                        <strong><i class="fas fa-align-left me-2"></i>Descrição:</strong>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($torneio['descricao'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Participantes</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($participantes)): ?>
                    <p class="text-muted mb-0">Nenhum participante inscrito ainda.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($participantes as $p): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($p['usuario_id']): ?>
                                        <?php
                                        $avatar = $p['foto_perfil'] ?: 'assets/arquivos/logo.png';
                                        if (strpos($avatar, 'assets/') === 0) {
                                            // Já está correto
                                        } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                            $avatar = 'assets/arquivos/' . $avatar;
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                             class="rounded-circle" width="32" height="32" 
                                             style="object-fit:cover;" alt="Avatar">
                                        <span><?php echo htmlspecialchars($p['usuario_nome']); ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($p['nome_avulso']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Times do Torneio -->
<?php if (!empty($times)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header" style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($time['integrantes'])): ?>
                                        <p class="text-muted mb-0"><small>Nenhum integrante</small></p>
                                    <?php else: ?>
                                        <?php foreach ($time['integrantes'] as $integ): ?>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <?php if ($integ['usuario_id']): ?>
                                                    <?php
                                                    $avatar = $integ['foto_perfil'] ?: 'assets/arquivos/logo.png';
                                                    if (strpos($avatar, 'assets/') === 0) {
                                                        // Já está correto
                                                    } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                        $avatar = 'assets/arquivos/' . $avatar;
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                         class="rounded-circle" width="24" height="24" 
                                                         style="object-fit:cover;" alt="Avatar">
                                                    <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                <?php else: ?>
                                                    <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

