<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Sistema de Pontuação';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupo.php');
    exit();
}

// Verificar se é admin do grupo
$sql = "SELECT g.*, u.nome AS admin_nome FROM grupos g LEFT JOIN usuarios u ON u.id=g.administrador_id WHERE g.id=?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupo.php');
    exit();
}

$sou_admin_grupo = ($grupo['administrador_id'] ?? 0) == ($_SESSION['user_id'] ?? 0);
if (!$sou_admin_grupo && !isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['mensagem'] = 'Apenas o administrador do grupo pode gerenciar o sistema de pontuação.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupo.php?id='.$grupo_id);
    exit();
}

// Buscar sistema de pontuação ativo do grupo
$sql = "SELECT * FROM sistemas_pontuacao WHERE grupo_id = ? AND ativo = 1 ORDER BY data_criacao DESC LIMIT 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$sistema = $stmt ? $stmt->fetch() : false;

// Buscar membros do grupo
$sql = "SELECT u.id, u.nome, u.foto_perfil, COALESCE(u.reputacao,0) AS reputacao
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1
        ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$membros = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i>Sistema de Pontuação: <?php echo htmlspecialchars($grupo['nome']); ?>
        </h2>
        <a href="grupo.php?id=<?php echo $grupo_id; ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<?php if (!$sistema): ?>
    <!-- Criar novo sistema de pontuação -->
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Criar Sistema de Pontuação</h5>
                </div>
                <div class="card-body">
                    <form id="formCriarSistema">
                        <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Sistema *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required 
                                   placeholder="Ex: Campeonato 2024, Liga de Inverno, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="data_inicial" class="form-label">Data Inicial *</label>
                                <input type="date" class="form-control" id="data_inicial" name="data_inicial" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_final" class="form-label">Data Final *</label>
                                <input type="date" class="form-control" id="data_final" name="data_final" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quantidade_jogos" class="form-label">Quantidade de Jogos *</label>
                            <input type="number" class="form-control" id="quantidade_jogos" name="quantidade_jogos" 
                                   min="1" required>
                            <small class="text-muted">Número total de jogos que serão realizados neste sistema.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Criar Sistema
                            </button>
                            <a href="grupo.php?id=<?php echo $grupo_id; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Gerenciar sistema existente -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy me-2"></i><?php echo htmlspecialchars($sistema['nome']); ?>
                    </h5>
                    <div>
                        <a href="../grupos/pontuacao_classificacao.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>Ver Classificação
                        </a>
                        <button class="btn btn-warning btn-sm" onclick="desativarSistema(<?php echo $sistema['id']; ?>)">
                            <i class="fas fa-ban me-1"></i>Desativar Sistema
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Data Inicial:</strong><br>
                            <?php echo date('d/m/Y', strtotime($sistema['data_inicial'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Data Final:</strong><br>
                            <div class="d-flex align-items-center gap-2">
                                <span id="dataFinalDisplay"><?php echo date('d/m/Y', strtotime($sistema['data_final'])); ?></span>
                                <input type="date" id="dataFinalInput" class="form-control form-control-sm d-none" 
                                       value="<?php echo date('Y-m-d', strtotime($sistema['data_final'])); ?>" style="max-width: 150px;">
                                <button class="btn btn-sm btn-link p-0" onclick="editarDataFinal()" id="btnEditarDataFinal">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <strong>Total de Jogos:</strong><br>
                            <div class="d-flex align-items-center gap-2">
                                <span id="totalJogosDisplay"><?php echo (int)$sistema['quantidade_jogos']; ?></span>
                                <input type="number" id="totalJogosInput" class="form-control form-control-sm d-none" 
                                       value="<?php echo (int)$sistema['quantidade_jogos']; ?>" min="1" style="max-width: 100px;">
                                <button class="btn btn-sm btn-link p-0" onclick="editarTotalJogos()" id="btnEditarTotalJogos">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <strong>Jogos Criados:</strong><br>
                            <?php
                            $sql = "SELECT COUNT(*) AS total FROM sistema_pontuacao_jogos WHERE sistema_id = ?";
                            $stmt = executeQuery($pdo, $sql, [$sistema['id']]);
                            $totalJogos = $stmt ? (int)$stmt->fetch()['total'] : 0;
                            echo $totalJogos;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gerenciar Jogos -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Jogos do Sistema</h5>
                    <button class="btn btn-primary btn-sm" onclick="abrirModalCriarJogo()">
                        <i class="fas fa-plus me-1"></i>Adicionar Jogo
                    </button>
                </div>
                <div class="card-body">
                    <div id="listaJogos">
                        <?php
                        $sql = "SELECT * FROM sistema_pontuacao_jogos WHERE sistema_id = ? ORDER BY numero_jogo";
                        $stmt = executeQuery($pdo, $sql, [$sistema['id']]);
                        $jogos = $stmt ? $stmt->fetchAll() : [];
                        
                        if (empty($jogos)):
                        ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>Nenhum jogo cadastrado ainda.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jogo #</th>
                                            <th>Data</th>
                                            <th>Descrição</th>
                                            <th>Pontos Registrados</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jogos as $jogo): ?>
                                            <?php
                                            $sql = "SELECT COUNT(*) AS total FROM sistema_pontuacao_pontos WHERE jogo_id = ?";
                                            $stmt = executeQuery($pdo, $sql, [$jogo['id']]);
                                            $totalPontos = $stmt ? (int)$stmt->fetch()['total'] : 0;
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo (int)$jogo['numero_jogo']; ?></strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($jogo['data_jogo'])); ?></td>
                                                <td><?php echo htmlspecialchars($jogo['descricao'] ?? 'Sem descrição'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $totalPontos; ?> jogadores</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-primary" onclick="abrirModalPontos(<?php echo $jogo['id']; ?>, <?php echo $jogo['numero_jogo']; ?>)" title="Gerenciar Pontos">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-info" onclick="abrirModalParticipantes(<?php echo $jogo['id']; ?>, <?php echo $jogo['numero_jogo']; ?>)" title="Gerenciar Participantes">
                                                            <i class="fas fa-users"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="excluirJogo(<?php echo $jogo['id']; ?>)" title="Excluir Jogo">
                                                            <i class="fas fa-trash"></i>
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
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Criar Jogo -->
<div class="modal fade" id="modalCriarJogo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Adicionar Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCriarJogo">
                <input type="hidden" name="sistema_id" value="<?php echo isset($sistema) ? (int)$sistema['id'] : 0; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="numero_jogo" class="form-label">Número do Jogo *</label>
                            <input type="number" class="form-control" id="numero_jogo" name="numero_jogo" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_jogo" class="form-label">Data do Jogo *</label>
                            <input type="date" class="form-control" id="data_jogo" name="data_jogo" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricao_jogo" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao_jogo" name="descricao" rows="2" 
                                  placeholder="Observações sobre o jogo..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Participantes do Jogo *</label>
                        <small class="text-muted d-block mb-2">Selecione os membros que participarão deste jogo</small>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($membros)): ?>
                                <p class="text-muted mb-0">Nenhum membro no grupo.</p>
                            <?php else: ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="selecionar_todos" onchange="selecionarTodosParticipantes(this.checked)">
                                    <label class="form-check-label fw-bold" for="selecionar_todos">
                                        Selecionar Todos
                                    </label>
                                </div>
                                <hr class="my-2">
                                <?php foreach ($membros as $membro): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input participante-checkbox" type="checkbox" 
                                               name="participantes[]" value="<?php echo (int)$membro['id']; ?>" 
                                               id="participante_<?php echo (int)$membro['id']; ?>">
                                        <label class="form-check-label d-flex align-items-center gap-2" 
                                               for="participante_<?php echo (int)$membro['id']; ?>">
                                            <?php 
                                            // Corrigir caminho da foto de perfil
                                            if (!empty($membro['foto_perfil'])) {
                                                // Se já começa com http ou /, usar como está
                                                if (strpos($membro['foto_perfil'], 'http') === 0 || strpos($membro['foto_perfil'], '/') === 0) {
                                                    $avatar = $membro['foto_perfil'];
                                                } elseif (strpos($membro['foto_perfil'], 'assets/') === 0) {
                                                    // Se já tem assets/, adicionar ../
                                                    $avatar = '../' . $membro['foto_perfil'];
                                                } else {
                                                    // Se não tem caminho, adicionar caminho completo
                                                    $avatar = '../assets/arquivos/' . $membro['foto_perfil'];
                                                }
                                            } else {
                                                $avatar = '../assets/arquivos/logo.png';
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                 class="rounded-circle" width="24" height="24" 
                                                 style="object-fit:cover;" alt="Avatar">
                                            <span><?php echo htmlspecialchars($membro['nome']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Jogo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Pontos -->
<div class="modal fade" id="modalGerenciarPontos" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Gerenciar Pontos - Jogo #<span id="numeroJogoModal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pontos-tab" data-bs-toggle="tab" data-bs-target="#pontos" type="button" role="tab">
                            <i class="fas fa-star me-1"></i>Pontos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="times-tab" data-bs-toggle="tab" data-bs-target="#times" type="button" role="tab">
                            <i class="fas fa-users me-1"></i>Times
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participantes-tab" data-bs-toggle="tab" data-bs-target="#participantes" type="button" role="tab">
                            <i class="fas fa-user-friends me-1"></i>Participantes
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pontos" role="tabpanel">
                        <div id="formPontos">
                            <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="times" role="tabpanel">
                        <div id="formTimes">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Gerenciar Times (Apenas Visual)</h6>
                                <button class="btn btn-sm btn-primary" onclick="adicionarTime()">
                                    <i class="fas fa-plus me-1"></i>Adicionar Time
                                </button>
                            </div>
                            <div id="listaTimes">
                                <p class="text-muted">Carregando times...</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="participantes" role="tabpanel">
                        <div id="formParticipantes">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Participantes do Jogo</h6>
                                    <div id="listaParticipantesJogo" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                        <p class="text-muted">Carregando participantes...</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">Membros Disponíveis do Grupo</h6>
                                    <div id="listaMembrosDisponiveis" class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                                        <p class="text-muted">Carregando membros...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="salvarPontos()">Salvar Pontos</button>
            </div>
        </div>
    </div>
</div>

<script>
// Garantir que showAlert esteja disponível (fallback se main.js não carregou)
(function() {
    if (typeof window.showAlert === 'undefined') {
        window.showAlert = function(message, type) {
            type = type || 'info';
            var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
            
            // Usar jQuery se disponível, senão usar JavaScript puro
            if (typeof $ !== 'undefined') {
                $('.container').first().prepend(alertHtml);
                setTimeout(function() {
                    $('.alert').first().fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            } else {
                var container = document.querySelector('.container');
                if (container) {
                    container.insertAdjacentHTML('afterbegin', alertHtml);
                } else {
                    document.body.insertAdjacentHTML('afterbegin', alertHtml);
                }
                setTimeout(function() {
                    var alert = document.querySelector('.alert');
                    if (alert) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            if (alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 500);
                    }
                }, 5000);
            }
        };
    }
})();

let jogoAtualId = null;

function selecionarTodosParticipantes(checked) {
    document.querySelectorAll('.participante-checkbox').forEach(function(checkbox) {
        checkbox.checked = checked;
    });
}

function abrirModalCriarJogo() {
    const modal = new bootstrap.Modal(document.getElementById('modalCriarJogo'));
    modal.show();
    // Resetar seleção ao abrir
    document.getElementById('selecionar_todos').checked = false;
    selecionarTodosParticipantes(false);
}

function abrirModalPontos(jogoId, numeroJogo) {
    jogoAtualId = jogoId;
    document.getElementById('numeroJogoModal').textContent = numeroJogo;
    
    // Carregar pontos
    $.ajax({
        url: '../ajax/carregar_pontos_jogo.php',
        method: 'GET',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Jogador</th><th>Pontos</th></tr></thead><tbody>';
                
                if (response.jogadores.length === 0) {
                    html += '<tr><td colspan="2" class="text-center text-muted">Nenhum participante encontrado.</td></tr>';
                } else {
                    response.jogadores.forEach(function(jogador) {
                        // Formatar pontos: se for inteiro, mostrar sem decimais
                        var pontos = parseFloat(jogador.pontos || 0);
                        var pontosValue = pontos == Math.floor(pontos) ? pontos.toString() : pontos.toFixed(2);
                        
                        // Corrigir caminho da foto de perfil
                        var avatar = '../assets/arquivos/logo.png';
                        if (jogador.foto_perfil) {
                            var foto = jogador.foto_perfil;
                            // Se já começa com http ou /, usar como está
                            if (foto.indexOf('http') === 0 || foto.indexOf('/') === 0) {
                                avatar = foto;
                            } else if (foto.indexOf('assets/') === 0) {
                                // Se já tem assets/, adicionar ../
                                avatar = '../' + foto;
                            } else {
                                // Se não tem caminho, adicionar caminho completo
                                avatar = '../assets/arquivos/' + foto;
                            }
                        }
                        
                        html += '<tr>';
                        html += '<td>';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<img src="' + avatar + '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">';
                        html += '<strong>' + jogador.nome + '</strong>';
                        html += '</div>';
                        html += '</td>';
                        html += '<td>';
                        html += '<input type="number" class="form-control pontos-jogador" data-usuario-id="' + jogador.id + '" value="' + pontosValue + '" step="0.01" min="0">';
                        html += '</td>';
                        html += '</tr>';
                    });
                }
                
                html += '</tbody></table></div>';
                document.getElementById('formPontos').innerHTML = html;
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar dados do jogo', 'danger');
        }
    });
    
    // Carregar times
    carregarTimes(jogoId);
    
    // Carregar participantes
    carregarParticipantes(jogoId);
    
    const modal = new bootstrap.Modal(document.getElementById('modalGerenciarPontos'));
    modal.show();
}

function abrirModalParticipantes(jogoId, numeroJogo) {
    jogoAtualId = jogoId;
    
    // Abrir modal primeiro
    const modal = new bootstrap.Modal(document.getElementById('modalGerenciarPontos'));
    modal.show();
    
    // Aguardar o modal abrir completamente antes de ativar a aba
    setTimeout(function() {
        document.getElementById('numeroJogoModal').textContent = numeroJogo;
        
        // Ativar aba de participantes
        const participantesTab = document.getElementById('participantes-tab');
        if (participantesTab) {
            const tab = new bootstrap.Tab(participantesTab);
            tab.show();
        }
        
        // Carregar participantes
        carregarParticipantes(jogoId);
    }, 300);
}

function carregarParticipantes(jogoId) {
    // Carregar participantes do jogo
    $.ajax({
        url: '../ajax/listar_participantes_jogo_pontuacao.php',
        method: 'GET',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted mb-0">Nenhum participante no jogo.</p>';
                } else {
                    response.participantes.forEach(function(participante) {
                        var avatar = '../assets/arquivos/logo.png';
                        if (participante.foto_perfil) {
                            var foto = participante.foto_perfil;
                            if (foto.indexOf('http') === 0 || foto.indexOf('/') === 0) {
                                avatar = foto;
                            } else if (foto.indexOf('assets/') === 0) {
                                avatar = '../' + foto;
                            } else {
                                avatar = '../assets/arquivos/' + foto;
                            }
                        }
                        
                        html += '<div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<img src="' + avatar + '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">';
                        html += '<span><strong>' + participante.nome + '</strong></span>';
                        html += '</div>';
                        html += '<button class="btn btn-sm btn-danger" onclick="removerParticipanteJogo(' + participante.usuario_id + ')">';
                        html += '<i class="fas fa-times"></i> Remover';
                        html += '</button>';
                        html += '</div>';
                    });
                }
                document.getElementById('listaParticipantesJogo').innerHTML = html;
            } else {
                document.getElementById('listaParticipantesJogo').innerHTML = '<p class="text-danger">Erro ao carregar participantes</p>';
            }
        },
        error: function() {
            document.getElementById('listaParticipantesJogo').innerHTML = '<p class="text-danger">Erro ao carregar participantes</p>';
        }
    });
    
    // Carregar membros disponíveis
    $.ajax({
        url: '../ajax/listar_membros_disponiveis_jogo_pontuacao.php',
        method: 'GET',
        data: { jogo_id: jogoId, grupo_id: <?php echo $grupo_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.membros.length === 0) {
                    html = '<p class="text-muted mb-0">Todos os membros já são participantes.</p>';
                } else {
                    response.membros.forEach(function(membro) {
                        var avatar = '../assets/arquivos/logo.png';
                        if (membro.foto_perfil) {
                            var foto = membro.foto_perfil;
                            if (foto.indexOf('http') === 0 || foto.indexOf('/') === 0) {
                                avatar = foto;
                            } else if (foto.indexOf('assets/') === 0) {
                                avatar = '../' + foto;
                            } else {
                                avatar = '../assets/arquivos/' + foto;
                            }
                        }
                        
                        html += '<div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<img src="' + avatar + '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">';
                        html += '<span><strong>' + membro.nome + '</strong></span>';
                        html += '</div>';
                        html += '<button class="btn btn-sm btn-success" onclick="adicionarParticipanteJogo(' + membro.id + ')">';
                        html += '<i class="fas fa-plus"></i> Adicionar';
                        html += '</button>';
                        html += '</div>';
                    });
                }
                document.getElementById('listaMembrosDisponiveis').innerHTML = html;
            } else {
                document.getElementById('listaMembrosDisponiveis').innerHTML = '<p class="text-danger">Erro ao carregar membros</p>';
            }
        },
        error: function() {
            document.getElementById('listaMembrosDisponiveis').innerHTML = '<p class="text-danger">Erro ao carregar membros</p>';
        }
    });
}

