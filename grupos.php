<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Grupos';

// Obter todos os grupos ativos
$sql = "SELECT g.*, u.nome as admin_nome, 
               COUNT(gm.id) as total_membros,
               COUNT(j.id) as total_jogos
        FROM grupos g
        LEFT JOIN usuarios u ON g.administrador_id = u.id
        LEFT JOIN grupo_membros gm ON g.id = gm.grupo_id AND gm.ativo = 1
        LEFT JOIN jogos j ON g.id = j.grupo_id AND j.status = 'Aberto'
        WHERE g.ativo = 1
        GROUP BY g.id
        ORDER BY g.nome";
$stmt = executeQuery($pdo, $sql);
$grupos = $stmt ? $stmt->fetchAll() : [];

// Obter grupos do usuário (com contagem de membros)
$grupos_usuario = [];
if (isLoggedIn()) {
    $sql = "SELECT g.*,
                   COUNT(gm2.id) AS total_membros,
                   CASE WHEN g.administrador_id = ? THEN 1 ELSE 0 END AS is_admin
            FROM grupos g
            JOIN grupo_membros gm_user ON gm_user.grupo_id = g.id AND gm_user.usuario_id = ? AND gm_user.ativo = 1
            LEFT JOIN grupo_membros gm2 ON gm2.grupo_id = g.id AND gm2.ativo = 1
            WHERE g.ativo = 1
            GROUP BY g.id
            ORDER BY g.nome";
    $stmt = executeQuery($pdo, $sql, [$_SESSION['user_id'], $_SESSION['user_id']]);
    $grupos_usuario = $stmt ? $stmt->fetchAll() : [];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-users me-2"></i>Grupos de Vôlei
            </h2>
            <?php if (isLoggedIn()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarGrupoModal">
                    <i class="fas fa-plus me-2"></i>Criar Grupo
                </button>
            <?php else: ?>
                <button class="btn btn-outline-primary" onclick="mostrarLoginNecessario()">
                    <i class="fas fa-plus me-2"></i>Criar Grupo
                </button>
            <?php endif; ?>
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
                                   placeholder="Nome do grupo ou local...">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="nivel" class="form-label">Nível</label>
                            <select class="form-select" id="nivel" name="nivel">
                                <option value="">Todos os níveis</option>
                                <option value="Iniciante">Iniciante</option>
                                <option value="Intermediário">Intermediário</option>
                                <option value="Avançado">Avançado</option>
                                <option value="Profissional">Profissional</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="local" class="form-label">Local</label>
                            <input type="text" class="form-control" id="local" name="local" 
                                   placeholder="Cidade ou bairro...">
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

<!-- Meus Grupos -->
<?php if (!empty($grupos_usuario)): ?>
<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3">
            <i class="fas fa-user-friends me-2"></i>Meus Grupos
        </h4>
        <div class="row">
            <?php foreach ($grupos_usuario as $grupo): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                <?php echo htmlspecialchars($grupo['nome']); ?>
                            </h6>
                            <?php if ($grupo['is_admin']): ?>
                                <span class="badge bg-warning text-dark">Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($grupo['logo_id'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo 'assets/arquivos/logosgrupos/'.(int)$grupo['logo_id'].'.png'; ?>" alt="Logo do grupo" class="rounded-circle" width="64" height="64">
                                </div>
                            <?php endif; ?>
                            <p class="card-text">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <strong>Local:</strong> <?php echo htmlspecialchars($grupo['local_principal']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-phone me-2"></i>
                                <strong>Contato:</strong> <?php echo htmlspecialchars($grupo['contato'] ?? 'Não informado'); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-table-tennis me-2"></i>
                                <strong>Modalidade:</strong> <?php echo htmlspecialchars($grupo['modalidade'] ?? 'Não informado'); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-star me-2"></i>
                                <strong>Nível:</strong> <?php echo htmlspecialchars($grupo['nivel'] ?? 'Não informado'); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-user-friends me-2"></i>
                                <strong>Membros:</strong> <?php echo isset($grupo['total_membros']) ? (int)$grupo['total_membros'] : 0; ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <strong>Jogos:</strong> <?php echo isset($grupo['total_jogos']) ? (int)$grupo['total_jogos'] : 0; ?> ativos
                            </p>
                            <?php if ($grupo['descricao']): ?>
                                <p class="card-text">
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($grupo['descricao'], 0, 100)); ?>...</small>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Ver Detalhes
                                </a>
                                <?php if (!empty($grupo['is_admin'])): ?>
                                    <a href="admin/grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-cog me-1"></i>Gerenciar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Todos os Grupos -->
<div class="row">
    <div class="col-12">
        <h4 class="mb-3">
            <i class="fas fa-globe me-2"></i>Todos os Grupos
        </h4>
        
        <?php if (empty($grupos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhum grupo encontrado</h5>
                <p class="text-muted">Seja o primeiro a criar um grupo!</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#criarGrupoModal">
                    <i class="fas fa-plus me-2"></i>Criar Primeiro Grupo
                </button>
            </div>
        <?php else: ?>
            <div class="row" id="grupos-container">
                <?php foreach ($grupos as $grupo): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    <?php echo htmlspecialchars($grupo['nome']); ?>
                                </h6>
                                <span class="badge bg-info">
                                    <?php echo $grupo['total_membros']; ?> membros
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($grupo['logo_id'])): ?>
                                    <div class="mb-2 text-center">
                                        <img src="<?php echo 'assets/arquivos/logosgrupos/'.(int)$grupo['logo_id'].'.png'; ?>" alt="Logo do grupo" class="rounded-circle" width="64" height="64">
                                    </div>
                                <?php endif; ?>
                                <p class="card-text">
                                    <i class="fas fa-user me-2"></i>
                                    <strong>Admin:</strong> <?php echo htmlspecialchars($grupo['admin_nome']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-phone me-2"></i>
                                    <strong>Contato:</strong> <?php echo htmlspecialchars($grupo['contato'] ?? 'Não informado'); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-table-tennis me-2"></i>
                                    <strong>Modalidade:</strong> <?php echo htmlspecialchars($grupo['modalidade'] ?? 'Não informado'); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-star me-2"></i>
                                    <strong>Nível:</strong> <?php echo htmlspecialchars($grupo['nivel'] ?? 'Não informado'); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <strong>Local:</strong> <?php echo htmlspecialchars($grupo['local_principal']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>Membros:</strong> <?php echo isset($grupo['total_membros']) ? (int)$grupo['total_membros'] : 0; ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <strong>Jogos Ativos:</strong> <?php echo $grupo['total_jogos']; ?>
                                </p>
                                <?php if ($grupo['descricao']): ?>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($grupo['descricao'], 0, 120)); ?>...</small>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <a href="grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                                    </a>
                                    <?php
                                    $ja_e_membro = false;
                                    foreach ($grupos_usuario as $meu_grupo) {
                                        if ($meu_grupo['id'] == $grupo['id']) {
                                            $ja_e_membro = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if (isLoggedIn()): ?>
                                        <?php if (!$ja_e_membro): ?>
                                            <button class="btn btn-success btn-sm" onclick="solicitarEntrada(<?php echo $grupo['id']; ?>)">
                                                <i class="fas fa-user-plus me-1"></i>Entrar
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-success">Membro</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="mostrarLoginNecessario()">
                                            <i class="fas fa-sign-in-alt me-1"></i>Fazer Login
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

<!-- Modal Criar Grupo -->
<div class="modal fade" id="criarGrupoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Criar Novo Grupo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/criar_grupo.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome_grupo" class="form-label">Nome do Grupo *</label>
                            <input type="text" class="form-control" id="nome_grupo" name="nome" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="local_principal" class="form-label">Local Principal *</label>
                            <input type="text" class="form-control" id="local_principal" name="local_principal" 
                                   placeholder="Ex: Ginásio Municipal" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nivel_grupo" class="form-label">Nível do Grupo</label>
                            <select class="form-select" id="nivel_grupo" name="nivel_grupo">
                                <option value="">Todos os níveis</option>
                                <option value="Iniciante">Iniciante</option>
                                <option value="Amador">Amador</option>
                                <option value="Avançado">Avançado</option>
                                <option value="Profissional">Profissional</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contato_grupo" class="form-label">Contato</label>
                            <input type="text" class="form-control" id="contato_grupo" name="contato" 
                                   placeholder="Telefone, email ou outro contato">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modalidade" class="form-label">Modalidade</label>
                            <select class="form-select" id="modalidade" name="modalidade">
                                <option value="">Selecione</option>
                                <option value="Vôlei">Vôlei</option>
                                <option value="Vôlei Quadra">Vôlei Quadra</option>
                                <option value="Vôlei Areia">Vôlei Areia</option>
                                <option value="Beach Tênis">Beach Tênis</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Logo do Grupo (opcional)</label>
                            <input type="file" id="logo_input" accept="image/*" class="form-control">
                            <small class="text-muted d-block mt-1">Tamanho exigido: 128 x 128 pixels. O recorte será aplicado nesta área fixa.</small>
                            <input type="hidden" name="logo_cropped" id="logo_cropped">
                            <div class="mt-2" style="width:128px; height:128px; overflow:hidden; border:1px dashed #ccc; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                                <img id="logo_preview" style="width:128px; height:128px; display:none; object-fit:cover;" alt="Prévia da logo">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_grupo" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao_grupo" name="descricao" rows="3" 
                                  placeholder="Descreva o grupo, regras, horários, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnCriarGrupo">
                        <i class="fas fa-plus me-2"></i>Criar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Solicitações -->
<div class="modal fade" id="solicitacoesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-inbox me-2"></i>Solicitações Pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="solicitacoesLista">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
    </div>

<script>
function solicitarEntrada(grupoId) {
    if (confirm('Deseja solicitar entrada neste grupo?')) {
        $.ajax({
            url: 'ajax/solicitar_entrada_grupo.php',
            method: 'POST',
            data: { grupo_id: grupoId },
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

// Filtrar grupos
$('#filtros-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    
    $.ajax({
        url: 'ajax/filtrar_grupos.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#grupos-container').html(response.html);
            } else {
                showAlert('Erro ao filtrar grupos', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao filtrar grupos', 'danger');
        }
    });
});

// Função para mostrar modal de login necessário
function mostrarLoginNecessario() {
    // Redirecionar para página de login com mensagem
    window.location.href = 'auth/login.php?msg=login_necessario';
}

// Abrir solicitações pendentes (somente admin do grupo vê o botão)
function abrirSolicitacoes(grupoId) {
    $('#solicitacoesModal').data('grupo', grupoId);
    $('#solicitacoesLista').html('<div class="text-center py-3"><div class="spinner-border" role="status"></div></div>');
    var modal = new bootstrap.Modal(document.getElementById('solicitacoesModal'));
    modal.show();
    
    $.get('ajax/listar_solicitacoes_grupo.php', { grupo_id: grupoId }, function(resp) {
        if (!resp.success) {
            $('#solicitacoesLista').html('<div class="alert alert-danger">'+(resp.message||'Erro ao carregar solicitações')+'</div>');
            return;
        }
        if (!resp.solicitacoes || resp.solicitacoes.length === 0) {
            $('#solicitacoesLista').html('<div class="text-center text-muted py-3">Nenhuma solicitação pendente.</div>');
            return;
        }
        var html = '<ul class="list-group">';
        resp.solicitacoes.forEach(function(u){
            html += '<li class="list-group-item d-flex justify-content-between align-items-center">'
                 +  '<div>'
                 +  '<strong>'+escapeHtml(u.nome)+'</strong><br>'
                 +  '<small class="text-muted">'+(u.email||'')+' '+(u.telefone?(' · '+u.telefone):'')+'</small>'
                 +  '</div>'
                 +  '<div class="btn-group">'
                 +  '<button class="btn btn-sm btn-success" onclick="aprovarSolicitacao('+grupoId+','+u.id+')"><i class="fas fa-check"></i></button>'
                 +  '<button class="btn btn-sm btn-danger" onclick="rejeitarSolicitacao('+grupoId+','+u.id+')"><i class="fas fa-times"></i></button>'
                 +  '</div>'
                 +  '</li>';
        });
        html += '</ul>';
        $('#solicitacoesLista').html(html);
    }, 'json');
}

function aprovarSolicitacao(grupoId, usuarioId) {
    $.post('ajax/aprovar_solicitacao_grupo.php', { grupo_id: grupoId, usuario_id: usuarioId }, function(resp){
        if (resp.success) {
            abrirSolicitacoes(grupoId);
            showAlert('Solicitação aprovada.', 'success');
        } else {
            showAlert(resp.message || 'Erro ao aprovar.', 'danger');
        }
    }, 'json');
}

function rejeitarSolicitacao(grupoId, usuarioId) {
    $.post('ajax/rejeitar_solicitacao_grupo.php', { grupo_id: grupoId, usuario_id: usuarioId }, function(resp){
        if (resp.success) {
            abrirSolicitacoes(grupoId);
            showAlert('Solicitação rejeitada.', 'success');
        } else {
            showAlert(resp.message || 'Erro ao rejeitar.', 'danger');
        }
    }, 'json');
}

function escapeHtml(text) {
    return text ? text.replace(/["&'<>]/g, function (a) {
        return {'"':'&quot;','&':'&amp;','\'':'&#39;','<':'&lt;','>':'&gt;'}[a];
    }) : '';
}
</script>

<!-- Cropper.js para recorte da logo -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
var cropper = null;
$('#logo_input').on('change', function(e){
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

$('#btnCriarGrupo').on('click', function(e){
    // Se houver cropper ativo, gerar imagem 128x128
    if (cropper) {
        e.preventDefault();
        const canvas = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: 'high' });
        if (canvas) {
            const dataUrl = canvas.toDataURL('image/png');
            $('#logo_cropped').val(dataUrl);
        }
        // Submeter o formulário após preencher o hidden
        $(this).closest('form').trigger('submit');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
