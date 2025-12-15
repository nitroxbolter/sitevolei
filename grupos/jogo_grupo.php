<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Jogo do Grupo';

$jogo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;

if ($jogo_id <= 0 || $grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Jogo ou grupo inválido.';
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
    $usuario_e_membro = $stmt ? ($stmt->fetch() !== false) : false;
}

if (!$usuario_e_membro && !$usuario_e_admin) {
    $_SESSION['mensagem'] = 'Você não tem permissão para acessar este jogo.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Buscar dados do jogo
$sql = "SELECT gj.*, u.nome AS criado_por_nome
        FROM grupo_jogos gj
        LEFT JOIN usuarios u ON u.id = gj.criado_por
        WHERE gj.id = ? AND gj.grupo_id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id, $grupo_id]);
$jogo = $stmt ? $stmt->fetch() : false;

if (!$jogo) {
    $_SESSION['mensagem'] = 'Jogo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: jogos_grupo.php?grupo_id=' . $grupo_id);
    exit();
}

// Buscar participantes
$sql = "SELECT gjp.*, u.id AS usuario_id, u.nome AS usuario_nome, u.foto_perfil
        FROM grupo_jogo_participantes gjp
        LEFT JOIN usuarios u ON u.id = gjp.usuario_id
        WHERE gjp.jogo_id = ?
        ORDER BY gjp.data_inscricao";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

// Buscar times
$sql = "SELECT * FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem, nome";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$times = $stmt ? $stmt->fetchAll() : [];

// Buscar integrantes dos times
foreach ($times as &$time) {
    $sql = "SELECT gjti.*, gjp.usuario_id, u.nome AS usuario_nome, u.foto_perfil
            FROM grupo_jogo_time_integrantes gjti
            JOIN grupo_jogo_participantes gjp ON gjp.id = gjti.participante_id
            LEFT JOIN usuarios u ON u.id = gjp.usuario_id
            WHERE gjti.time_id = ?
            ORDER BY u.nome";
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
}

// Buscar partidas
$sql = "SELECT gjp.*, 
               t1.nome AS time1_nome, t1.cor AS time1_cor,
               t2.nome AS time2_nome, t2.cor AS time2_cor
        FROM grupo_jogo_partidas gjp
        LEFT JOIN grupo_jogo_times t1 ON t1.id = gjp.time1_id
        LEFT JOIN grupo_jogo_times t2 ON t2.id = gjp.time2_id
        WHERE gjp.jogo_id = ?
        ORDER BY gjp.rodada, gjp.id";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$partidas = $stmt ? $stmt->fetchAll() : [];

// Buscar classificação
$sql = "SELECT gjc.*, gjt.nome AS time_nome, gjt.cor AS time_cor
        FROM grupo_jogo_classificacao gjc
        JOIN grupo_jogo_times gjt ON gjt.id = gjc.time_id
        WHERE gjc.jogo_id = ?
        ORDER BY gjc.pontos_total DESC, gjc.vitorias DESC, gjc.average DESC, gjc.saldo_pontos DESC";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$classificacao = $stmt ? $stmt->fetchAll() : [];