function adicionarParticipanteJogo(usuarioId) {
    if (!confirm('Deseja adicionar este membro como participante do jogo?')) return;
    
    $.ajax({
        url: '../ajax/adicionar_participante_jogo_pontuacao.php',
        method: 'POST',
        data: {
            jogo_id: jogoAtualId,
            usuario_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarParticipantes(jogoAtualId);
                // Recarregar pontos também para atualizar a lista
                abrirModalPontos(jogoAtualId, document.getElementById('numeroJogoModal').textContent);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao adicionar participante', 'danger');
        }
    });
}

function removerParticipanteJogo(usuarioId) {
    if (!confirm('Deseja remover este participante do jogo? Os pontos registrados serão mantidos, mas o participante não aparecerá mais na lista.')) return;
    
    $.ajax({
        url: '../ajax/remover_participante_jogo_pontuacao.php',
        method: 'POST',
        data: {
            jogo_id: jogoAtualId,
            usuario_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarParticipantes(jogoAtualId);
                // Recarregar pontos também para atualizar a lista
                abrirModalPontos(jogoAtualId, document.getElementById('numeroJogoModal').textContent);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao remover participante', 'danger');
        }
    });
}

function carregarTimes(jogoId) {
    $.ajax({
        url: '../ajax/carregar_times_jogo.php',
        method: 'GET',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.times.length === 0) {
                    html = '<p class="text-muted">Nenhum time criado ainda. Clique em "Adicionar Time" para criar.</p>';
                } else {
                    response.times.forEach(function(time) {
                        html += '<div class="card mb-3" data-time-id="' + time.id + '">';
                        html += '<div class="card-header d-flex justify-content-between align-items-center" style="background-color: ' + time.cor + '20; border-left: 4px solid ' + time.cor + ';">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<div style="width: 20px; height: 20px; background-color: ' + time.cor + '; border-radius: 4px;"></div>';
                        html += '<strong>' + time.nome + '</strong>';
                        html += '</div>';
                        html += '<div class="btn-group btn-group-sm">';
                        html += '<button class="btn btn-outline-secondary" onclick="editarTime(' + time.id + ', \'' + time.nome + '\', \'' + time.cor + '\')"><i class="fas fa-edit"></i></button>';
                        html += '<button class="btn btn-outline-danger" onclick="excluirTime(' + time.id + ')"><i class="fas fa-trash"></i></button>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="card-body">';
                        html += '<div class="d-flex flex-wrap gap-2" id="jogadores-time-' + time.id + '">';
                        if (time.jogadores && time.jogadores.length > 0) {
                            time.jogadores.forEach(function(jogador) {
                                html += '<span class="badge bg-secondary d-flex align-items-center gap-1">';
                                html += '<img src="' + (jogador.foto_perfil || '../assets/arquivos/logo.png') + '" class="rounded-circle" width="16" height="16" style="object-fit:cover;">';
                                html += jogador.nome;
                                html += '<button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removerJogadorTime(' + time.id + ', ' + jogador.id + ')"></button>';
                                html += '</span>';
                            });
                        } else {
                            html += '<span class="text-muted">Nenhum jogador no time</span>';
                        }
                        html += '</div>';
                        html += '<button class="btn btn-sm btn-outline-primary mt-2" onclick="adicionarJogadorTime(' + time.id + ')"><i class="fas fa-user-plus me-1"></i>Adicionar Jogador</button>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
                document.getElementById('listaTimes').innerHTML = html;
            } else {
                document.getElementById('listaTimes').innerHTML = '<p class="text-danger">Erro ao carregar times</p>';
            }
        },
        error: function() {
            document.getElementById('listaTimes').innerHTML = '<p class="text-danger">Erro ao carregar times</p>';
        }
    });
}

