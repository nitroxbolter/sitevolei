<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Classificação de Pontuação';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

// Verificar se é membro do grupo ou admin
$sql = "SELECT g.*, u.nome AS admin_nome FROM grupos g LEFT JOIN usuarios u ON u.id=g.administrador_id WHERE g.id=?";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupos.php');
    exit();
}

$usuario_id = (int)$_SESSION['user_id'];
$sou_admin_grupo = ((int)$grupo['administrador_id'] === $usuario_id);

// Verificar se é membro
$sql = "SELECT id FROM grupo_membros WHERE grupo_id = ? AND usuario_id = ? AND ativo = 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
$e_membro = $stmt ? ($stmt->fetch() !== false) : false;

if (!$e_membro && !$sou_admin_grupo && !isAdmin($pdo, $usuario_id)) {
    $_SESSION['mensagem'] = 'Você precisa ser membro do grupo para ver a classificação.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: grupo.php?id='.$grupo_id);
    exit();
}

// Buscar sistema de pontuação ativo
$sql = "SELECT * FROM sistemas_pontuacao WHERE grupo_id = ? AND ativo = 1 ORDER BY data_criacao DESC LIMIT 1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$sistema = $stmt ? $stmt->fetch() : false;

if (!$sistema) {
    $_SESSION['mensagem'] = 'Nenhum sistema de pontuação ativo encontrado para este grupo.';
    $_SESSION['tipo_mensagem'] = 'warning';
    header('Location: grupo.php?id='.$grupo_id);
    exit();
}

// Buscar classificação separada por gênero
// Primeiro, verificar se o campo genero existe na tabela e criar se não existir
try {
    $sql_check = "SHOW COLUMNS FROM usuarios LIKE 'genero'";
    $stmt_check = $pdo->query($sql_check);
    $genero_exists = $stmt_check && $stmt_check->rowCount() > 0;
    
    // Se o campo não existir, criar
    if (!$genero_exists) {
        try {
            $sql_add = "ALTER TABLE usuarios ADD COLUMN genero ENUM('Masculino', 'Feminino', 'M', 'F') DEFAULT NULL AFTER nivel";
            $pdo->exec($sql_add);
            $genero_exists = true;
        } catch (Exception $e) {
            // Campo pode já existir ou erro de permissão
            $genero_exists = false;
        }
    }
} catch (Exception $e) {
    $genero_exists = false;
}

// Buscar classificação masculina
if ($genero_exists) {
    $sql_masculino = "SELECT 
                u.id,
                u.nome,
                u.foto_perfil,
                COALESCE((
                    SELECT SUM(sp.pontos) 
                    FROM sistema_pontuacao_pontos sp
                    WHERE sp.usuario_id = u.id 
                    AND sp.jogo_id IN (SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ?)
                ), 0) AS total_pontos,
                COALESCE((
                    SELECT COUNT(DISTINCT spp.jogo_id)
                    FROM sistema_pontuacao_participantes spp
                    WHERE spp.usuario_id = u.id 
                    AND spp.jogo_id IN (SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ?)
                ), 0) AS jogos_participados
            FROM grupo_membros gm
            JOIN usuarios u ON u.id = gm.usuario_id
            WHERE gm.grupo_id = ? AND gm.ativo = 1 
                AND (u.genero = 'Masculino' OR u.genero = 'M' OR u.genero LIKE '%Masculino%')
            ORDER BY total_pontos DESC, u.nome";
    $stmt_masculino = executeQuery($pdo, $sql_masculino, [$sistema['id'], $sistema['id'], $grupo_id]);
    $classificacao_masculino = $stmt_masculino ? $stmt_masculino->fetchAll() : [];
} else {
    $classificacao_masculino = [];
}

