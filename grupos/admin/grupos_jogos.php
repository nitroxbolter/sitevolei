<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Grupos e Jogos';
requireAdmin($pdo);

// Processar ações
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'remover_grupo':
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            if ($grupo_id) {
                try {
                    $pdo->beginTransaction();

                    // Remover logo do grupo (se houver)
                    $stmtLogo = executeQuery($pdo, "SELECT logo_id FROM grupos WHERE id = ?", [$grupo_id]);
                    $logoRow = $stmtLogo ? $stmtLogo->fetch() : null;
                    $logoId = (int)($logoRow['logo_id'] ?? 0);

                    // Jogos e dependências
                    $stmtJogos = executeQuery($pdo, "SELECT id FROM jogos WHERE grupo_id = ?", [$grupo_id]);
                    $jogosIds = $stmtJogos ? $stmtJogos->fetchAll(PDO::FETCH_COLUMN) : [];
                    if (!empty($jogosIds)) {
                        $inJogos = implode(',', array_map('intval', $jogosIds));
                        $stmtTimes = $pdo->query("SELECT id FROM times WHERE jogo_id IN (".$inJogos.")");
                        $timesIds = $stmtTimes ? $stmtTimes->fetchAll(PDO::FETCH_COLUMN) : [];
                        if (!empty($timesIds)) {
                            $inTimes = implode(',', array_map('intval', $timesIds));
                            $pdo->exec("DELETE FROM partidas WHERE time1_id IN (".$inTimes.") OR time2_id IN (".$inTimes.")");
                            $pdo->exec("DELETE FROM time_jogadores WHERE time_id IN (".$inTimes.")");
                            $pdo->exec("DELETE FROM times WHERE id IN (".$inTimes.")");
                        }
                        $pdo->exec("DELETE FROM confirmacoes_presenca WHERE jogo_id IN (".$inJogos.")");
                        $pdo->exec("DELETE FROM partidas WHERE jogo_id IN (".$inJogos.")");
                        $pdo->exec("DELETE FROM jogos WHERE id IN (".$inJogos.")");
                    }

                    // Torneios e dependências
                    $stmtTorneios = executeQuery($pdo, "SELECT id FROM torneios WHERE grupo_id = ?", [$grupo_id]);
                    $torneiosIds = $stmtTorneios ? $stmtTorneios->fetchAll(PDO::FETCH_COLUMN) : [];
                    if (!empty($torneiosIds)) {
                        $inTorneios = implode(',', array_map('intval', $torneiosIds));
                        $pdo->exec("DELETE FROM torneio_chaves WHERE torneio_id IN (".$inTorneios.")");
                        $pdo->exec("DELETE FROM torneio_participantes WHERE torneio_id IN (".$inTorneios.")");
                        $pdo->exec("DELETE FROM torneios WHERE id IN (".$inTorneios.")");
                    }

                    // Avisos e membros
                    executeQuery($pdo, "DELETE FROM avisos WHERE grupo_id = ?", [$grupo_id]);
                    executeQuery($pdo, "DELETE FROM grupo_membros WHERE grupo_id = ?", [$grupo_id]);

                    // Remover grupo
                    executeQuery($pdo, "DELETE FROM grupos WHERE id = ?", [$grupo_id]);

                    // Remover arquivo e registro de logo após apagar o grupo
                    if ($logoId > 0) {
                        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'logosgrupos' . DIRECTORY_SEPARATOR . $logoId . '.png';
                        if (file_exists($file)) { @unlink($file); }
                        executeQuery($pdo, "DELETE FROM logos_grupos WHERE id = ?", [$logoId]);
                    }

                    $pdo->commit();
                    $_SESSION['mensagem'] = 'Grupo removido definitivamente!';
                    $_SESSION['tipo_mensagem'] = 'success';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    $_SESSION['mensagem'] = 'Erro ao remover grupo: ' . $e->getMessage();
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
        case 'inativar_grupo':
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            if ($grupo_id) {
                if (executeQuery($pdo, "UPDATE grupos SET ativo = 0 WHERE id = ?", [$grupo_id])) {
                    $_SESSION['mensagem'] = 'Grupo marcado como inativo.';
                    $_SESSION['tipo_mensagem'] = 'success';
                } else {
                    $_SESSION['mensagem'] = 'Erro ao inativar grupo.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
        case 'reativar_grupo':
            $grupo_id = (int)($_POST['grupo_id'] ?? 0);
            if ($grupo_id) {
                if (executeQuery($pdo, "UPDATE grupos SET ativo = 1 WHERE id = ?", [$grupo_id])) {
                    $_SESSION['mensagem'] = 'Grupo reativado com sucesso.';
                    $_SESSION['tipo_mensagem'] = 'success';
                } else {
                    $_SESSION['mensagem'] = 'Erro ao reativar grupo.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
            
        case 'remover_jogo':
            $jogo_id = (int)($_POST['jogo_id'] ?? 0);
            if ($jogo_id) {
                $sql = "UPDATE jogos SET status = 'Fechado' WHERE id = ?";
                if (executeQuery($pdo, $sql, [$jogo_id])) {
                    $_SESSION['mensagem'] = 'Jogo removido com sucesso!';
                    $_SESSION['tipo_mensagem'] = 'success';
                } else {
                    $_SESSION['mensagem'] = 'Erro ao remover jogo.';
                    $_SESSION['tipo_mensagem'] = 'danger';
                }
            }
            break;
    }
    
    header('Location: grupos_jogos.php');
    exit();
}

// Obter grupos (ativos e inativos)
$sql = "SELECT g.*, u.nome as admin_nome, COUNT(gm.id) as total_membros
        FROM grupos g
        LEFT JOIN usuarios u ON g.administrador_id = u.id
        LEFT JOIN grupo_membros gm ON g.id = gm.grupo_id AND gm.ativo = 1
        GROUP BY g.id
        ORDER BY g.ativo DESC, g.data_criacao DESC";
$stmt = executeQuery($pdo, $sql);
$grupos = $stmt ? $stmt->fetchAll() : [];

// Obter jogos
$sql = "SELECT j.*, g.nome as grupo_nome, u.nome as criado_por_nome,
               COUNT(cp.id) as total_confirmacoes
        FROM jogos j
        LEFT JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id
        WHERE j.status != 'Fechado'
        GROUP BY j.id
        ORDER BY j.data_jogo DESC";
$stmt = executeQuery($pdo, $sql);
$jogos = $stmt ? $stmt->fetchAll() : [];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-cogs me-2"></i>Gerenciar Grupos e Jogos
            </h2>
        </div>
    </div>
</div>

<!-- Grupos -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>Grupos
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($grupos)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <p class="text-muted">Nenhum grupo encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Administrador</th>
                            <th>Local</th>
                            <th>Membros</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupos as $grupo): ?>
                            <tr class="<?php echo $grupo['ativo'] == 0 ? 'table-secondary' : ''; ?>">
                                <td><?php echo $grupo['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($grupo['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($grupo['admin_nome']); ?></td>
                                <td><?php echo htmlspecialchars($grupo['local_principal']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $grupo['total_membros']; ?> membros</span>
                                </td>
                                <td>
                                    <?php if ($grupo['ativo'] == 1): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatarData($grupo['data_criacao'], 'd/m/Y'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($grupo['ativo'] == 1): ?>
                                            <button class="btn btn-sm btn-warning" onclick="confirmarInativacaoGrupo(<?php echo $grupo['id']; ?>, '<?php echo htmlspecialchars($grupo['nome']); ?>')">
                                                <i class="fas fa-ban"></i> Inativar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success" onclick="confirmarReativacaoGrupo(<?php echo $grupo['id']; ?>, '<?php echo htmlspecialchars($grupo['nome']); ?>')">
                                                <i class="fas fa-check"></i> Reativar
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-danger" onclick="confirmarRemocaoGrupo(<?php echo $grupo['id']; ?>, '<?php echo htmlspecialchars($grupo['nome']); ?>')">
                                            <i class="fas fa-trash"></i> Remover
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Jogos -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-calendar-alt me-2"></i>Jogos
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($jogos)): ?>
            <div class="text-center py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <p class="text-muted">Nenhum jogo encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Grupo</th>
                            <th>Data</th>
                            <th>Local</th>
                            <th>Status</th>
                            <th>Confirmados</th>
                            <th>Criado por</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogos as $jogo): ?>
                            <tr>
                                <td><?php echo $jogo['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($jogo['titulo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($jogo['grupo_nome']); ?></td>
                                <td><?php echo formatarData($jogo['data_jogo']); ?></td>
                                <td><?php echo htmlspecialchars($jogo['local']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $jogo['status'] === 'Aberto' ? 'success' : ($jogo['status'] === 'Fechado' ? 'danger' : 'info'); ?>">
                                        <?php echo $jogo['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $jogo['total_confirmacoes']; ?> confirmados</span>
                                </td>
                                <td><?php echo htmlspecialchars($jogo['criado_por_nome']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="confirmarRemocaoJogo(<?php echo $jogo['id']; ?>, '<?php echo htmlspecialchars($jogo['titulo']); ?>')">
                                        <i class="fas fa-trash"></i> Remover
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

<!-- Modal de Confirmação de Remoção de Grupo -->
<div class="modal fade" id="confirmarRemocaoGrupoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Remoção de Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja remover o grupo <strong id="nomeGrupo"></strong>?</p>
                <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="remover_grupo">
                    <input type="hidden" name="grupo_id" id="grupoIdRemover">
                    <button type="submit" class="btn btn-danger">Remover Grupo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Inativação de Grupo -->
<div class="modal fade" id="confirmarInativacaoGrupoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Inativação de Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Marcar o grupo <strong id="nomeGrupoInativar"></strong> como inativo?</p>
                <p class="text-muted"><small>Os dados permanecem na base, apenas deixam de aparecer para usuários.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="inativar_grupo">
                    <input type="hidden" name="grupo_id" id="grupoIdInativar">
                    <button type="submit" class="btn btn-warning">Inativar Grupo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Reativação de Grupo -->
<div class="modal fade" id="confirmarReativacaoGrupoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Reativação de Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reativar o grupo <strong id="nomeGrupoReativar"></strong>?</p>
                <p class="text-muted"><small>O grupo voltará a aparecer para todos os usuários.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="reativar_grupo">
                    <input type="hidden" name="grupo_id" id="grupoIdReativar">
                    <button type="submit" class="btn btn-success">Reativar Grupo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Remoção de Jogo -->
<div class="modal fade" id="confirmarRemocaoJogoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Remoção de Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja remover o jogo <strong id="nomeJogo"></strong>?</p>
                <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="remover_jogo">
                    <input type="hidden" name="jogo_id" id="jogoIdRemover">
                    <button type="submit" class="btn btn-danger">Remover Jogo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarRemocaoGrupo(grupoId, nomeGrupo) {
    document.getElementById('grupoIdRemover').value = grupoId;
    document.getElementById('nomeGrupo').textContent = nomeGrupo;
    var modal = new bootstrap.Modal(document.getElementById('confirmarRemocaoGrupoModal'));
    modal.show();
}

function confirmarInativacaoGrupo(grupoId, nomeGrupo) {
    document.getElementById('grupoIdInativar').value = grupoId;
    document.getElementById('nomeGrupoInativar').textContent = nomeGrupo;
    var modal = new bootstrap.Modal(document.getElementById('confirmarInativacaoGrupoModal'));
    modal.show();
}

function confirmarReativacaoGrupo(grupoId, nomeGrupo) {
    document.getElementById('grupoIdReativar').value = grupoId;
    document.getElementById('nomeGrupoReativar').textContent = nomeGrupo;
    var modal = new bootstrap.Modal(document.getElementById('confirmarReativacaoGrupoModal'));
    modal.show();
}

function confirmarRemocaoJogo(jogoId, nomeJogo) {
    document.getElementById('jogoIdRemover').value = jogoId;
    document.getElementById('nomeJogo').textContent = nomeJogo;
    var modal = new bootstrap.Modal(document.getElementById('confirmarRemocaoJogoModal'));
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