function adicionarTime() {
    const nome = prompt('Nome do time:', 'Time ' + (document.querySelectorAll('[data-time-id]').length + 1));
    if (!nome) return;
    
    const cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14'];
    const cor = cores[document.querySelectorAll('[data-time-id]').length % cores.length];
    
    $.ajax({
        url: '../ajax/criar_time_pontuacao.php',
        method: 'POST',
        data: {
            jogo_id: jogoAtualId,
            nome: nome,
            cor: cor
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarTimes(jogoAtualId);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao criar time', 'danger');
        }
    });
}

function editarTime(timeId, nomeAtual, corAtual) {
    const novoNome = prompt('Novo nome do time:', nomeAtual);
    if (!novoNome || novoNome === nomeAtual) return;
    
    $.ajax({
        url: '../ajax/editar_time_pontuacao.php',
        method: 'POST',
        data: {
            time_id: timeId,
            nome: novoNome
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarTimes(jogoAtualId);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao editar time', 'danger');
        }
    });
}

function excluirTime(timeId) {
    if (!confirm('Tem certeza que deseja excluir este time?')) return;
    
    $.ajax({
        url: '../ajax/excluir_time_pontuacao.php',
        method: 'POST',
        data: { time_id: timeId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarTimes(jogoAtualId);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao excluir time', 'danger');
        }
    });
}

