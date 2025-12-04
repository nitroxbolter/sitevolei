<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Detalhes do Jogo';
// Atualiza status global antes de carregar
atualizarStatusJogos($pdo);
requireLogin();

$jogo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($jogo_id <= 0) {
    $_SESSION['mensagem'] = 'Jogo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: jogos.php');
    exit();
}

$sql = "SELECT j.*, g.nome AS grupo_nome, u.nome AS criado_por_nome
        FROM jogos j
        LEFT JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        WHERE j.id = ?";
$stmt = executeQuery($pdo, $sql, [$jogo_id]);
$jogo = $stmt ? $stmt->fetch() : false;
if (!$jogo) {
    $_SESSION['mensagem'] = 'Jogo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: jogos.php');
    exit();
}

$sou_criador = ((int)$jogo['criado_por'] === (int)($_SESSION['user_id'] ?? 0));

// Confirmados
$stmt = executeQuery($pdo, "SELECT COUNT(*) AS qt FROM confirmacoes_presenca WHERE jogo_id = ? AND status = 'Confirmado'", [$jogo_id]);
$row = $stmt ? $stmt->fetch() : ['qt' => 0];
$confirmados = (int)$row['qt'];
$confirmados_sem_criador = max(0, $confirmados - 1);

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0">
            <i class="fas fa-volleyball-ball me-2"></i><?php echo htmlspecialchars($jogo['titulo']); ?>
        </h2>
        <div class="btn-group">
            <a href="jogos.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
            <?php if ($sou_criador): ?>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editarJogoModal"><i class="fas fa-edit me-1"></i>Editar</button>
                <button class="btn btn-danger" onclick="excluirJogo(<?php echo (int)$jogo_id; ?>)"><i class="fas fa-trash me-1"></i>Excluir</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-7 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <p class="mb-2"><i class="fas fa-users me-2"></i><strong>Grupo:</strong> <?php echo htmlspecialchars(empty($jogo['grupo_id']) ? 'Avulso' : ($jogo['grupo_nome'] ?? 'Avulso')); ?></p>
                <p class="mb-2"><i class="fas fa-list me-2"></i><strong>Modalidade:</strong> <?php echo htmlspecialchars($jogo['modalidade'] ?? '-'); ?></p>
                <p class="mb-2"><i class="fas fa-calendar me-2"></i><strong>Data:</strong> <?php echo formatarPeriodoJogo($jogo['data_jogo'], $jogo['data_fim'] ?? null); ?></p>
                <p class="mb-2"><i class="fas fa-flag-checkered me-2"></i><strong>Status:</strong> <?php echo htmlspecialchars($jogo['status']); ?></p>
                <p class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><strong>Local:</strong> <?php echo htmlspecialchars($jogo['local']); ?></p>
                <p class="mb-2"><i class="fas fa-user-friends me-2"></i><strong>Jogadores:</strong> <?php echo $confirmados_sem_criador; ?>/<?php echo (int)$jogo['max_jogadores']; ?></p>
                <?php if (!empty($jogo['contato'])): ?>
                <p class="mb-2"><i class="fas fa-phone me-2"></i><strong>Contato:</strong> <?php echo htmlspecialchars($jogo['contato']); ?></p>
                <?php endif; ?>
                <p class="mb-2"><i class="fas fa-user me-2"></i><strong>Criado por:</strong> <?php echo htmlspecialchars($jogo['criado_por_nome']); ?></p>
                <?php if (!empty($jogo['descricao'])): ?>
                <p class="mb-0"><i class="fas fa-info-circle me-2"></i><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($jogo['descricao'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Vagas</strong>
                <span class="badge bg-<?php echo ($jogo['vagas_disponiveis'] > 0 ? 'success' : 'danger'); ?>"><?php echo (int)$jogo['vagas_disponiveis']; ?> vagas</span>
            </div>
            <div class="card-body">
                <div class="progress" style="height:10px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo ($jogo['max_jogadores']>0? ($confirmados_sem_criador/$jogo['max_jogadores']*100):0); ?>%"></div>
                </div>
                <hr>
                <?php
                $confirmadosLista = [];
                $stmtC = executeQuery($pdo, "SELECT u.id, u.nome FROM confirmacoes_presenca cp JOIN usuarios u ON u.id = cp.usuario_id WHERE cp.jogo_id = ? AND cp.status = 'Confirmado'", [$jogo_id]);
                if ($stmtC) { $confirmadosLista = $stmtC->fetchAll(); }
                // Buscar já avaliados pelo criador para desabilitar botão
                $avaliadosIds = [];
                if ($sou_criador) {
                    $stAv = executeQuery($pdo, "SELECT avaliado_id FROM avaliacoes_reputacao WHERE avaliador_id = ? AND jogo_id = ?", [$_SESSION['user_id'], $jogo_id]);
                    if ($stAv) { $avaliadosIds = array_map('intval', array_column($stAv->fetchAll(), 'avaliado_id')); }
                }
                // Só permite classificar após o jogo ser finalizado explicitamente
                // Botão de classificar apenas quando status em DB for Finalizado
                $podeClassificarAgora = (($jogo['status'] ?? '') === 'Finalizado');
                ?>
                <h6 class="mt-3">Confirmados</h6>
                <?php if (empty($confirmadosLista)): ?>
                    <div class="text-muted">Ninguém confirmado ainda.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($confirmadosLista as $c): ?>
                        <?php if ((int)$c['id'] === (int)$jogo['criado_por']) continue; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($c['nome']); ?></span>
                            <?php if ($sou_criador): ?>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info" title="Ver Perfil" onclick="verPerfil(<?php echo (int)$jogo_id; ?>, <?php echo (int)$c['id']; ?>)"><i class="fas fa-user"></i></button>
                                    <?php 
                                    $jaAvaliado = in_array((int)$c['id'], $avaliadosIds, true);
                                    if ($podeClassificarAgora): 
                                        if ($jaAvaliado): ?>
                                            <button class="btn btn-sm btn-outline-success" title="Já classificado" disabled><i class="fas fa-star"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success" title="Classificar" data-nome="<?php echo htmlspecialchars($c['nome'], ENT_QUOTES); ?>" onclick="abrirClassificacaoUsuario(<?php echo (int)$jogo_id; ?>, <?php echo (int)$c['id']; ?>, this.dataset.nome)"><i class="fas fa-star"></i></button>
                                        <?php endif; 
                                    endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Remover" onclick="remover(<?php echo (int)$jogo_id; ?>, <?php echo (int)$c['id']; ?>)"><i class="fas fa-user-times"></i></button>
                                </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($sou_criador): ?>
<!-- Modal Editar Jogo -->
<div class="modal fade" id="editarJogoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/editar_jogo.php">
                <div class="modal-body">
                    <input type="hidden" name="jogo_id" value="<?php echo (int)$jogo_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_titulo">Título *</label>
                            <input type="text" class="form-control" id="ed_titulo" name="titulo" value="<?php echo htmlspecialchars($jogo['titulo']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_modalidade">Modalidade</label>
                            <select class="form-select" id="ed_modalidade" name="modalidade">
                                <?php $m = $jogo['modalidade'] ?? ''; ?>
                                <option value="">Selecione</option>
                                <option value="Volei" <?php echo $m==='Volei'?'selected':''; ?>>Volei</option>
                                <option value="Volei Quadra" <?php echo $m==='Volei Quadra'?'selected':''; ?>>Volei Quadra</option>
                                <option value="Volei Areia" <?php echo $m==='Volei Areia'?'selected':''; ?>>Volei Areia</option>
                                <option value="Beach Tenis" <?php echo $m==='Beach Tenis'?'selected':''; ?>>Beach Tenis</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_data">Data e Hora *</label>
                            <input type="datetime-local" class="form-control" id="ed_data" name="data_jogo" value="<?php echo date('Y-m-d\TH:i', strtotime($jogo['data_jogo'])); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_local">Local *</label>
                            <input type="text" class="form-control" id="ed_local" name="local" value="<?php echo htmlspecialchars($jogo['local']); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_qtd">Qtd de Jogadores</label>
                            <input type="number" class="form-control" id="ed_qtd" name="max_jogadores" value="<?php echo (int)$jogo['max_jogadores']; ?>" min="1" max="200">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ed_contato">Contato (opcional)</label>
                            <input type="text" class="form-control" id="ed_contato" name="contato" value="<?php echo htmlspecialchars($jogo['contato'] ?? ''); ?>" placeholder="Telefone, email...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ed_desc">Descrição</label>
                        <textarea class="form-control" id="ed_desc" name="descricao" rows="3"><?php echo htmlspecialchars($jogo['descricao']); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Classificar Jogadores -->
<div class="modal fade" id="classificarModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-star me-2"></i>Classificar Jogadores</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php
        $avaliaveis = [];
        $stmtAv = executeQuery($pdo, "SELECT u.id, u.nome FROM confirmacoes_presenca cp JOIN usuarios u ON u.id = cp.usuario_id WHERE cp.jogo_id = ? AND cp.status = 'Confirmado'", [$jogo_id]);
        if ($stmtAv) { $avaliaveis = $stmtAv->fetchAll(); }
        ?>
        <?php if (empty($avaliaveis)): ?>
            <div class="text-muted">Sem jogadores confirmados.</div>
        <?php else: ?>
            <form id="formClassificar">
                <input type="hidden" name="jogo_id" value="<?php echo (int)$jogo_id; ?>">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th class="text-center">Estrelas</th>
                                <th>Motivo (negativo)</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($avaliaveis as $av): if ((int)$av['id'] === (int)$jogo['criado_por']) continue; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($av['nome']); ?></strong></td>
                                <td class="text-center">
                                    <span class="stars-display" data-uid="<?php echo (int)$av['id']; ?>"></span>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm sel-motivo" data-uid="<?php echo (int)$av['id']; ?>">
                                        <option value="">-</option>
                                        <optgroup label="Negativo">
                                            <option value="gravidade falta do jogo">Não foi no jogo</option>
                                            <option value="atrasado">Chegou atrasado</option>
                                            <option value="atitude negativa">Atitude negativa</option>
                                            <option value="desrespeito">Desrespeito</option>
                                            <option value="outro_negativo">Outro</option>
                                        </optgroup>
                                        <optgroup label="Positivo">
                                            <option value="jogador prestativo">Jogador prestativo</option>
                                            <option value="jogador alegre">Jogador alegre</option>
                                            <option value="jogador normal">Jogador normal</option>
                                            <option value="outro_positivo">Outro</option>
                                        </optgroup>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm inp-obs" data-uid="<?php echo (int)$av['id']; ?>" placeholder="(visível apenas para você e o jogador)">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" onclick="enviarClassificacao()"><i class="fas fa-save me-1"></i>Salvar Avaliações</button>
      </div>
    </div>
  </div>
  </div>

<script>
function excluirJogo(id){
    if(!confirm('Tem certeza que deseja excluir este jogo?')) return;
    $.post('ajax/excluir_jogo.php', { jogo_id: id }, function(resp){
        if(resp && resp.success){
            window.location.href = 'jogos.php';
        } else {
            alert(resp && resp.message ? resp.message : 'Erro ao excluir jogo');
        }
    }, 'json').fail(function(){ alert('Erro ao excluir jogo'); });
}
</script>
<script>
function remover(jogoId, usuarioId){
    if(!confirm('Remover este participante?')) return;
    $.post('ajax/remover_participacao_jogo.php', { jogo_id: jogoId, usuario_id: usuarioId }, function(resp){
        if(resp && resp.success){ location.reload(); }
        else { alert(resp && resp.message ? resp.message : 'Erro ao remover'); }
    }, 'json').fail(function(){ alert('Erro ao remover'); });
}
</script>
<script>
// Mapeia categoria -> estrelas (-5..+5)
function starsFromMotivo(m){
  if(!m) return 0;
  m = (m+'').toLowerCase();
  if(m.indexOf('gravidade falta do jogo')!==-1) return -4;
  if(m.indexOf('nao foi')!==-1 || m.indexOf('não foi')!==-1) return -4;
  if(m.indexOf('desrespeito')!==-1) return -3;
  if(m.indexOf('atitude negativa')!==-1) return -3;
  if(m.indexOf('atrasado')!==-1) return -2;
  if(m.indexOf('outro_negativo')!==-1) return -1;
  if(m.indexOf('jogador prestativo')!==-1) return 4;
  if(m.indexOf('jogador alegre')!==-1) return 2;
  if(m.indexOf('jogador normal')!==-1) return 1;
  if(m.indexOf('outro_positivo')!==-1) return 1;
  return 0;
}
function renderStars(n){
  var abs = Math.abs(n);
  var cls = n<0 ? 'text-danger' : 'text-success';
  if(n===0) return '<span class="text-muted">0</span>';
  var html='';
  for(var i=0;i<abs;i++){ html += '<i class="fas fa-star '+cls+'"></i>'; }
  return html;
}
function updateMassStars(){
  document.querySelectorAll('.sel-motivo').forEach(function(sel){
    var uid = sel.getAttribute('data-uid');
    var n = starsFromMotivo(sel.value);
    var el = document.querySelector('.stars-display[data-uid="'+uid+'"]').innerHTML = renderStars(n);
  });
}
document.addEventListener('change', function(e){
  if(e.target && e.target.classList.contains('sel-motivo')){ updateMassStars(); }
});
document.addEventListener('DOMContentLoaded', updateMassStars);

function updateRuStars(){
  var m = document.getElementById('ru_motivo').value;
  var n = starsFromMotivo(m);
  document.getElementById('ru_stars').innerHTML = renderStars(n);
}
</script>
<?php
// Lista de pendentes para o criador
$pendentes = [];
$stmtP = executeQuery($pdo, "SELECT u.id, u.nome, u.email FROM confirmacoes_presenca cp JOIN usuarios u ON u.id = cp.usuario_id WHERE cp.jogo_id = ? AND cp.status = 'Pendente'", [$jogo_id]);
if ($stmtP) { $pendentes = $stmtP->fetchAll(); }
?>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Solicitações Pendentes</strong>
        <span class="badge bg-secondary"><?php echo count($pendentes); ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($pendentes)): ?>
            <div class="text-muted">Nenhuma solicitação.</div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($pendentes as $p): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($p['nome']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-info" title="Ver Perfil" onclick="verPerfil(<?php echo (int)$jogo_id; ?>, <?php echo (int)$p['id']; ?>)"><i class="fas fa-user"></i></button>
                        <button class="btn btn-sm btn-success" onclick="aprovar(<?php echo (int)$jogo_id; ?>, <?php echo (int)$p['id']; ?>)"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="rejeitar(<?php echo (int)$jogo_id; ?>, <?php echo (int)$p['id']; ?>)"><i class="fas fa-times"></i></button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
function aprovar(jogoId, usuarioId){
    $.post('ajax/aprovar_participacao_jogo.php', { jogo_id: jogoId, usuario_id: usuarioId }, function(resp){
        if(resp && resp.success){ location.reload(); }
        else { alert(resp && resp.message ? resp.message : 'Erro ao aprovar'); }
    }, 'json').fail(function(){ alert('Erro ao aprovar'); });
}
function rejeitar(jogoId, usuarioId){
    $.post('ajax/rejeitar_participacao_jogo.php', { jogo_id: jogoId, usuario_id: usuarioId }, function(resp){
        if(resp && resp.success){ location.reload(); }
        else { alert(resp && resp.message ? resp.message : 'Erro ao rejeitar'); }
    }, 'json').fail(function(){ alert('Erro ao rejeitar'); });
}
function verPerfil(jogoId, usuarioId){
    $.getJSON('ajax/get_usuario_jogo.php', { jogo_id: jogoId, usuario_id: usuarioId }, function(resp){
        if(resp && resp.success){
            const u = resp.usuario || {};
            const foto = u.foto_perfil ? u.foto_perfil : '../../assets/arquivos/06.png';
            $('#pv_foto').attr('src', foto);
            $('#pv_nome').text(u.nome || '');
            $('#pv_email').text(u.email || '');
            $('#pv_tel').text(u.telefone || '');
            $('#pv_nivel').text(u.nivel || '');
            const rep = parseInt(u.reputacao ?? 0, 10);
            const repEl = $('#pv_rep');
            repEl.removeClass();
            repEl.addClass('badge');
            repEl.css('background-color','');
            if (rep > 75) {
                repEl.addClass('bg-success');
            } else if (rep > 50) {
                repEl.addClass('bg-warning text-dark');
            } else if (rep > 25) {
                repEl.addClass('text-dark');
                repEl.css('background-color','#fd7e14');
            } else {
                repEl.addClass('bg-danger');
            }
            repEl.text(rep + ' pts');
            var modal = new bootstrap.Modal(document.getElementById('perfilUsuarioModal'));
            modal.show();
        } else {
            alert(resp && resp.message ? resp.message : 'Erro ao carregar perfil');
        }
    }).fail(function(){ alert('Erro ao carregar perfil'); });
}
</script>
<script>
function enviarClassificacao(){
    var rows = [];
    document.querySelectorAll('#formClassificar .sel-motivo').forEach(function(sel){
        var uid = sel.getAttribute('data-uid');
        var motivo = sel.value || '';
        var estrelas = starsFromMotivo(motivo);
        var obsEl = document.querySelector('.inp-obs[data-uid="'+uid+'"]');
        var obs = obsEl ? obsEl.value : '';
        if (estrelas !== 0 || (motivo || obs)) {
            rows.push({ usuario_id: parseInt(uid,10), estrelas: estrelas, motivo: motivo, observacoes: obs });
        }
    });
    if (rows.length === 0){
        alert('Preencha ao menos uma avaliação.');
        return;
    }
    $.post('ajax/classificar_jogadores.php', { jogo_id: <?php echo (int)$jogo_id; ?>, avaliacoes: rows }, function(resp){
        if(resp && resp.success){
            alert('Avaliações salvas!');
            location.reload();
        } else {
            alert(resp && resp.message ? resp.message : 'Erro ao salvar avaliações');
        }
    }, 'json').fail(function(){ alert('Erro ao salvar avaliações'); });
}
</script>
<script>
let rateTarget = { jogoId: null, usuarioId: null };
function abrirClassificacaoUsuario(jogoId, usuarioId, nome){
    rateTarget = { jogoId, usuarioId };
    document.getElementById('ru_nome').textContent = nome;
    document.getElementById('ru_motivo').value = '';
    document.getElementById('ru_obs').value = '';
    var modal = new bootstrap.Modal(document.getElementById('classificarUsuarioModal'));
    modal.show();
}
function enviarClassificacaoUsuario(){
    const motivo = document.getElementById('ru_motivo').value || '';
    const estrelas = starsFromMotivo(motivo);
    const observacoes = document.getElementById('ru_obs').value || '';
    const payload = [{ usuario_id: rateTarget.usuarioId, estrelas, motivo, observacoes }];
    $.post('ajax/classificar_jogadores.php', { jogo_id: rateTarget.jogoId, avaliacoes: payload }, function(resp){
        if(resp && resp.success){ alert('Avaliação salva!'); location.reload(); }
        else { alert(resp && resp.message ? resp.message : 'Erro ao salvar avaliação'); }
    }, 'json').fail(function(){ alert('Erro ao salvar avaliação'); });
}
</script>
<!-- Modal Classificar Usuário (individual) -->
<div class="modal fade" id="classificarUsuarioModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-star me-2"></i>Classificar <span id="ru_nome"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Estrelas</label>
            <div id="ru_stars" class="small text-muted">Selecione uma categoria</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="ru_motivo">Categoria</label>
            <select id="ru_motivo" class="form-select" onchange="updateRuStars()">
                <option value="">-</option>
                <optgroup label="Negativo">
                    <option value="gravidade falta do jogo">Não foi no jogo</option>
                    <option value="atrasado">Chegou atrasado</option>
                    <option value="atitude negativa">Atitude negativa</option>
                    <option value="desrespeito">Desrespeito</option>
                    <option value="outro_negativo">Outro</option>
                </optgroup>
                <optgroup label="Positivo">
                    <option value="jogador prestativo">Jogador prestativo</option>
                    <option value="jogador alegre">Jogador alegre</option>
                    <option value="jogador normal">Jogador normal</option>
                    <option value="outro_positivo">Outro</option>
                </optgroup>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="ru_obs">Observações</label>
            <input type="text" id="ru_obs" class="form-control" placeholder="(visível apenas para você e o jogador)">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" onclick="enviarClassificacaoUsuario()">Salvar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Perfil do Usuário -->
<div class="modal fade" id="perfilUsuarioModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user me-2"></i>Perfil do Jogador</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
            <img id="pv_foto" src="" class="rounded-circle me-3" width="64" height="64" alt="Foto">
            <div>
                <div id="pv_nome" class="fw-bold"></div>
                <small class="text-muted" id="pv_email"></small>
            </div>
        </div>
        <p class="mb-1"><strong>Telefone:</strong> <span id="pv_tel"></span></p>
        <p class="mb-1"><strong>Nível:</strong> <span id="pv_nivel"></span></p>
        <p class="mb-1"><strong>Reputação:</strong> <span id="pv_rep" class="badge"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>