// Verificar se o usuário já está inscrito
$usuario_inscrito = false;
if (isLoggedIn()) {
    foreach ($participantes as $p) {
        if ((int)$p['usuario_id'] === (int)$_SESSION['user_id']) {
            $usuario_inscrito = true;
            break;
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0">
            <i class="fas fa-volleyball-ball me-2"></i><?php echo htmlspecialchars($jogo['nome']); ?>
        </h2>
        <div class="d-flex gap-2">
            <a href="jogos_grupo.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
</div>

<!-- Informações do Jogo -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Jogo</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-calendar me-2"></i>Data:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($jogo['data_jogo'])); ?>
                    </div>
                    <?php if ($jogo['local']): ?>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Local:</strong><br>
                        <?php echo htmlspecialchars($jogo['local']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($jogo['modalidade']): ?>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-users me-2"></i>Modalidade:</strong><br>
                        <?php echo htmlspecialchars($jogo['modalidade']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-tag me-2"></i>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $jogo['status'] === 'Finalizado' || $jogo['status'] === 'Arquivado' ? 'secondary' : 
                                ($jogo['status'] === 'Em Andamento' ? 'warning' : 
                                ($jogo['status'] === 'Lista Fechada' ? 'info' : 'success')); 
                        ?>">
                            <?php echo htmlspecialchars($jogo['status']); ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($jogo['descricao'])): ?>
                    <div class="mt-3">
                        <strong><i class="fas fa-align-left me-2"></i>Descrição:</strong>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($jogo['descricao'])); ?></p>
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
                                        $avatar = $p['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                        if (!empty($avatar)) {
                                            if (strpos($avatar, 'http') === 0 || strpos(trim($avatar), '/') === 0) {
                                                // Já é um caminho absoluto
                                            } elseif (strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                if (strpos($avatar, '../') !== 0) {
                                                    $avatar = '../' . ltrim($avatar, '/');
                                                }
                                            } else {
                                                $avatar = '../assets/arquivos/' . ltrim($avatar, '/');
                                            }
                                        } else {
                                            $avatar = '../assets/arquivos/logo.png';
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                             class="rounded-circle" width="32" height="32" 
                                             style="object-fit:cover;" alt="<?php echo htmlspecialchars($p['usuario_nome']); ?>">
                                        <span><?php echo htmlspecialchars($p['usuario_nome']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($jogo['lista_aberta'] == 1 && $jogo['status'] === 'Lista Aberta' && !$usuario_inscrito && isLoggedIn()): ?>
            <div class="card-footer">
                <button class="btn btn-primary w-100" onclick="entrarJogo(<?php echo $jogo_id; ?>)">
                    <i class="fas fa-sign-in-alt me-1"></i>Entrar no Jogo
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Times do Jogo -->
<?php if (!empty($times)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Jogo</h5>
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
                                                    $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    if (!empty($avatar)) {
                                                        if (strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                            if (strpos($avatar, '../') !== 0) {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            }
                                                        } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                            $avatar = '../assets/arquivos/' . $avatar;
                                                        }
                                                    } else {
                                                        $avatar = '../assets/arquivos/logo.png';
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                         class="rounded-circle" width="24" height="24" 
                                                         style="object-fit:cover;" alt="<?php echo htmlspecialchars($integ['usuario_nome']); ?>">
                                                    <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
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

<!-- Partidas -->
<?php if (!empty($partidas)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-futbol me-2"></i>Partidas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rodada_atual = 0;
                            foreach ($partidas as $partida): 
                                if (isset($partida['rodada']) && $partida['rodada'] != $rodada_atual):
                                    $rodada_atual = $partida['rodada'];
                            ?>
                                <tr class="table-secondary">
                                    <td colspan="4"><strong>Rodada <?php echo $rodada_atual; ?></strong></td>
                                </tr>
                            <?php endif; ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time1_nome'] ?? 'Time 1'); ?></strong>
                                            <?php if (!empty($partida['vencedor_id']) && $partida['vencedor_id'] == $partida['time1_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo (int)($partida['pontos_time1'] ?? 0); ?> x <?php echo (int)($partida['pontos_time2'] ?? 0); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time2_nome'] ?? 'Time 2'); ?></strong>
                                            <?php if (!empty($partida['vencedor_id']) && $partida['vencedor_id'] == $partida['time2_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo (!empty($partida['status']) && $partida['status'] === 'Finalizada') ? 'success' : ((!empty($partida['status']) && $partida['status'] === 'Em Andamento') ? 'warning' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars($partida['status'] ?? 'Agendada'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classificação Geral -->
<?php if (!empty($classificacao)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação Geral</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">Pos</th>
                                <th>Time</th>
                                <th class="text-center">Jogos</th>
                                <th class="text-center">V</th>
                                <th class="text-center">D</th>
                                <th class="text-center">PF</th>
                                <th class="text-center">PC</th>
                                <th class="text-center">Saldo</th>
                                <th class="text-center">Average</th>
                                <th class="text-center">Pontos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $posicao = 1;
                            foreach ($classificacao as $class): 
                            ?>
                                <tr>
                                    <td><strong><?php echo $posicao++; ?>º</strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($class['time_nome'] ?? 'Time'); ?></strong>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo (int)($class['vitorias'] ?? 0) + (int)($class['derrotas'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['vitorias'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['derrotas'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['pontos_pro'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['pontos_contra'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['saldo_pontos'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo number_format($class['average'] ?? 0, 2); ?></td>
                                    <td class="text-center"><strong><?php echo (int)($class['pontos_total'] ?? 0); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function entrarJogo(jogoId) {
    if (!confirm('Deseja entrar neste jogo?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/adicionar_participante_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'success');
                } else {
                    alert(response.message);
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'danger');
                } else {
                    alert(response.message);
                }
            }
        },
        error: function() {
            if (typeof showAlert === 'function') {
                showAlert('Erro ao processar solicitação', 'danger');
            } else {
                alert('Erro ao processar solicitação');
            }
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>