function adicionarJogadorTime(timeId) {
    // Carregar participantes disponíveis
    $.ajax({
        url: '../ajax/listar_participantes_jogo.php',
        method: 'GET',
        data: { jogo_id: jogoAtualId, time_id: timeId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.participantes.length > 0) {
                let options = response.participantes.map(function(p) {
                    return '<option value="' + p.id + '">' + p.nome + '</option>';
                }).join('');
                
                const select = document.createElement('select');
                select.className = 'form-select mb-2';
                select.innerHTML = '<option value="">Selecione um jogador...</option>' + options;
                
                const div = document.createElement('div');
                div.className = 'd-flex gap-2';
                div.appendChild(select);
                
                const btnSalvar = document.createElement('button');
                btnSalvar.className = 'btn btn-sm btn-primary';
                btnSalvar.innerHTML = '<i class="fas fa-check"></i>';
                btnSalvar.onclick = function() {
                    const usuarioId = select.value;
                    if (!usuarioId) {
                        showAlert('Selecione um jogador', 'warning');
                        return;
                    }
                    adicionarJogadorAoTime(timeId, usuarioId);
                    div.remove();
                };
                div.appendChild(btnSalvar);
                
                const btnCancelar = document.createElement('button');
                btnCancelar.className = 'btn btn-sm btn-secondary';
                btnCancelar.innerHTML = '<i class="fas fa-times"></i>';
                btnCancelar.onclick = function() { div.remove(); };
                div.appendChild(btnCancelar);
                
                document.getElementById('jogadores-time-' + timeId).appendChild(div);
            } else {
                showAlert('Nenhum participante disponível', 'warning');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarJogadorAoTime(timeId, usuarioId) {
    $.ajax({
        url: '../ajax/adicionar_jogador_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            usuario_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarTimes(jogoAtualId);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao adicionar jogador', 'danger');
        }
    });
}

function removerJogadorTime(timeId, usuarioId) {
    if (!confirm('Remover este jogador do time?')) return;
    
    $.ajax({
        url: '../ajax/remover_jogador_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            usuario_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarTimes(jogoAtualId);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao remover jogador', 'danger');
        }
    });
}