// Buscar classificação feminina
if ($genero_exists) {
    $sql_feminino = "SELECT 
                u.id,
                u.nome,
                u.foto_perfil,
                COALESCE((
                    SELECT SUM(sp.pontos) 
                    FROM sistema_pontuacao_pontos sp
                    WHERE sp.usuario_id = u.id 
                    AND sp.jogo_id IN (SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ?)
                ), 0) AS total_pontos,
                COALESCE((
                    SELECT COUNT(DISTINCT spp.jogo_id)
                    FROM sistema_pontuacao_participantes spp
                    WHERE spp.usuario_id = u.id 
                    AND spp.jogo_id IN (SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ?)
                ), 0) AS jogos_participados
            FROM grupo_membros gm
            JOIN usuarios u ON u.id = gm.usuario_id
            WHERE gm.grupo_id = ? AND gm.ativo = 1 
                AND (u.genero = 'Feminino' OR u.genero = 'F' OR u.genero LIKE '%Feminino%')
            ORDER BY total_pontos DESC, u.nome";
    $stmt_feminino = executeQuery($pdo, $sql_feminino, [$sistema['id'], $sistema['id'], $grupo_id]);
    $classificacao_feminino = $stmt_feminino ? $stmt_feminino->fetchAll() : [];
} else {
    $classificacao_feminino = [];
}

