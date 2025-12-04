<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Jogos';
// Atualiza status conforme data de fim/início
atualizarStatusJogos($pdo);
requireLogin();

// Obter todos os jogos ativos
$sql = "SELECT j.*, g.nome as grupo_nome, g.local_principal, u.nome as criado_por_nome,
               COUNT(cp.id) as total_confirmacoes,
               SUM(CASE WHEN cp.status = 'Confirmado' THEN 1 ELSE 0 END) as confirmados
        FROM jogos j
        LEFT JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id
        GROUP BY j.id
        ORDER BY j.data_jogo DESC";
$stmt = executeQuery($pdo, $sql);
$jogos = $stmt ? $stmt->fetchAll() : [];

// Obter grupos do usuário para filtro
$grupos_usuario = getGruposUsuario($pdo, $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-calendar-alt me-2"></i>Jogos de Vôlei
            </h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarJogoModal">
                <i class="fas fa-plus me-2"></i>Criar Jogo
            </button>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form id="filtros-form">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="busca" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busca" name="busca" 
                                   placeholder="Título ou local...">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="grupo" class="form-label">Grupo</label>
                            <select class="form-select" id="grupo" name="grupo">
                                <option value="">Todos os grupos</option>
                                <?php foreach ($grupos_usuario as $grupo): ?>
                                    <option value="<?php echo $grupo['id']; ?>">
                                        <?php echo htmlspecialchars($grupo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Aberto">Aberto</option>
                                <option value="Em Andamento">Em Andamento</option>
                                <option value="Fechado">Fechado</option>
                                <option value="Finalizado">Finalizado</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="vagas" class="form-label">Vagas</label>
                            <select class="form-select" id="vagas" name="vagas">
                                <option value="">Todas</option>
                                <option value="disponiveis">Com vagas</option>
                                <option value="completos">Completos</option>
                            </select>
                        </div>
                        <div class="col-md-1 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Jogos -->
<div class="row">
    <div class="col-12">
        <?php if (empty($jogos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhum jogo encontrado</h5>
                <p class="text-muted">Seja o primeiro a criar um jogo!</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarJogoModal">
                    <i class="fas fa-plus me-2"></i>Criar Primeiro Jogo
                </button>
            </div>
        <?php else: ?>
            <div class="row" id="jogos-container">
                <?php foreach ($jogos as $jogo): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 jogo-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-volleyball-ball me-2"></i>
                                    <?php echo htmlspecialchars($jogo['titulo']); ?>
                                </h6>
                                <span class="badge bg-<?php echo $jogo['vagas_disponiveis'] > 0 ? 'success' : 'danger'; ?>">
                                    <?php echo $jogo['vagas_disponiveis']; ?> vagas
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>Grupo:</strong> <?php echo htmlspecialchars(empty($jogo['grupo_id']) ? 'Avulso' : ($jogo['grupo_nome'] ?? 'Avulso')); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Status:</strong> <span class="badge bg-<?php echo $jogo['status']==='Aberto'?'success':($jogo['status']==='Em Andamento'?'info':($jogo['status']==='Fechado'?'warning text-dark':'secondary')); ?>"><?php echo htmlspecialchars($jogo['status']); ?></span>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Data:</strong> <?php echo formatarPeriodoJogo($jogo['data_jogo'], $jogo['data_fim'] ?? null); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <strong>Local:</strong> <?php echo htmlspecialchars($jogo['local']); ?>
                                </p>
                                <?php 
                                    $confirmadosSemCriador = max(0, (int)$jogo['confirmados'] - 1);
                                ?>
                                <p class="card-text">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>Jogadores:</strong> <?php echo $confirmadosSemCriador; ?>/<?php echo $jogo['max_jogadores']; ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-user me-2"></i>
                                    <strong>Criado por:</strong> <?php echo htmlspecialchars($jogo['criado_por_nome']); ?>
                                </p>
                                <?php if ($jogo['descricao']): ?>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($jogo['descricao'], 0, 100)); ?>...</small>
                                    </p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-end align-items-center">
                                    <div class="progress" style="width: 100px; height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo ($jogo['max_jogadores'] > 0 ? ($confirmadosSemCriador / $jogo['max_jogadores']) * 100 : 0); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <a href="jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                                    </a>
                                    <?php
                                    $confirmacao = isJogoConfirmado($pdo, $jogo['id'], $_SESSION['user_id']);
                                    ?>
                                    <?php 
                                    $pendente = isJogoPendente($pdo, $jogo['id'], $_SESSION['user_id']);
                                    ?>
                                    <?php if ($confirmacao): ?>
                                        <span class="badge bg-success">Confirmado</span>
                                    <?php elseif ($pendente): ?>
                                        <span class="badge bg-warning text-dark">Pendente</span>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm" onclick="pedirEntrada(<?php echo $jogo['id']; ?>)">
                                            <i class="fas fa-user-plus me-1"></i>Pedir Entrada
                                        </button>
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

<!-- Modal Criar Jogo -->
<div class="modal fade" id="criarJogoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Criar Novo Jogo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/criar_jogo.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titulo_jogo" id="lbl_titulo_jogo" class="form-label">Título do Jogo *</label>
                            <input type="text" class="form-control" id="titulo_jogo" name="titulo" aria-labelledby="lbl_titulo_jogo"
                                   placeholder="Ex: Jogo de Sábado" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="grupo_jogo" id="lbl_grupo_jogo" class="form-label">Grupo</label>
                            <select class="form-select" id="grupo_jogo" name="grupo_id" aria-labelledby="lbl_grupo_jogo">
                                <option value="0" selected>Avulso (sem grupo)</option>
                                <?php foreach ($grupos_usuario as $grupo): ?>
                                    <option value="<?php echo $grupo['id']; ?>">
                                        <?php echo htmlspecialchars($grupo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modalidade_jogo" id="lbl_modalidade_jogo" class="form-label">Modalidade *</label>
                            <select class="form-select" id="modalidade_jogo" name="modalidade" aria-labelledby="lbl_modalidade_jogo" required>
                                <option value="">Selecione</option>
                                <option value="Volei">Volei</option>
                                <option value="Volei Quadra">Volei Quadra</option>
                                <option value="Volei Areia">Volei Areia</option>
                                <option value="Beach Tenis">Beach Tenis</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contato_jogo" id="lbl_contato_jogo" class="form-label">Contato (opcional)</label>
                            <input type="text" class="form-control" id="contato_jogo" name="contato" aria-labelledby="lbl_contato_jogo" placeholder="Telefone, email...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_jogo" id="lbl_data_jogo" class="form-label">Data e Hora *</label>
                            <input type="datetime-local" class="form-control" id="data_jogo" name="data_jogo" aria-labelledby="lbl_data_jogo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="local_jogo" id="lbl_local_jogo" class="form-label">Local *</label>
                            <input type="text" class="form-control" id="local_jogo" name="local" aria-labelledby="lbl_local_jogo"
                                   placeholder="Ex: Ginásio Municipal" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_fim_jogo" class="form-label">Término (opcional)</label>
                            <input type="datetime-local" class="form-control" id="data_fim_jogo" name="data_fim_jogo">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="max_jogadores_jogo" id="lbl_max_jogadores_jogo" class="form-label">Qtd de Jogadores</label>
                            <input type="number" class="form-control" id="max_jogadores_jogo" name="max_jogadores" aria-labelledby="lbl_max_jogadores_jogo"
                                   value="1" min="1" max="200">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nivel_jogo" id="lbl_nivel_jogo" class="form-label">Nível Sugerido</label>
                            <select class="form-select" id="nivel_jogo" name="nivel_sugerido" aria-labelledby="lbl_nivel_jogo">
                                <option value="">Todos os níveis</option>
                                <option value="Iniciante">Iniciante</option>
                                <option value="Intermediário">Intermediário</option>
                                <option value="Avançado">Avançado</option>
                                <option value="Profissional">Profissional</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_jogo" id="lbl_descricao_jogo" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao_jogo" name="descricao" rows="3" aria-labelledby="lbl_descricao_jogo"
                                  placeholder="Regras especiais, observações, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Criar Jogo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filtrar jogos
$('#filtros-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    
    $.ajax({
        url: 'ajax/filtrar_jogos.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#jogos-container').html(response.html);
            } else {
                showAlert('Erro ao filtrar jogos', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao filtrar jogos', 'danger');
        }
    });
});

// Permitir selecionar qualquer data e hora (inclui o dia de hoje)
</script>
<script>
function pedirEntrada(id){
    $.post('ajax/solicitar_participacao_jogo.php', { jogo_id: id }, function(resp){
        if(resp && resp.success){ location.reload(); }
        else { showAlert(resp && resp.message ? resp.message : 'Erro ao solicitar', 'danger'); }
    }, 'json').fail(function(){ showAlert('Erro ao solicitar', 'danger'); });
}
</script>

<?php include '../includes/footer.php'; ?>