function salvarPontos() {
    const pontos = {};
    document.querySelectorAll('.pontos-jogador').forEach(function(input) {
        const usuarioId = input.getAttribute('data-usuario-id');
        pontos[usuarioId] = parseFloat(input.value) || 0;
    });
    
    $.ajax({
        url: '../ajax/salvar_pontos_jogo.php',
        method: 'POST',
        data: {
            jogo_id: jogoAtualId,
            pontos: pontos
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Fechar modal usando Bootstrap 5
                var modalElement = document.getElementById('modalGerenciarPontos');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    } else {
                        // Se não existe instância, criar e fechar
                        var modal = new bootstrap.Modal(modalElement);
                        modal.hide();
                    }
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar pontos', 'danger');
        }
    });
}

function excluirJogo(jogoId) {
    if (confirm('Tem certeza que deseja excluir este jogo? Todos os pontos registrados serão perdidos.')) {
        $.ajax({
            url: '../ajax/excluir_jogo_pontuacao.php',
            method: 'POST',
            data: { jogo_id: jogoId },
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
                showAlert('Erro ao excluir jogo', 'danger');
            }
        });
    }
}

function desativarSistema(sistemaId) {
    if (confirm('Tem certeza que deseja desativar este sistema de pontuação?')) {
        $.ajax({
            url: '../ajax/desativar_sistema_pontuacao.php',
            method: 'POST',
            data: { sistema_id: sistemaId },
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
                showAlert('Erro ao desativar sistema', 'danger');
            }
        });
    }
}

