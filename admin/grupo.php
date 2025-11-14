<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Gerenciar Grupo';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$grupo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

// Carregar grupo e checar permissão: admin do grupo OU admin do site
$sql = "SELECT g.*, u.nome AS admin_nome FROM grupos g LEFT JOIN usuarios u ON u.id=g.administrador_id WHERE g.id=?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

$sou_admin_grupo = ($grupo['administrador_id'] ?? 0) == ($_SESSION['user_id'] ?? 0);
if (!$sou_admin_grupo && !isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['mensagem'] = 'Acesso negado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

// Processar ações
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'atualizar_grupo') {
        $nome = trim($_POST['nome'] ?? '');
        $local = trim($_POST['local_principal'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $modalidade = trim($_POST['modalidade'] ?? '');
        $logoCropped = $_POST['logo_cropped'] ?? '';

        if ($nome === '' || $local === '') {
            $_SESSION['mensagem'] = 'Nome e Local são obrigatórios.';
            $_SESSION['tipo_mensagem'] = 'danger';
        } else {
            // Atualizar com modalidade se a coluna existir
            $hasModalidade = false;
            $stmtCols = executeQuery($pdo, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos' AND COLUMN_NAME = 'modalidade'");
            if ($stmtCols && $stmtCols->fetch()) { $hasModalidade = true; }

            if ($hasModalidade) {
                $sql = "UPDATE grupos SET nome = ?, local_principal = ?, descricao = ?, modalidade = ? WHERE id = ?";
                $ok = executeQuery($pdo, $sql, [$nome, $local, $descricao, ($modalidade ?: null), $grupo_id]);
            } else {
                $sql = "UPDATE grupos SET nome = ?, local_principal = ?, descricao = ? WHERE id = ?";
                $ok = executeQuery($pdo, $sql, [$nome, $local, $descricao, $grupo_id]);
            }

            // Processar nova logo (opcional)
            if ($ok && !empty($logoCropped) && preg_match('/^data:image\/(png|jpeg);base64,/', $logoCropped)) {
                $dadosBase64 = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $logoCropped);
                $dadosBase64 = str_replace(' ', '+', $dadosBase64);
                $binario = base64_decode($dadosBase64);
                if ($binario !== false) {
                    // Criar registro de logo
                    $stmtLogo = executeQuery($pdo, "INSERT INTO logos_grupos (caminho) VALUES ('')", []);
                    if ($stmtLogo) {
                        $novoLogoId = (int)$pdo->lastInsertId();
                        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'logosgrupos';
                        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                        $arquivo = $dir . DIRECTORY_SEPARATOR . $novoLogoId . '.png';
                        // Redimensionar se GD disponível; caso não, salvar como veio
                        if (function_exists('imagecreatefromstring')) {
                            $src = @imagecreatefromstring($binario);
                            if ($src) {
                                $dst = imagecreatetruecolor(128, 128);
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                                $width = imagesx($src);
                                $height = imagesy($src);
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, 128, 128, $width, $height);
                                $saved = imagepng($dst, $arquivo);
                                imagedestroy($dst);
                                imagedestroy($src);
                            } else {
                                $saved = (file_put_contents($arquivo, $binario) !== false);
                            }
                        } else {
                            $saved = (file_put_contents($arquivo, $binario) !== false);
                        }
                        if ($saved) {
                            $relPath = 'assets/arquivos/logosgrupos/' . $novoLogoId . '.png';
                            executeQuery($pdo, "UPDATE logos_grupos SET caminho = ? WHERE id = ?", [$relPath, $novoLogoId]);
                            // Buscar logo antiga para remover
                            $stmtOld = executeQuery($pdo, "SELECT logo_id FROM grupos WHERE id = ?", [$grupo_id]);
                            $old = $stmtOld ? $stmtOld->fetch() : null;
                            $oldId = (int)($old['logo_id'] ?? 0);
                            // Atualizar para nova logo
                            executeQuery($pdo, "UPDATE grupos SET logo_id = ? WHERE id = ?", [$novoLogoId, $grupo_id]);
                            // Remover logo antiga (arquivo + registro)
                            if ($oldId > 0) {
                                $oldFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relPath; // placeholder base
                                $oldFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'logosgrupos' . DIRECTORY_SEPARATOR . $oldId . '.png';
                                if (file_exists($oldFile)) { @unlink($oldFile); }
                                executeQuery($pdo, "DELETE FROM logos_grupos WHERE id = ?", [$oldId]);
                            }
                        } else {
                            executeQuery($pdo, "DELETE FROM logos_grupos WHERE id = ?", [$novoLogoId]);
                        }
                    }
                }
            }

            if ($ok) {
                $_SESSION['mensagem'] = 'Grupo atualizado com sucesso.';
                $_SESSION['tipo_mensagem'] = 'success';
                header('Location: grupo.php?id='.(int)$grupo_id);
                exit();
            } else {
                $_SESSION['mensagem'] = 'Erro ao atualizar grupo.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        }
    }
    if ($acao === 'excluir_grupo') {
        // Hard delete: remover dependências e o grupo definitivamente
        try {
            $pdo->beginTransaction();

            // Remover logo do grupo (se houver)
            $stmtLogo = executeQuery($pdo, "SELECT logo_id FROM grupos WHERE id = ?", [$grupo_id]);
            $logoRow = $stmtLogo ? $stmtLogo->fetch() : null;
            $logoId = (int)($logoRow['logo_id'] ?? 0);

            // Coletar jogos do grupo
            $stmtJogos = executeQuery($pdo, "SELECT id FROM jogos WHERE grupo_id = ?", [$grupo_id]);
            $jogosIds = $stmtJogos ? $stmtJogos->fetchAll(PDO::FETCH_COLUMN) : [];

            if (!empty($jogosIds)) {
                $inJogos = implode(',', array_map('intval', $jogosIds));

                // Coletar times dos jogos
                $stmtTimes = $pdo->query("SELECT id FROM times WHERE jogo_id IN (".$inJogos.")");
                $timesIds = $stmtTimes ? $stmtTimes->fetchAll(PDO::FETCH_COLUMN) : [];
                if (!empty($timesIds)) {
                    $inTimes = implode(',', array_map('intval', $timesIds));
                    // Remover partidas que referenciam os jogos/times
                    $pdo->exec("DELETE FROM partidas WHERE time1_id IN (".$inTimes.") OR time2_id IN (".$inTimes.")");
                }

                // Remover confirmações de presença dos jogos
                $pdo->exec("DELETE FROM confirmacoes_presenca WHERE jogo_id IN (".$inJogos.")");

                // Remover times e jogadores dos times
                if (!empty($timesIds)) {
                    $inTimes = implode(',', array_map('intval', $timesIds));
                    $pdo->exec("DELETE FROM time_jogadores WHERE time_id IN (".$inTimes.")");
                    $pdo->exec("DELETE FROM times WHERE id IN (".$inTimes.")");
                }

                // Remover partidas restantes vinculadas ao jogo
                $pdo->exec("DELETE FROM partidas WHERE jogo_id IN (".$inJogos.")");

                // Remover os jogos
                $pdo->exec("DELETE FROM jogos WHERE id IN (".$inJogos.")");
            }

            // Coletar torneios do grupo
            $stmtTorneios = executeQuery($pdo, "SELECT id FROM torneios WHERE grupo_id = ?", [$grupo_id]);
            $torneiosIds = $stmtTorneios ? $stmtTorneios->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!empty($torneiosIds)) {
                $inTorneios = implode(',', array_map('intval', $torneiosIds));
                $pdo->exec("DELETE FROM torneio_chaves WHERE torneio_id IN (".$inTorneios.")");
                $pdo->exec("DELETE FROM torneio_participantes WHERE torneio_id IN (".$inTorneios.")");
                $pdo->exec("DELETE FROM torneios WHERE id IN (".$inTorneios.")");
            }

            // Remover membros do grupo
            executeQuery($pdo, "DELETE FROM grupo_membros WHERE grupo_id = ?", [$grupo_id]);

            // Remover avisos do grupo
            executeQuery($pdo, "DELETE FROM avisos WHERE grupo_id = ?", [$grupo_id]);

            // Remover o grupo
            executeQuery($pdo, "DELETE FROM grupos WHERE id = ?", [$grupo_id]);

            // Remover arquivo e registro de logo após apagar o grupo
            if ($logoId > 0) {
                $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'logosgrupos' . DIRECTORY_SEPARATOR . $logoId . '.png';
                if (file_exists($file)) { @unlink($file); }
                executeQuery($pdo, "DELETE FROM logos_grupos WHERE id = ?", [$logoId]);
            }

            $pdo->commit();

            $_SESSION['mensagem'] = 'Grupo removido definitivamente.';
            $_SESSION['tipo_mensagem'] = 'success';
            header('Location: ../grupos.php');
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['mensagem'] = 'Erro ao remover grupo: ' . $e->getMessage();
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    }
    if ($acao === 'inativar_grupo') {
        if (executeQuery($pdo, "UPDATE grupos SET ativo = 0 WHERE id = ?", [$grupo_id])) {
            $_SESSION['mensagem'] = 'Grupo marcado como inativo.';
            $_SESSION['tipo_mensagem'] = 'success';
            header('Location: ../grupos.php');
            exit();
        } else {
            $_SESSION['mensagem'] = 'Erro ao inativar grupo.';
            $_SESSION['tipo_mensagem'] = 'danger';
        }
    }
    if ($acao === 'remover_membro') {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        if ($usuario_id) {
            $sql = "DELETE FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ?";
            if (executeQuery($pdo, $sql, [$grupo_id, $usuario_id])) {
                $_SESSION['mensagem'] = 'Membro removido.';
                $_SESSION['tipo_mensagem'] = 'success';
            } else {
                $_SESSION['mensagem'] = 'Erro ao remover membro.';
                $_SESSION['tipo_mensagem'] = 'danger';
            }
        }
        header('Location: grupo.php?id='.(int)$grupo_id);
        exit();
    }
}

// Listar membros (ativos) e solicitações (pendentes)
$sql = "SELECT u.id, u.nome, u.email, u.telefone, u.nivel, COALESCE(u.reputacao,0) AS reputacao, u.foto_perfil, gm.data_entrada
        FROM grupo_membros gm JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1 ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$membros = $stmt ? $stmt->fetchAll() : [];

$membrosExibir = $membros;
if ($sou_admin_grupo) {
    $adminIdGrupo = (int)($grupo['administrador_id'] ?? 0);
    $membrosExibir = array_values(array_filter($membros, function($m) use ($adminIdGrupo) {
        return (int)($m['id'] ?? 0) !== $adminIdGrupo;
    }));
}
// Garantir ordenação alfabética
usort($membrosExibir, function($a, $b) {
    return strcasecmp($a['nome'], $b['nome']);
});
$totalMembrosExibir = count($membrosExibir);

$sql = "SELECT u.id, u.nome, u.email, u.telefone, u.nivel
        FROM grupo_membros gm JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 0 ORDER BY u.data_cadastro DESC";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$solicitacoes = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-users-cog me-2"></i>Gerenciar Grupo: <?php echo htmlspecialchars($grupo['nome']); ?>
        </h2>
        <div class="btn-group">
            <a href="sistema_pontuacao.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-info">
                <i class="fas fa-trophy me-1"></i>Sistema de Pontuação
            </a>
            <a href="../grupos.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
            <form method="POST" onsubmit="return confirm('Marcar este grupo como inativo?');">
                <input type="hidden" name="acao" value="inativar_grupo">
                <button type="submit" class="btn btn-warning"><i class="fas fa-ban me-1"></i>Inativar</button>
            </form>
            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este grupo?');">
                <input type="hidden" name="acao" value="excluir_grupo">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Excluir Grupo</button>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Informações do Grupo</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formEditarGrupo">
                    <input type="hidden" name="acao" value="atualizar_grupo">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome" class="form-label">Nome do Grupo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($grupo['nome']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="local_principal" class="form-label">Local Principal *</label>
                            <input type="text" class="form-control" id="local_principal" name="local_principal" value="<?php echo htmlspecialchars($grupo['local_principal']); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modalidade" class="form-label">Modalidade</label>
                            <select class="form-select" id="modalidade" name="modalidade">
                                <?php $modAtual = $grupo['modalidade'] ?? ''; ?>
                                <option value="" <?php echo $modAtual === '' ? 'selected' : ''; ?>>Selecione</option>
                                <option value="Vôlei" <?php echo $modAtual === 'Vôlei' ? 'selected' : ''; ?>>Vôlei</option>
                                <option value="Vôlei Quadra" <?php echo $modAtual === 'Vôlei Quadra' ? 'selected' : ''; ?>>Vôlei Quadra</option>
                                <option value="Vôlei Areia" <?php echo $modAtual === 'Vôlei Areia' ? 'selected' : ''; ?>>Vôlei Areia</option>
                                <option value="Beach Tênis" <?php echo $modAtual === 'Beach Tênis' ? 'selected' : ''; ?>>Beach Tênis</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" placeholder="Regras, horários, observações..."><?php echo htmlspecialchars($grupo['descricao'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Logo do Grupo (opcional)</label>
                        <input type="file" id="logo_input" accept="image/*" class="form-control">
                        <small class="text-muted d-block mt-1">Tamanho exigido: 128 x 128 pixels. O recorte será aplicado nesta área fixa.</small>
                        <input type="hidden" name="logo_cropped" id="logo_cropped">
                        <div class="mt-2" style="width:128px; height:128px; overflow:hidden; border:1px dashed #ccc; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <img id="logo_preview" style="width:128px; height:128px; display:none; object-fit:cover;" alt="Prévia da logo">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar Alterações</button>
                        <a href="grupo.php?id=<?php echo (int)$grupo_id; ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Membros (<?php echo (int)$totalMembrosExibir; ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($membrosExibir)): ?>
                    <div class="text-center text-muted">Nenhum membro.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Contato</th>
                                    <th>Nível</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($membrosExibir as $m): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php 
                                                // Corrigir caminho da foto de perfil
                                                if (!empty($m['foto_perfil'])) {
                                                    // Se já começa com http ou /, usar como está
                                                    if (strpos($m['foto_perfil'], 'http') === 0 || strpos($m['foto_perfil'], '/') === 0) {
                                                        $avatar = $m['foto_perfil'];
                                                    } elseif (strpos($m['foto_perfil'], 'assets/') === 0) {
                                                        // Se já tem assets/, adicionar ../
                                                        $avatar = '../' . $m['foto_perfil'];
                                                    } else {
                                                        // Se não tem caminho, adicionar caminho completo
                                                        $avatar = '../assets/arquivos/' . $m['foto_perfil'];
                                                    }
                                                } else {
                                                    $avatar = '../assets/arquivos/logo.png';
                                                }
                                                $rep = (int)($m['reputacao'] ?? 0);
                                                $repBadgeClass = 'bg-danger';
                                                $repStyle = '';
                                                if ($rep > 75) {
                                                    $repBadgeClass = 'bg-success';
                                                } elseif ($rep > 50) {
                                                    $repBadgeClass = 'bg-warning text-dark';
                                                } elseif ($rep > 25) {
                                                    $repBadgeClass = 'text-dark';
                                                    $repStyle = 'background-color:#fd7e14;';
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="28" height="28" style="object-fit:cover;" alt="Avatar">
                                                <strong><?php echo htmlspecialchars($m['nome']); ?></strong>
                                                <span class="badge ms-1 <?php echo $repBadgeClass; ?>" style="<?php echo $repStyle; ?>"><?php echo (int)$m['reputacao']; ?> pts</span>
                                            </div>
                                        </td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($m['email']); ?><?php echo $m['telefone'] ? ' · '.htmlspecialchars($m['telefone']) : ''; ?></small></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($m['nivel']); ?></span></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Remover este membro?');" style="display:inline-block;">
                                                <input type="hidden" name="acao" value="remover_membro">
                                                <input type="hidden" name="usuario_id" value="<?php echo (int)$m['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-user-times"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Solicitações Pendentes (<?php echo count($solicitacoes); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($solicitacoes)): ?>
                    <div class="text-center text-muted">Nenhuma solicitação.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($solicitacoes as $s): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($s['nome']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['email']); ?><?php echo $s['telefone'] ? ' · '.htmlspecialchars($s['telefone']) : ''; ?></small>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-success" onclick="aprovarSolicitacao(<?php echo (int)$grupo_id; ?>, <?php echo (int)$s['id']; ?>)"><i class="fas fa-check"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="rejeitarSolicitacao(<?php echo (int)$grupo_id; ?>, <?php echo (int)$s['id']; ?>)"><i class="fas fa-times"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function aprovarSolicitacao(grupoId, usuarioId) {
    $.post('../ajax/aprovar_solicitacao_grupo.php', { grupo_id: grupoId, usuario_id: usuarioId }, function(resp){
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Erro ao aprovar.');
        }
    }, 'json');
}
function rejeitarSolicitacao(grupoId, usuarioId) {
    $.post('../ajax/rejeitar_solicitacao_grupo.php', { grupo_id: grupoId, usuario_id: usuarioId }, function(resp){
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Erro ao rejeitar.');
        }
    }, 'json');
}
</script>

<?php include '../includes/footer.php'; ?>

<!-- Cropper para recorte de logo -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
var cropper = null;
document.getElementById('logo_input')?.addEventListener('change', function(e){
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const img = document.getElementById('logo_preview');
    img.src = url;
    img.style.display = 'block';
    if (cropper) { cropper.destroy(); cropper = null; }
    cropper = new Cropper(img, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        background: false,
        responsive: false,
        minContainerWidth: 128,
        minContainerHeight: 128,
        minCanvasWidth: 128,
        minCanvasHeight: 128
    });
});

// Intercepta submit para preencher hidden com imagem 128x128
document.getElementById('formEditarGrupo')?.addEventListener('submit', function(e){
    if (cropper) {
        const canvas = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: 'high' });
        if (canvas) {
            document.getElementById('logo_cropped').value = canvas.toDataURL('image/png');
        }
    }
});
</script>


