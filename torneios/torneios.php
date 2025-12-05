<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Torneios';
requireLogin();

// Obter todos os torneios (incluindo finalizados)
$sql = "SELECT t.*, g.nome as grupo_nome, g.logo_id as grupo_logo_id, u.nome as criado_por_nome,
               COUNT(tp.id) as total_inscritos
        FROM torneios t
        LEFT JOIN grupos g ON t.grupo_id = g.id
        LEFT JOIN usuarios u ON t.criado_por = u.id
        LEFT JOIN torneio_participantes tp ON t.id = tp.torneio_id
        WHERE t.status IN ('Criado', 'Inscrições Abertas', 'Em Andamento', 'Finalizado')
        GROUP BY t.id
        ORDER BY CASE 
            WHEN t.status = 'Finalizado' THEN 1 
            ELSE 0 
        END, t.data_inicio ASC";
$stmt = executeQuery($pdo, $sql);
$torneios = $stmt ? $stmt->fetchAll() : [];

// Debug: verificar se há torneios
error_log("Total de torneios encontrados: " . count($torneios));
if (count($torneios) > 0) {
    error_log("Primeiro torneio: " . print_r($torneios[0], true));
}

// Obter grupos do usuário para filtro
$grupos_usuario = getGruposUsuario($pdo, $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-trophy me-2"></i>Torneios de Vôlei
            </h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarTorneioModal">
                <i class="fas fa-plus me-2"></i>Criar Torneio
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
                        <div class="col-md-4 mb-3">
                            <label for="busca" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busca" name="busca" 
                                   placeholder="Nome do torneio...">
                        </div>
                        <div class="col-md-3 mb-3">
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
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos os status</option>
                                <option value="Criado">Criado</option>
                                <option value="Inscrições Abertas">Inscrições Abertas</option>
                                <option value="Em Andamento">Em Andamento</option>
                                <option value="Finalizado">Finalizado</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
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

<!-- Torneios -->
<div class="row">
    <div class="col-12">
        <?php if (empty($torneios)): ?>
            <div class="text-center py-5">
                <i class="fas fa-trophy fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhum torneio encontrado</h5>
                <p class="text-muted">Seja o primeiro a criar um torneio!</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarTorneioModal">
                    <i class="fas fa-plus me-2"></i>Criar Primeiro Torneio
                </button>
            </div>
        <?php else: ?>
            <div class="row" id="torneios-container">
                <?php foreach ($torneios as $torneio): 
                    // Verificar se há vagas disponíveis
                    $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
                    $totalInscritos = (int)$torneio['total_inscritos'];
                    $temVagas = ($maxParticipantes > 0 && $totalInscritos < $maxParticipantes);
                    $estaFinalizado = ($torneio['status'] === 'Finalizado');
                    
                    // Aplicar estilos baseado no status
                    if ($estaFinalizado) {
                        $cardClass = 'border-warning';
                        $cardStyle = 'background-color: #fff3cd;'; // Laranja fraquinho
                    } elseif ($temVagas) {
                        $cardClass = 'border-success';
                        $cardStyle = 'background-color: #d4edda;'; // Verde
                    } else {
                        $cardClass = '';
                        $cardStyle = '';
                    }
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 torneio-card <?php echo $cardClass; ?>" style="<?php echo $cardStyle; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    <?php echo htmlspecialchars($torneio['nome']); ?>
                                </h6>
                                <span class="badge bg-<?php 
                                    echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 
                                        ($torneio['status'] === 'Em Andamento' ? 'warning' : 
                                        ($torneio['status'] === 'Finalizado' ? 'dark' :
                                        ($torneio['status'] === 'Criado' ? 'info' : 'secondary'))); 
                                ?>">
                                    <?php echo htmlspecialchars($torneio['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>Grupo:</strong> 
                                    <?php if (!empty($torneio['grupo_logo_id']) && $torneio['grupo_nome']): ?>
                                        <img src="../assets/arquivos/logosgrupos/<?php echo (int)$torneio['grupo_logo_id']; ?>.png" 
                                             alt="<?php echo htmlspecialchars($torneio['grupo_nome']); ?>" 
                                             class="rounded-circle me-2" 
                                             width="20" 
                                             height="20" 
                                             style="object-fit:cover;"
                                             onerror="this.style.display='none';">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($torneio['grupo_nome'] ?: 'Avulso'); ?>
                                </p>
                                <?php if (!empty($torneio['data_inicio'])): ?>
                                <p class="card-text">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Início:</strong> <?php echo formatarData($torneio['data_inicio']); ?>
                                </p>
                                <?php endif; ?>
                                <p class="card-text">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>Inscritos:</strong> <?php 
                                    $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
                                    echo (int)$torneio['total_inscritos']; 
                                    if ($maxParticipantes > 0) {
                                        echo '/' . (int)$maxParticipantes;
                                    }
                                    ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-user me-2"></i>
                                    <strong>Criado por:</strong> <?php echo htmlspecialchars($torneio['criado_por_nome']); ?>
                                </p>
                                <?php if ($torneio['descricao']): ?>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($torneio['descricao'], 0, 100)); ?>...</small>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($torneio['data_inicio'])): ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo tempoRestante($torneio['data_inicio']); ?>
                                    </small>
                                    <?php 
                                    $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
                                    if ($maxParticipantes > 0): 
                                    ?>
                                    <div class="progress" style="width: 100px; height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo min(100, ($torneio['total_inscritos'] / $maxParticipantes) * 100); ?>%">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <a href="torneio.php?id=<?php echo $torneio['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                                    </a>
                                    <?php if ($torneio['status'] === 'Inscrições Abertas'): ?>
                                        <button class="btn btn-success btn-sm" 
                                                onclick="inscreverTorneio(<?php echo $torneio['id']; ?>)">
                                            <i class="fas fa-user-plus me-1"></i>Inscrever-se
                                        </button>
                                    <?php elseif ($torneio['status'] === 'Criado'): ?>
                                        <?php
                                        $sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
                                        $sou_admin_grupo = false;
                                        if ($torneio['grupo_id']) {
                                            $sql_check = "SELECT administrador_id FROM grupos WHERE id = ?";
                                            $stmt_check = executeQuery($pdo, $sql_check, [$torneio['grupo_id']]);
                                            $grupo_check = $stmt_check ? $stmt_check->fetch() : false;
                                            $sou_admin_grupo = $grupo_check && ((int)$grupo_check['administrador_id'] === (int)$_SESSION['user_id']);
                                        }
                                        if ($sou_criador || $sou_admin_grupo || isAdmin($pdo, $_SESSION['user_id'])):
                                        ?>
                                            <a href="admin/gerenciar_torneio.php?id=<?php echo $torneio['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-cog me-1"></i>Gerenciar
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($torneio['status']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($torneio['status']); ?></span>
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

<!-- Modal Criar Torneio -->
<div class="modal fade" id="criarTorneioModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Criar Novo Torneio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCriarTorneio">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome_torneio" class="form-label">Nome do Torneio *</label>
                            <input type="text" class="form-control" id="nome_torneio" name="nome" 
                                   placeholder="Ex: Torneio de Verão" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_torneio" class="form-label">Data do Torneio *</label>
                            <input type="date" class="form-control" id="data_torneio" name="data_torneio" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_torneio" class="form-label">Tipo de Torneio *</label>
                            <select class="form-select" id="tipo_torneio" name="tipo" required onchange="toggleGrupoSelect()">
                                <option value="grupo">Torneio do Grupo</option>
                                <option value="avulso">Torneio Avulso</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="grupo_torneio_container">
                            <label for="grupo_torneio" class="form-label">Grupo *</label>
                            <select class="form-select" id="grupo_torneio" name="grupo_id">
                                <option value="">Selecione um grupo</option>
                                <?php foreach ($grupos_usuario as $grupo): ?>
                                    <option value="<?php echo $grupo['id']; ?>">
                                        <?php echo htmlspecialchars($grupo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Criar Torneio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleGrupoSelect() {
    var tipo = document.getElementById('tipo_torneio').value;
    var container = document.getElementById('grupo_torneio_container');
    var select = document.getElementById('grupo_torneio');
    
    if (tipo === 'grupo') {
        container.style.display = 'block';
        select.required = true;
    } else {
        container.style.display = 'none';
        select.required = false;
        select.value = '';
    }
}

$(document).ready(function() {
    $('#formCriarTorneio').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/criar_torneio.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    var modalElement = document.getElementById('criarTorneioModal');
                    if (modalElement) {
                        var modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    setTimeout(function() {
                        if (response.torneio_id) {
                            window.location.href = 'admin/gerenciar_torneio.php?id=' + response.torneio_id;
                        } else {
                            location.reload();
                        }
                    }, 1000);
                } else {
                    var mensagem = response.message || 'Erro ao criar torneio';
                    if (response.debug) {
                        mensagem += '<br><small><strong>Debug:</strong> ' + response.debug + '</small>';
                    }
                    if (typeof showAlert === 'function') {
                        showAlert(mensagem, 'danger');
                    } else {
                        alert(mensagem.replace(/<[^>]*>/g, ''));
                    }
                    console.error('Erro ao criar torneio:', response);
                }
            },
            error: function(xhr, status, error) {
                var mensagem = 'Erro ao criar torneio';
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        mensagem = response.message || mensagem;
                        if (response.debug) {
                            mensagem += '<br><small><strong>Debug:</strong> ' + response.debug + '</small>';
                        }
                    } catch(e) {
                        mensagem += '<br><small>Resposta do servidor: ' + xhr.responseText.substring(0, 200) + '</small>';
                    }
                }
                if (typeof showAlert === 'function') {
                    showAlert(mensagem, 'danger');
                } else {
                    alert(mensagem.replace(/<[^>]*>/g, ''));
                }
                console.error('Erro AJAX:', status, error, xhr);
            }
        });
    });
});
</script>

<script>
function inscreverTorneio(torneioId) {
    if (confirm('Deseja se inscrever neste torneio?')) {
        $.ajax({
            url: 'ajax/inscrever_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao processar solicitação', 'danger');
            }
        });
    }
}

// Filtrar torneios
$('#filtros-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    
    $.ajax({
        url: 'ajax/filtrar_torneios.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#torneios-container').html(response.html);
            } else {
                showAlert('Erro ao filtrar torneios', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao filtrar torneios', 'danger');
        }
    });
});

// Definir data mínima como hoje
$(document).ready(function() {
    var hoje = new Date().toISOString().slice(0, 16);
    $('#data_inicio_torneio').attr('min', hoje);
});
</script>

<?php include '../includes/footer.php'; ?>