function editarDataFinal() {
    const display = document.getElementById('dataFinalDisplay');
    const input = document.getElementById('dataFinalInput');
    const btn = document.getElementById('btnEditarDataFinal');
    
    display.classList.add('d-none');
    input.classList.remove('d-none');
    input.focus();
    btn.innerHTML = '<i class="fas fa-save"></i>';
    btn.onclick = salvarDataFinal;
    btn.classList.remove('btn-link');
    btn.classList.add('btn-success', 'btn-sm');
    
    // Salvar ao pressionar Enter
    input.onkeypress = function(e) {
        if (e.key === 'Enter') {
            salvarDataFinal();
        }
    };
}

function salvarDataFinal() {
    const input = document.getElementById('dataFinalInput');
    const novaData = input.value;
    const sistemaId = <?php echo (int)$sistema['id']; ?>;
    
    if (!novaData) {
        showAlert('Data inválida', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/editar_sistema_pontuacao.php',
        method: 'POST',
        data: {
            sistema_id: sistemaId,
            campo: 'data_final',
            valor: novaData
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Data final atualizada com sucesso!', 'success');
                // Atualizar display
                const dataFormatada = new Date(novaData + 'T00:00:00');
                const dia = String(dataFormatada.getDate()).padStart(2, '0');
                const mes = String(dataFormatada.getMonth() + 1).padStart(2, '0');
                const ano = dataFormatada.getFullYear();
                document.getElementById('dataFinalDisplay').textContent = dia + '/' + mes + '/' + ano;
                
                // Voltar ao modo visualização
                document.getElementById('dataFinalDisplay').classList.remove('d-none');
                input.classList.add('d-none');
                const btn = document.getElementById('btnEditarDataFinal');
                btn.innerHTML = '<i class="fas fa-edit"></i>';
                btn.onclick = editarDataFinal;
                btn.classList.remove('btn-success', 'btn-sm');
                btn.classList.add('btn-link');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao atualizar data final', 'danger');
        }
    });
}