// Buscar jogos do sistema
$sql = "SELECT * FROM sistema_pontuacao_jogos WHERE sistema_id = ? ORDER BY numero_jogo";
$stmt = executeQuery($pdo, $sql, [$sistema['id']]);
$jogos = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i>Classificação: <?php echo htmlspecialchars($sistema['nome']); ?>
        </h2>
        <div class="d-flex gap-2">
            <?php if ($sou_admin_grupo): ?>
                <a href="admin/sistema_pontuacao.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-warning">
                    <i class="fas fa-cog me-1"></i>Gerenciar Sistema
                </a>
            <?php endif; ?>
            <a href="grupo.php?id=<?php echo $grupo_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar ao Grupo
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Sistema</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Data Inicial:</strong><br>
                        <?php echo date('d/m/Y', strtotime($sistema['data_inicial'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Data Final:</strong><br>
                        <?php echo date('d/m/Y', strtotime($sistema['data_final'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total de Jogos:</strong><br>
                        <?php echo (int)$sistema['quantidade_jogos']; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Jogos Realizados:</strong><br>
                        <?php echo count($jogos); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Função auxiliar para renderizar tabela de classificação -->
<?php
function renderizarTabelaClassificacao($classificacao, $titulo, $genero) {
    if (empty($classificacao)): ?>
        <div class="text-center text-muted py-4">
            <i class="fas fa-trophy fa-3x mb-3"></i>
            <p>Nenhuma pontuação registrada ainda para <?php echo $genero; ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 60px;">Pos.</th>
                        <th>Jogador</th>
                        <th class="text-center">Jogos</th>
                        <th class="text-end">Pontos Totais</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $posicao = 1;
                    $pontosAnterior = null;
                    foreach ($classificacao as $index => $jogador): 
                        $totalPontos = (float)$jogador['total_pontos'];
                        // Se os pontos são iguais ao anterior, mantém a mesma posição
                        if ($pontosAnterior !== null && $totalPontos < $pontosAnterior) {
                            $posicao = $index + 1;
                        }
                        $pontosAnterior = $totalPontos;
                        
                        // Cores para top 3
                        $badgeClass = '';
                        if ($posicao === 1) {
                            $badgeClass = 'bg-warning text-dark';
                        } elseif ($posicao === 2) {
                            $badgeClass = 'bg-secondary';
                        } elseif ($posicao === 3) {
                            $badgeClass = 'bg-danger';
                        }
                    ?>
                        <tr>
                            <td>
                                <?php if ($posicao <= 3): ?>
                                    <span class="badge <?php echo $badgeClass; ?> fs-6"><?php echo $posicao; ?>º</span>
                                <?php else: ?>
                                    <strong><?php echo $posicao; ?>º</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php 
                                    // Processar caminho da foto de perfil
                                    $avatar = $jogador['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                    if (!empty($jogador['foto_perfil'])) {
                                        if (strpos($jogador['foto_perfil'], 'http') === 0 || strpos($jogador['foto_perfil'], '/') === 0) {
                                            // URL absoluta ou caminho absoluto
                                            $avatar = $jogador['foto_perfil'];
                                        } elseif (strpos($jogador['foto_perfil'], '../../assets/') === 0 || strpos($jogador['foto_perfil'], '../assets/') === 0 || strpos($jogador['foto_perfil'], 'assets/') === 0) {
                                            // Já tem assets/, garantir que comece com ../../
                                            if (strpos($jogador['foto_perfil'], '../../') !== 0) {
                                                if (strpos($jogador['foto_perfil'], '../') === 0) {
                                                    $avatar = '../' . ltrim($jogador['foto_perfil'], '/');
                                                } else {
                                                    $avatar = '../../' . ltrim($jogador['foto_perfil'], '/');
                                                }
                                            } else {
                                                $avatar = $jogador['foto_perfil'];
                                            }
                                        } else {
                                            // Apenas nome do arquivo, adicionar caminho completo
                                            $avatar = '../../assets/arquivos/' . ltrim($jogador['foto_perfil'], '/');
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="40" height="40" style="object-fit:cover;" alt="Avatar">
                                    <strong><?php echo htmlspecialchars($jogador['nome']); ?></strong>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo (int)$jogador['jogos_participados']; ?></span>
                            </td>
                            <td class="text-end">
                                <strong class="fs-5"><?php 
                                    $pontosFormatados = $totalPontos == (int)$totalPontos 
                                        ? number_format($totalPontos, 0, ',', '.') 
                                        : number_format($totalPontos, 2, ',', '.');
                                    echo $pontosFormatados;
                                ?></strong>
                            </td>
                        </tr>
                    <?php 
                    $posicao++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif;
}
?>

<div class="row">
    <!-- Classificação Masculina -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-mars me-2"></i>Classificação Masculina
                </h5>
            </div>
            <div class="card-body">
                <?php renderizarTabelaClassificacao($classificacao_masculino, 'Masculina', 'Masculino'); ?>
            </div>
        </div>
    </div>
    
    <!-- Classificação Feminina -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-venus me-2"></i>Classificação Feminina
                </h5>
            </div>
            <div class="card-body">
                <?php renderizarTabelaClassificacao($classificacao_feminino, 'Feminina', 'Feminino'); ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($jogos)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Detalhes por Jogo</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="accordionJogos">
                    <?php foreach ($jogos as $index => $jogo): ?>
                        <?php
                        $jogoId = 'jogo_' . $jogo['id'];
                        $collapsed = $index > 0 ? 'collapsed' : '';
                        $show = $index === 0 ? 'show' : '';
                        
                        $sql = "SELECT u.id, u.nome, u.foto_perfil, COALESCE(sp.pontos, 0) AS pontos
                                FROM sistema_pontuacao_participantes spp
                                JOIN usuarios u ON u.id = spp.usuario_id
                                LEFT JOIN sistema_pontuacao_pontos sp ON sp.usuario_id = u.id AND sp.jogo_id = ?
                                WHERE spp.jogo_id = ?
                                ORDER BY sp.pontos DESC, u.nome";
                        $stmt = executeQuery($pdo, $sql, [$jogo['id'], $jogo['id']]);
                        $pontosJogo = $stmt ? $stmt->fetchAll() : [];
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $jogo['id']; ?>">
                                <button class="accordion-button <?php echo $collapsed; ?>" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#<?php echo $jogoId; ?>" 
                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                        aria-controls="<?php echo $jogoId; ?>">
                                    <i class="fas fa-volleyball-ball me-2"></i>
                                    <strong>Jogo #<?php echo (int)$jogo['numero_jogo']; ?> - <?php echo date('d/m/Y', strtotime($jogo['data_jogo'])); ?></strong>
                                    <?php if ($jogo['descricao']): ?>
                                        <small class="text-muted ms-2">(<?php echo htmlspecialchars($jogo['descricao']); ?>)</small>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="<?php echo $jogoId; ?>" class="accordion-collapse collapse <?php echo $show; ?>" 
                                 aria-labelledby="heading<?php echo $jogo['id']; ?>" 
                                 data-bs-parent="#accordionJogos">
                                <div class="accordion-body">
                                    <?php if (!empty($pontosJogo)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Jogador</th>
                                                        <th class="text-end">Pontos</th>
                                                        <?php if ($sou_admin_grupo || isAdmin($pdo, $usuario_id)): ?>
                                                            <th style="width: 120px;" class="text-center">Ações</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pontosJogo as $p): ?>
                                                        <tr id="row_jogador_<?php echo $jogo['id']; ?>_<?php echo $p['id']; ?>">
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php 
                                                                    // Corrigir caminho da foto de perfil
                                                                    if (!empty($p['foto_perfil'])) {
                                                                        if (strpos($p['foto_perfil'], 'http') === 0 || strpos($p['foto_perfil'], '/') === 0) {
                                                                            $avatar = $p['foto_perfil'];
                                                                        } elseif (strpos($p['foto_perfil'], '../../assets/') === 0 || strpos($p['foto_perfil'], '../assets/') === 0 || strpos($p['foto_perfil'], 'assets/') === 0) {
                                                                            if (strpos($p['foto_perfil'], '../../') !== 0) {
                                                                                if (strpos($p['foto_perfil'], '../') === 0) {
                                                                                    $avatar = '../' . $p['foto_perfil'];
                                                                                } else {
                                                                                    $avatar = '../../' . $p['foto_perfil'];
                                                                                }
                                                                            } else {
                                                                                $avatar = $p['foto_perfil'];
                                                                            }
                                                                        } else {
                                                                            $avatar = '../../assets/arquivos/' . $p['foto_perfil'];
                                                                        }
                                                                    } else {
                                                                        $avatar = '../../assets/arquivos/logo.png';
                                                                    }
                                                                    ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="28" height="28" style="object-fit:cover;">
                                                                    <?php echo htmlspecialchars($p['nome']); ?>
                                                                </div>
                                                            </td>
                                                            <td class="text-end">
                                                                <span id="pontos_display_<?php echo $jogo['id']; ?>_<?php echo $p['id']; ?>" class="pontos-display">
                                                                    <strong><?php 
                                                                        $pontos = (float)$p['pontos'];
                                                                        $pontosFormatados = $pontos == (int)$pontos 
                                                                            ? number_format($pontos, 0, ',', '.') 
                                                                            : number_format($pontos, 2, ',', '.');
                                                                        echo $pontosFormatados;
                                                                    ?></strong>
                                                                </span>
                                                                <span id="pontos_edit_<?php echo $jogo['id']; ?>_<?php echo $p['id']; ?>" class="pontos-edit" style="display: none;">
                                                                    <div class="input-group input-group-sm" style="width: 120px; margin-left: auto;">
                                                                        <input type="number" 
                                                                               class="form-control form-control-sm" 
                                                                               id="input_pontos_<?php echo $jogo['id']; ?>_<?php echo $p['id']; ?>" 
                                                                               value="<?php echo $pontos; ?>" 
                                                                               step="0.01" 
                                                                               min="0"
                                                                               style="text-align: right;">
                                                                        <button class="btn btn-success btn-sm" 
                                                                                type="button" 
                                                                                onclick="salvarPontosJogador(<?php echo $jogo['id']; ?>, <?php echo $p['id']; ?>)"
                                                                                title="Salvar">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <button class="btn btn-secondary btn-sm" 
                                                                                type="button" 
                                                                                onclick="cancelarEdicaoPontos(<?php echo $jogo['id']; ?>, <?php echo $p['id']; ?>)"
                                                                                title="Cancelar">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                </span>
                                                            </td>
                                                            <?php if ($sou_admin_grupo || isAdmin($pdo, $usuario_id)): ?>
                                                                <td class="text-center">
                                                                    <div class="btn-group btn-group-sm" role="group">
                                                                        <button type="button" 
                                                                                class="btn btn-primary btn-sm" 
                                                                                onclick="editarPontosJogador(<?php echo $jogo['id']; ?>, <?php echo $p['id']; ?>, <?php echo $pontos; ?>)"
                                                                                title="Editar Pontos">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <button type="button" 
                                                                                class="btn btn-danger btn-sm" 
                                                                                onclick="removerParticipanteJogo(<?php echo $jogo['id']; ?>, <?php echo $p['id']; ?>)"
                                                                                title="Remover Participante">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0"><small>Nenhum ponto registrado para este jogo.</small></p>
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

<?php if ($sou_admin_grupo || isAdmin($pdo, $usuario_id)): ?>
<script>
// Função para editar pontos de um jogador
function editarPontosJogador(jogoId, usuarioId, pontosAtuais) {
    // Esconder display e mostrar input
    document.getElementById('pontos_display_' + jogoId + '_' + usuarioId).style.display = 'none';
    document.getElementById('pontos_edit_' + jogoId + '_' + usuarioId).style.display = 'block';
    
    // Focar no input
    const input = document.getElementById('input_pontos_' + jogoId + '_' + usuarioId);
    input.focus();
    input.select();
    
    // Adicionar listener para Enter
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            salvarPontosJogador(jogoId, usuarioId);
        } else if (e.key === 'Escape') {
            cancelarEdicaoPontos(jogoId, usuarioId);
        }
    });
}

// Função para cancelar edição de pontos
function cancelarEdicaoPontos(jogoId, usuarioId) {
    // Mostrar display e esconder input
    document.getElementById('pontos_display_' + jogoId + '_' + usuarioId).style.display = '';
    document.getElementById('pontos_edit_' + jogoId + '_' + usuarioId).style.display = 'none';
}

// Função para salvar pontos de um jogador
function salvarPontosJogador(jogoId, usuarioId) {
    const input = document.getElementById('input_pontos_' + jogoId + '_' + usuarioId);
    const pontos = parseFloat(input.value) || 0;
    
    if (pontos < 0) {
        alert('Pontos não podem ser negativos.');
        return;
    }
    
    // Desabilitar botões durante o salvamento
    const btnSalvar = input.parentElement.querySelector('.btn-success');
    const btnCancelar = input.parentElement.querySelector('.btn-secondary');
    btnSalvar.disabled = true;
    btnCancelar.disabled = true;
    
    $.ajax({
        url: '../ajax/atualizar_pontos_jogador.php',
        method: 'POST',
        data: {
            jogo_id: jogoId,
            usuario_id: usuarioId,
            pontos: pontos
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Esconder input e mostrar display
                cancelarEdicaoPontos(jogoId, usuarioId);
                
                // Recarregar página para atualizar classificação geral
                setTimeout(function() {
                    location.reload();
                }, 300);
            } else {
                alert(response.message || 'Erro ao salvar pontos.');
                btnSalvar.disabled = false;
                btnCancelar.disabled = false;
            }
        },
        error: function() {
            alert('Erro ao salvar pontos.');
            btnSalvar.disabled = false;
            btnCancelar.disabled = false;
        }
    });
}

// Função para remover participante do jogo
function removerParticipanteJogo(jogoId, usuarioId) {
    if (!confirm('Tem certeza que deseja remover este participante do jogo? Os pontos registrados também serão removidos.')) {
        return;
    }
    
    $.ajax({
        url: '../jogos/ajax/remover_participante_jogo_pontuacao.php',
        method: 'POST',
        data: {
            jogo_id: jogoId,
            usuario_id: usuarioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Remover linha da tabela
                const row = document.getElementById('row_jogador_' + jogoId + '_' + usuarioId);
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() {
                        row.remove();
                        // Recarregar página para atualizar classificação geral
                        location.reload();
                    }, 300);
                } else {
                    location.reload();
                }
            } else {
                alert(response.message || 'Erro ao remover participante.');
            }
        },
        error: function() {
            alert('Erro ao remover participante.');
        }
    });
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