function editarTotalJogos() {
    const display = document.getElementById('totalJogosDisplay');
    const input = document.getElementById('totalJogosInput');
    const btn = document.getElementById('btnEditarTotalJogos');
    
    display.classList.add('d-none');
    input.classList.remove('d-none');
    input.focus();
    input.select();
    btn.innerHTML = '<i class="fas fa-save"></i>';
    btn.onclick = salvarTotalJogos;
    btn.classList.remove('btn-link');
    btn.classList.add('btn-success', 'btn-sm');
    
    // Salvar ao pressionar Enter
    input.onkeypress = function(e) {
        if (e.key === 'Enter') {
            salvarTotalJogos();
        }
    };
}

function salvarTotalJogos() {
    const input = document.getElementById('totalJogosInput');
    const novoTotal = parseInt(input.value);
    const sistemaId = <?php echo (int)$sistema['id']; ?>;
    
    if (!novoTotal || novoTotal < 1) {
        showAlert('Total de jogos deve ser maior que zero', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/editar_sistema_pontuacao.php',
        method: 'POST',
        data: {
            sistema_id: sistemaId,
            campo: 'quantidade_jogos',
            valor: novoTotal
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Total de jogos atualizado com sucesso!', 'success');
                // Atualizar display
                document.getElementById('totalJogosDisplay').textContent = novoTotal;
                
                // Voltar ao modo visualização
                document.getElementById('totalJogosDisplay').classList.remove('d-none');
                input.classList.add('d-none');
                const btn = document.getElementById('btnEditarTotalJogos');
                btn.innerHTML = '<i class="fas fa-edit"></i>';
                btn.onclick = editarTotalJogos;
                btn.classList.remove('btn-success', 'btn-sm');
                btn.classList.add('btn-link');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao atualizar total de jogos', 'danger');
        }
    });
}

// Formulário criar sistema
$('#formCriarSistema').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: '../ajax/criar_sistema_pontuacao.php',
        method: 'POST',
        data: $(this).serialize(),
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
            showAlert('Erro ao criar sistema', 'danger');
        }
    });
});

// Formulário criar jogo
$('#formCriarJogo').on('submit', function(e) {
    e.preventDefault();
    
    // Validar se há participantes selecionados
    var participantes = $('input[name="participantes[]"]:checked');
    if (participantes.length === 0) {
        showAlert('Selecione pelo menos um participante para o jogo.', 'warning');
        return;
    }
    
    $.ajax({
        url: '../ajax/criar_jogo_pontuacao.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Fechar modal usando Bootstrap 5
                var modalElement = document.getElementById('modalCriarJogo');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) {
                        modalInstance.hide();
                    } else {
                        // Se não existe instância, criar e fechar
                        var modal = new bootstrap.Modal(modalElement);
                        modal.hide();
                    }
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao criar jogo', 'danger');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>

