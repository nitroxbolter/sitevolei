<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Torneio';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

$torneio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($torneio_id <= 0) {
    $_SESSION['mensagem'] = 'Torneio inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Carregar torneio
$sql = "SELECT t.*, g.nome AS grupo_nome, u.nome AS criado_por_nome
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        LEFT JOIN usuarios u ON u.id = t.criado_por
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;

// Verificar se a coluna inscricoes_abertas existe e obter o valor
$inscricoes_abertas = 0;
try {
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'inscricoes_abertas'");
    $coluna_existe = $columnsQuery && $columnsQuery->rowCount() > 0;
    if ($coluna_existe && isset($torneio['inscricoes_abertas'])) {
        $inscricoes_abertas = (int)$torneio['inscricoes_abertas'];
    }
} catch (Exception $e) {
    // Coluna não existe ainda
    $inscricoes_abertas = 0;
}

if (!$torneio) {
    $_SESSION['mensagem'] = 'Torneio não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Verificar permissão
$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin_grupo = false;
if ($torneio['grupo_id']) {
    $sql = "SELECT administrador_id FROM grupos WHERE id = ?";
    $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
    $grupo = $stmt ? $stmt->fetch() : false;
    $sou_admin_grupo = $grupo && ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
}

if (!$sou_criador && !$sou_admin_grupo && !isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['mensagem'] = 'Você não tem permissão para gerenciar este torneio.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Buscar participantes com informações do usuário
// Verificar quais colunas existem
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
$columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
$tem_ordem = in_array('ordem', $columns);
$tem_nome_avulso = in_array('nome_avulso', $columns);

// Debug
error_log("Colunas torneio_participantes: " . implode(', ', $columns));
error_log("Buscando participantes do torneio ID: " . $torneio_id);

// Query básica - funciona mesmo sem nome_avulso
$sql = "SELECT tp.id, tp.torneio_id, tp.usuario_id, tp.data_inscricao";
if ($tem_nome_avulso) {
    $sql .= ", tp.nome_avulso";
}
if ($tem_ordem) {
    $sql .= ", tp.ordem";
}
$sql .= ", u.nome AS usuario_nome, u.foto_perfil AS usuario_foto
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id
        WHERE tp.torneio_id = ?";

// Adicionar ORDER BY baseado nas colunas disponíveis
if ($tem_ordem && $tem_nome_avulso) {
    $sql .= " ORDER BY tp.ordem, tp.nome_avulso, u.nome";
} elseif ($tem_ordem) {
    $sql .= " ORDER BY tp.ordem, u.nome";
} elseif ($tem_nome_avulso) {
    $sql .= " ORDER BY tp.nome_avulso, u.nome";
} else {
    $sql .= " ORDER BY u.nome, tp.id";
}

try {
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    $participantes = $stmt ? $stmt->fetchAll() : [];
    
    // Debug
    error_log("SQL executado: " . $sql);
    error_log("Total de participantes encontrados: " . count($participantes));
    if (count($participantes) > 0) {
        error_log("Primeiro participante: " . print_r($participantes[0], true));
    } else {
        // Verificar se há participantes na tabela
        $sql_check = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id]);
        $check_result = $stmt_check ? $stmt_check->fetch() : false;
        error_log("Total de participantes na tabela para este torneio: " . ($check_result['total'] ?? 0));
    }
} catch (Exception $e) {
    error_log("Erro ao buscar participantes: " . $e->getMessage());
    $participantes = [];
}

// Buscar membros do grupo (se for torneio do grupo)
$membros_grupo = [];
$tipoTorneio = $torneio['tipo'] ?? ($torneio['grupo_id'] ? 'grupo' : 'avulso');
if ($tipoTorneio === 'grupo' && $torneio['grupo_id']) {
    $sql = "SELECT u.id, u.nome, u.foto_perfil
            FROM grupo_membros gm
            JOIN usuarios u ON u.id = gm.usuario_id
            WHERE gm.grupo_id = ? AND gm.ativo = 1
            ORDER BY u.nome";
    $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
    $membros_grupo = $stmt ? $stmt->fetchAll() : [];
}

// Buscar times ordenados por ordem - usar DISTINCT para evitar duplicatas na query
$sql = "SELECT DISTINCT id, torneio_id, nome, cor, ordem, data_criacao FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Remover duplicatas baseado em ID (manter apenas um de cada ID)
$times_unicos = [];
$ids_vistos = [];
foreach ($times_raw as $time) {
    $id = (int)($time['id'] ?? 0);
    
    if ($id > 0 && !in_array($id, $ids_vistos)) {
        $times_unicos[] = $time;
        $ids_vistos[] = $id;
    }
}
$times = $times_unicos;

// Contar quantos times existem no banco de dados
$quantidade_times_db = count($times);

// Buscar integrantes dos times
foreach ($times as &$time) {
    $sql = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
            FROM torneio_time_integrantes tti
            JOIN torneio_participantes tp ON tp.id = tti.participante_id
            LEFT JOIN usuarios u ON u.id = tp.usuario_id
            WHERE tti.time_id = ?";
    
    // Verificar se a coluna 'ordem' existe
    if (!isset($tem_ordem)) {
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
        $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
        $tem_ordem = in_array('ordem', $columns);
    }
    
    if ($tem_ordem) {
        $sql .= " ORDER BY tp.ordem";
    } else {
        $sql .= " ORDER BY tp.nome_avulso, u.nome";
    }
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
}
unset($time); // Importante: remover referência após o loop

// Inicializar variável $pode_encerrar (será definida mais tarde quando necessário)
$pode_encerrar = false;

include '../../includes/header.php';
?>

<style>
/* Remover spinners dos campos de número */
.pontos-input[type="number"]::-webkit-inner-spin-button,
.pontos-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pontos-input[type="number"] {
    -moz-appearance: textfield;
}

/* Remover setas (spinners) de input number para chaves */
.pontos-chave-input[type="number"]::-webkit-inner-spin-button,
.pontos-chave-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pontos-chave-input[type="number"] {
    -moz-appearance: textfield;
}
</style>

<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<div class="row mb-3" style="margin-top: 10px;">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i>Gerenciar Torneio: <?php echo htmlspecialchars($torneio['nome']); ?>
        </h2>
        <div>
            <a href="../torneios.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
</div>

<!-- Informações do Torneio -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoInformacoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoInformacoes"></i>
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Torneio</h5>
                </div>
                <?php if ($sou_criador): ?>
                    <div class="d-flex gap-2">
                        <?php if ($torneio['status'] !== 'Finalizado' && $pode_encerrar): ?>
                            <button class="btn btn-sm btn-danger" onclick="encerrarTorneio()" id="btnEncerrarTorneio">
                                <i class="fas fa-flag-checkered me-1"></i>Encerrar Torneio
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" onclick="abrirModalEditarTorneio()">
                            <i class="fas fa-edit me-1"></i>Editar
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body" id="corpoSecaoInformacoes">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Data:</strong><br>
                        <?php 
                        $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                        echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'N/A';
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Tipo:</strong><br>
                        <?php 
                        if (isset($torneio['tipo'])) {
                            echo $torneio['tipo'] === 'grupo' ? 'Torneio do Grupo' : 'Torneio Avulso';
                        } else {
                            echo $torneio['grupo_id'] ? 'Torneio do Grupo' : 'Torneio Avulso';
                        }
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Grupo:</strong><br>
                        <?php echo $torneio['grupo_nome'] ? htmlspecialchars($torneio['grupo_nome']) : 'N/A'; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Participantes:</strong><br>
                        <?php 
                        // Calcular quantidade máxima baseado em times × integrantes
                        $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                        $integrantesPorTime = (int)($torneio['integrantes_por_time'] ?? 0);
                        $maxParticipantesCalculado = ($quantidadeTimes > 0 && $integrantesPorTime > 0) ? ($quantidadeTimes * $integrantesPorTime) : 0;
                        $maxParticipantes = $maxParticipantesCalculado > 0 ? $maxParticipantesCalculado : ($torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0);
                        echo count($participantes); ?> / <?php echo (int)$maxParticipantes; 
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        <?php 
                        $status_class = '';
                        $status_icon = '';
                        switch($torneio['status']) {
                            case 'Finalizado':
                                $status_class = 'success';
                                $status_icon = 'fa-flag-checkered';
                                break;
                            case 'Em Andamento':
                                $status_class = 'warning';
                                $status_icon = 'fa-play-circle';
                                break;
                            case 'Inscrições Abertas':
                                $status_class = 'info';
                                $status_icon = 'fa-user-plus';
                                break;
                            default:
                                $status_class = 'secondary';
                                $status_icon = 'fa-clock';
                        }
                        ?>
                        <span class="badge bg-<?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($torneio['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configurações do Torneio -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoConfiguracoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoConfiguracoes"></i>
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Configurações do Torneio</h5>
                </div>
                <?php 
                $configSalva = ($torneio['quantidade_times'] ?? 0) > 0 && ($torneio['integrantes_por_time'] ?? 0) > 0;
                if ($configSalva): ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editarConfiguracoes()" id="btnEditarConfig">
                        <i class="fas fa-edit me-1"></i>Editar
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="corpoSecaoConfiguracoes">
                <form id="formConfigTorneio">
                    <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="max_participantes" class="form-label">Quantidade Máxima de Participantes</label>
                            <?php 
                            // Calcular valor inicial baseado em times × integrantes se já estiver configurado
                            $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                            $integrantesPorTime = (int)($torneio['integrantes_por_time'] ?? 0);
                            $maxParticipantesCalculado = ($quantidadeTimes > 0 && $integrantesPorTime > 0) ? ($quantidadeTimes * $integrantesPorTime) : 0;
                            $maxParticipantesInicial = $maxParticipantesCalculado > 0 ? $maxParticipantesCalculado : (int)($torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0);
                            ?>
                            <input type="number" class="form-control config-field" id="max_participantes" name="max_participantes" 
                                   min="1" value="<?php echo $maxParticipantesInicial; ?>"
                                   readonly style="background-color: #e9ecef; cursor: not-allowed;">
                            <small class="text-muted">Atual: <?php echo count($participantes); ?> participantes</small>
                            <small class="text-info d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>Calculado automaticamente (Times × Integrantes)
                            </small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="tipo_time" class="form-label">Tipo de Time</label>
                            <?php 
                            $integrantesAtual = (int)($torneio['integrantes_por_time'] ?? 0);
                            $tipoAtual = '';
                            if ($integrantesAtual === 2) $tipoAtual = 'dupla';
                            elseif ($integrantesAtual === 3) $tipoAtual = 'trio';
                            elseif ($integrantesAtual === 4) $tipoAtual = 'quarteto';
                            elseif ($integrantesAtual === 5) $tipoAtual = 'quinteto';
                            ?>
                            <select class="form-control config-field" id="tipo_time" name="tipo_time" onchange="calcularParticipantesNecessarios()" <?php echo $configSalva ? 'disabled' : ''; ?>>
                                <option value="">Selecione...</option>
                                <option value="dupla" data-integrantes="2" <?php echo $tipoAtual === 'dupla' ? 'selected' : ''; ?>>Dupla (2 pessoas)</option>
                                <option value="trio" data-integrantes="3" <?php echo $tipoAtual === 'trio' ? 'selected' : ''; ?>>Trio (3 pessoas)</option>
                                <option value="quarteto" data-integrantes="4" <?php echo $tipoAtual === 'quarteto' ? 'selected' : ''; ?>>Quarteto (4 pessoas)</option>
                                <option value="quinteto" data-integrantes="5" <?php echo $tipoAtual === 'quinteto' ? 'selected' : ''; ?>>Quinteto (5 pessoas)</option>
                            </select>
                            <input type="hidden" id="integrantes_por_time" name="integrantes_por_time" value="<?php echo $integrantesAtual; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantidade_times" class="form-label">Quantidade de Times</label>
                            <input type="number" class="form-control config-field" id="quantidade_times" name="quantidade_times" 
                                   min="2" value="<?php echo (int)($torneio['quantidade_times'] ?? 0); ?>"
                                   onchange="calcularParticipantesNecessarios()" oninput="calcularParticipantesNecessarios()"
                                   <?php echo $configSalva ? 'disabled' : ''; ?>>
                            <small class="text-muted" id="info_participantes_necessarios" style="display: none;">
                                <span id="participantes_necessarios">0</span> participantes necessários
                            </small>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" id="btnSalvarConfig" <?php echo $configSalva ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-save me-1"></i>Salvar Configurações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Participantes -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleListaParticipantes()">
                    <i class="fas fa-chevron-right" id="iconeParticipantes"></i>
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Participantes
                        <span class="badge bg-secondary ms-2"><?php echo count($participantes); ?></span>
                    </h5>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if (!empty($participantes)): ?>
                        <span class="text-muted small me-2"><?php echo count($participantes); ?> participante(s)</span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary" onclick="abrirModalAdicionarParticipante()" title="Adicionar Participante">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm <?php echo $inscricoes_abertas ? 'btn-success' : 'btn-outline-success'; ?>" 
                            onclick="toggleInscricoes()" 
                            id="btnToggleInscricoes"
                            title="<?php echo $inscricoes_abertas ? 'Inscrições Abertas - Clique para fechar' : 'Inscrições Fechadas - Clique para abrir'; ?>">
                        <i class="fas <?php echo $inscricoes_abertas ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                    </button>
                    <?php if (!empty($participantes)): ?>
                        <button class="btn btn-sm btn-danger" onclick="limparTodosParticipantes()" title="Remover todos os participantes">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="corpoListaParticipantes" style="display: none;">
                <div id="listaParticipantes">
                    <?php 
                    // Debug
                    error_log("DEBUG: Total participantes no PHP: " . count($participantes));
                    if (empty($participantes)): 
                    ?>
                        <p class="text-muted mb-0">Nenhum participante adicionado ainda.</p>
                        
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($participantes as $p): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php 
                                        // Verificar se é usuário ou participante avulso
                                        $temUsuario = isset($p['usuario_id']) && !empty($p['usuario_id']) && (int)$p['usuario_id'] > 0;
                                        $temNomeAvulso = isset($p['nome_avulso']) && !empty($p['nome_avulso']);
                                        
                                        // Debug
                                        error_log("Participante ID: " . $p['id'] . ", usuario_id: " . ($p['usuario_id'] ?? 'NULL') . ", nome_avulso: " . ($p['nome_avulso'] ?? 'NULL'));
                                        
                                        if ($temUsuario): 
                                            $avatar = $p['usuario_foto'] ?: '../../assets/arquivos/logo.png';
                                            // Corrigir caminho da foto de perfil
                                            if (!empty($avatar)) {
                                                // Se já começa com http ou /, usar como está
                                                if (strpos($avatar, 'http') === 0 || strpos(trim($avatar), '/') === 0) {
                                                    // Já é um caminho absoluto ou começa com /
                                                } elseif (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                    // Se já tem assets/, garantir que comece com ../../
                                                    if (strpos($avatar, '../../') !== 0) {
                                                        if (strpos($avatar, '../') === 0) {
                                                        $avatar = '../' . ltrim($avatar, '/');
                                                        } else {
                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                        }
                                                    }
                                                } else {
                                                    // Se não tem caminho, adicionar caminho completo
                                                    $avatar = '../../assets/arquivos/' . ltrim($avatar, '/');
                                                }
                                            } else {
                                                $avatar = '../../assets/arquivos/logo.png';
                                            }
                                            $nome = isset($p['usuario_nome']) && !empty($p['usuario_nome']) ? $p['usuario_nome'] : 'Usuário não encontrado';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="24" height="24" style="object-fit:cover;">
                                            <span><?php echo htmlspecialchars($nome); ?></span>
                                        <?php elseif ($temNomeAvulso): ?>
                                            <i class="fas fa-user"></i>
                                            <span><?php echo htmlspecialchars($p['nome_avulso']); ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                            <span>
                                                <?php 
                                                if ($temNomeAvulso) {
                                                    echo htmlspecialchars($p['nome_avulso']);
                                                } else {
                                                    echo 'Participante #' . $p['id'];
                                                    if (isset($p['usuario_id'])) {
                                                        echo ' (Usuario ID: ' . $p['usuario_id'] . ')';
                                                    }
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="removerParticipante(<?php echo $p['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Solicitações de Participação -->
    <?php 
    // Verificar se há solicitações pendentes, mesmo que inscrições estejam fechadas
    $sql_check_solicitacoes = "SELECT COUNT(*) AS total FROM torneio_solicitacoes WHERE torneio_id = ? AND status = 'Pendente'";
    $stmt_check_solicitacoes = executeQuery($pdo, $sql_check_solicitacoes, [$torneio_id]);
    $tem_solicitacoes_pendentes = false;
    if ($stmt_check_solicitacoes) {
        $result = $stmt_check_solicitacoes->fetch();
        $tem_solicitacoes_pendentes = ($result && (int)$result['total'] > 0);
    }
    // Mostrar seção se inscrições estão abertas OU se há solicitações pendentes
    if ($inscricoes_abertas || $tem_solicitacoes_pendentes): 
    ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>Solicitações de Participação
                    <span class="badge bg-warning ms-2" id="badgeSolicitacoes">0</span>
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="carregarSolicitacoes()" title="Atualizar">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="listaSolicitacoes">
                    <p class="text-muted text-center mb-0">
                        <i class="fas fa-spinner fa-spin me-2"></i>Carregando solicitações...
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Times do Torneio -->
<?php 
$quantidadeTimes = $torneio['quantidade_times'] ?? 0;
// Usar a quantidade real do banco se houver times, senão usar a configurada
$quantidadeTimesExibicao = $quantidade_times_db > 0 ? $quantidade_times_db : $quantidadeTimes;

if ($quantidadeTimes > 0 || $quantidade_times_db > 0): 
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2 mb-2" style="cursor: pointer;" onclick="toggleSecaoTimes()">
                    <i class="fas fa-chevron-right" id="iconeSecaoTimes"></i>
                <div>
                    <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                    <small class="text-muted">
                        <?php if ($quantidade_times_db > 0): ?>
                            <?php echo $quantidade_times_db; ?> time(s) criado(s) no banco de dados
                            <?php if ($quantidade_times_db != $quantidadeTimes): ?>
                                <span class="badge bg-warning text-dark ms-2">Configuração: <?php echo $quantidadeTimes; ?> time(s)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Configuração: <?php echo $quantidadeTimes; ?> time(s) - Nenhum time criado ainda
                        <?php endif; ?>
                    </small>
                </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php 
                    $timesSalvos = $quantidade_times_db > 0;
                    if ($timesSalvos): ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editarTimes()" id="btnEditarTimes">
                            <i class="fas fa-edit me-1"></i>Editar
                        </button>
                    <?php endif; ?>
                    <?php if ($quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-warning times-action-btn" onclick="sortearTimes()" id="btnSortearTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-random me-1"></i>Sortear
                        </button>
                        <button class="btn btn-sm btn-success times-action-btn" onclick="salvarTimes()" id="btnSalvarTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-save me-1"></i>Salvar Times
                        </button>
                    <?php endif; ?>
                    <?php 
                    $integrantesPorTime = $torneio['integrantes_por_time'] ?? null;
                    if ($quantidadeTimes > 0 && !empty($participantes) && $integrantesPorTime): 
                    ?>
                        <button class="btn btn-sm btn-primary times-action-btn" onclick="criarTimes(this)" id="btnCriarTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-magic me-1"></i>Criar Times
                        </button>
                    <?php endif; ?>
                    <?php if ($quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-danger times-action-btn" onclick="limparTimes()" id="btnLimparTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash me-1"></i>Limpar Times
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($times) && $quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirTimes()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="corpoSecaoTimes" style="display: none;">
                <?php if ($quantidade_times_db == 0 && $quantidadeTimes > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum time foi criado ainda. Clique em "Criar Times" para criar <?php echo $quantidadeTimes; ?> time(s) baseado na configuração.
                    </div>
                <?php endif; ?>
                <div class="row" id="containerTimes">
                    <?php 
                    // APENAS usar times do banco de dados - não criar estrutura vazia
                    // Isso evita mostrar times "fantasma" quando não há nada no banco
                    $timesExistentes = [];
                    if (!empty($times)) {
                        // REMOVER DUPLICATAS POR ID antes de usar (segunda verificação de segurança)
                        $times_unicos_por_id = [];
                        $ids_ja_adicionados = [];
                        
                        foreach ($times as $time) {
                            $id = (int)($time['id'] ?? 0);
                            if ($id > 0 && !in_array($id, $ids_ja_adicionados)) {
                                $times_unicos_por_id[] = $time;
                                $ids_ja_adicionados[] = $id;
                            }
                        }
                        
                        $timesExistentes = $times_unicos_por_id;
                        
                        // Ordenar times por ordem antes de exibir
                        usort($timesExistentes, function($a, $b) {
                            $ordemA = isset($a['ordem']) ? (int)$a['ordem'] : 999;
                            $ordemB = isset($b['ordem']) ? (int)$b['ordem'] : 999;
                            if ($ordemA == $ordemB) {
                                $idA = isset($a['id']) ? (int)$a['id'] : 0;
                                $idB = isset($b['id']) ? (int)$b['id'] : 0;
                                return $idA - $idB;
                            }
                            return $ordemA - $ordemB;
                        });
                    }
                    ?>
                    <?php if (empty($timesExistentes)): ?>
                        <div class="col-12">
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                Nenhum time criado. Clique em "Criar Times" para criar os times baseado na configuração.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($timesExistentes as $idx => $time): ?>
                            <div class="col-md-6 col-lg-4 mb-3" data-time-id="<?php echo (int)($time['id'] ?? 0); ?>" data-time-ordem="<?php echo $time['ordem'] ?? 0; ?>">
                            <div class="card h-100" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome'] ?? 'Time sem nome'); ?></strong>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="adicionarParticipanteAoTime(<?php echo $time['id'] ?? 'null'; ?>, <?php echo $time['ordem']; ?>)"
                                                title="Adicionar Participante">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    <?php if ($time['id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="excluirTime(<?php echo $time['id']; ?>)" title="Excluir Time">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                                    <div id="time-<?php echo $time['id'] ?? 'novo_' . $time['ordem']; ?>" class="time-participantes" 
                                         data-time-numero="<?php echo $time['ordem']; ?>"
                                         style="min-height: 150px;">
                                        <?php if (!empty($time['integrantes'])): ?>
                                            <?php foreach ($time['integrantes'] as $integ): ?>
                                                <div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" 
                                                     data-participante-id="<?php echo $integ['participante_id']; ?>"
                                                     onclick="event.stopPropagation(); selecionarParticipante(this, event)"
                                                     style="cursor: pointer; user-select: none; -webkit-user-select: none;"
                                                     oncontextmenu="return false;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($integ['usuario_id']): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="24" height="24" style="object-fit:cover;">
                                                            <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                        <?php else: ?>
                                                            <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removerParticipanteDoTime(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Times (versão antiga - manter para compatibilidade) -->
<?php if (!empty($times) && $quantidadeTimes == 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                <div>
                    <?php if (!empty($times)): ?>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="imprimirTimes()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                <button class="btn btn-sm btn-warning" onclick="sortearTimes()">
                    <i class="fas fa-random me-1"></i>Sortear Times
                </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="tabela-times">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="adicionarIntegranteTime(<?php echo $time['id']; ?>)"
                                                title="Adicionar Integrante">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirTime(<?php echo $time['id']; ?>)" title="Excluir Time">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="integrantes-time-<?php echo $time['id']; ?>">
                                        <?php if (empty($time['integrantes'])): ?>
                                            <p class="text-muted mb-2"><small>Nenhum integrante</small></p>
                                        <?php else: ?>
                                            <?php foreach ($time['integrantes'] as $integ): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($integ['usuario_id']): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            // Corrigir caminho da foto de perfil
                                                            if (!empty($avatar)) {
                                                                // Se já começa com http ou /, usar como está
                                                                if (strpos($avatar, 'http') === 0 || strpos(trim($avatar), '/') === 0) {
                                                                    // Já é um caminho absoluto ou começa com /
                                                                } elseif (strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    // Se já tem assets/, garantir que comece com ../
                                                                    if (strpos($avatar, '../') !== 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    }
                                                                } else {
                                                                    // Se não tem caminho, adicionar caminho completo
                                                                    $avatar = '../assets/arquivos/' . ltrim($avatar, '/');
                                                                }
                                                            } else {
                                                                $avatar = '../assets/arquivos/logo.png';
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;">
                                                            <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                        <?php else: ?>
                                                            <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removerIntegrante(<?php echo $time['id']; ?>, <?php echo $integ['participante_id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
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

<!-- Formato de Campeonato -->
<?php 
$timesSalvos = $quantidade_times_db > 0;
if ($timesSalvos): 
    // Verificar se já tem modalidade configurada
    $modalidade = $torneio['modalidade'] ?? null;
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoFormato()">
                    <i class="fas fa-chevron-down" id="iconeSecaoFormato"></i>
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Formato de Campeonato</h5>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" form="formModalidadeTorneio" class="btn btn-sm btn-primary" id="btnSalvarModalidade">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" id="corpoSecaoFormato">
                <form id="formModalidadeTorneio">
                    <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Formato *</label>
                            <div class="form-check mb-2">
                                <?php 
                                // Se não pode criar chaves e estava selecionado todos_chaves, forçar todos_contra_todos
                                $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                                $podeCriarChaves = $quantidadeTimes > 0 && $quantidadeTimes % 2 === 0 && $quantidadeTimes >= 4;
                                if (!$podeCriarChaves && $modalidade === 'todos_chaves') {
                                    $modalidade = 'todos_contra_todos';
                                }
                                ?>
                                <input class="form-check-input" type="radio" name="modalidade" id="modalidade_todos_contra_todos" value="todos_contra_todos" <?php echo ($modalidade === 'todos_contra_todos' || (!$podeCriarChaves && $modalidade === 'todos_chaves')) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="modalidade_todos_contra_todos">
                                    <strong>Todos contra Todos</strong>
                                    <p class="text-muted small mb-0">Classificação por pontuação. Em caso de empate de vitórias, será considerado o average (diferença de pontos).</p>
                                </label>
                            </div>
                            <div class="form-check">
                                <?php 
                                $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                                $podeCriarChaves = $quantidadeTimes > 0 && $quantidadeTimes % 2 === 0 && $quantidadeTimes >= 4;
                                $disabledChaves = !$podeCriarChaves ? 'disabled' : '';
                                // Se não pode criar chaves mas estava selecionado, desmarcar e forçar todos_contra_todos
                                $checkedChaves = ($modalidade === 'todos_chaves' && $podeCriarChaves) ? 'checked' : '';
                                if (!$podeCriarChaves && $modalidade === 'todos_chaves') {
                                    // Forçar todos_contra_todos se não pode criar chaves
                                    $checkedChaves = '';
                                    $modalidade = 'todos_contra_todos'; // Atualizar para garantir que o outro radio fique marcado
                                }
                                ?>
                                <input class="form-check-input" type="radio" name="modalidade" id="modalidade_todos_chaves" value="todos_chaves" <?php echo $checkedChaves; ?> <?php echo $disabledChaves; ?> onchange="toggleQuantidadeGrupos()" style="<?php echo !$podeCriarChaves ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>">
                                <label class="form-check-label <?php echo !$podeCriarChaves ? 'text-danger' : ''; ?>" for="modalidade_todos_chaves" style="<?php echo !$podeCriarChaves ? 'cursor: not-allowed;' : ''; ?>">
                                    <strong>Todos contra Todos + Chaves</strong>
                                    <p class="text-muted small mb-0">Os times serão divididos em chaves. Dentro de cada chave, todos se enfrentam. Os melhores de cada chave avançam para as chaves eliminatórias.</p>
                                </label>
                            </div>
                            <?php if (!$podeCriarChaves && $quantidadeTimes > 0): ?>
                                <div class="alert alert-danger mt-2 mb-0" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Atenção:</strong> A quantidade de times (<?php echo $quantidadeTimes; ?>) não é suficiente para gerar chaves. É necessário um número par de times (mínimo 4) para criar chaves.
                                </div>
                            <?php endif; ?>
                            <div id="divQuantidadeGrupos" style="display: <?php echo ($modalidade === 'todos_chaves' && $podeCriarChaves) ? 'block' : 'none'; ?>;" class="mt-3">
                                <label class="form-label">Selecione a quantidade de chaves *</label>
                                <div id="opcoesChaves">
                                    <?php
                                    if ($podeCriarChaves) {
                                        // Calcular divisores válidos (que resultam em pelo menos 2 times por chave)
                                        $divisores = [];
                                        for ($i = 2; $i <= $quantidadeTimes / 2; $i++) {
                                            if ($quantidadeTimes % $i === 0) {
                                                $timesPorChave = $quantidadeTimes / $i;
                                                if ($timesPorChave >= 2) {
                                                    $divisores[] = $i;
                                                }
                                            }
                                        }
                                        
                                        // Adicionar opção de 1 chave (todos contra todos sem divisão)
                                        // Mas na verdade, isso seria igual a todos_contra_todos, então não vamos incluir
                                        
                                        if (empty($divisores)) {
                                            echo '<p class="text-muted">Nenhuma opção de chaves disponível para ' . $quantidadeTimes . ' times.</p>';
                                        } else {
                                            $quantidadeGruposAtual = (int)($torneio['quantidade_grupos'] ?? 0);
                                            foreach ($divisores as $divisor) {
                                                $timesPorChave = $quantidadeTimes / $divisor;
                                                $checked = ($quantidadeGruposAtual === $divisor) ? 'checked' : '';
                                                echo '<div class="form-check mb-2">';
                                                echo '<input class="form-check-input" type="radio" name="quantidade_grupos" id="chaves_' . $divisor . '" value="' . $divisor . '" ' . $checked . ' required>';
                                                echo '<label class="form-check-label" for="chaves_' . $divisor . '">';
                                                echo '<strong>' . $divisor . ' chaves</strong> - ' . $timesPorChave . ' time(s) em cada chave';
                                                echo '</label>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <small class="text-muted d-block mt-2">Os times serão divididos igualmente entre as chaves selecionadas.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Jogos de Enfrentamento -->
<?php 
if ($timesSalvos && $modalidade): 
    // Buscar partidas do torneio
    // Primeiro verificar se há partidas no banco para este torneio
    $sql_check_partidas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
    $stmt_check = executeQuery($pdo, $sql_check_partidas, [$torneio_id]);
    $total_partidas_db = $stmt_check ? (int)$stmt_check->fetch()['total'] : 0;
    
    $partidas = [];
    if ($total_partidas_db > 0) {
    $sql_partidas = "SELECT tp.*, 
                            t1.nome AS time1_nome, t1.cor AS time1_cor,
                            t2.nome AS time2_nome, t2.cor AS time2_cor,
                                tv.nome AS vencedor_nome, tp.vencedor_id,
                            tg.nome AS grupo_nome, tg.id AS grupo_id
                     FROM torneio_partidas tp
                         LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                     LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                     LEFT JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                     WHERE tp.torneio_id = ? AND tp.fase = 'Grupos'
                     ORDER BY COALESCE(tp.grupo_id, 0) ASC, tp.rodada ASC, tp.id ASC";
    
    // Verificar se a coluna grupo_id existe (para compatibilidade)
    static $grupo_id_exists = null;
    if ($grupo_id_exists === null) {
        try {
            $check_col = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
            $grupo_id_exists = $check_col && $check_col->rowCount() > 0;
        } catch (Exception $e) {
            $grupo_id_exists = false;
        }
    }
    
    if (!$grupo_id_exists) {
        // Se não existe, remover referência a grupo_id na query
        $sql_partidas = "SELECT tp.*, 
                                t1.nome AS time1_nome, t1.cor AS time1_cor,
                                t2.nome AS time2_nome, t2.cor AS time2_cor,
                                tv.nome AS vencedor_nome,
                                NULL AS grupo_nome, NULL AS grupo_id
                         FROM torneio_partidas tp
                         LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                         WHERE tp.torneio_id = ? AND tp.fase = 'Grupos'
                         ORDER BY tp.rodada ASC, tp.id ASC";
    }
    $stmt_partidas = executeQuery($pdo, $sql_partidas, [$torneio_id]);
    $partidas = $stmt_partidas ? $stmt_partidas->fetchAll() : [];
        
        // Se não encontrou com fase 'Grupos', buscar sem filtro de fase para debug
        if (empty($partidas)) {
            $sql_partidas_sem_fase = "SELECT tp.*, 
                                            t1.nome AS time1_nome, t1.cor AS time1_cor,
                                            t2.nome AS time2_nome, t2.cor AS time2_cor,
                                            tv.nome AS vencedor_nome,
                                            tg.nome AS grupo_nome, tg.id AS grupo_id
                                     FROM torneio_partidas tp
                                     LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                                     LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                                     LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                                     LEFT JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                                     WHERE tp.torneio_id = ?
                                     ORDER BY COALESCE(tp.grupo_id, 0) ASC, tp.rodada ASC, tp.id ASC";
            $stmt_partidas_sem_fase = executeQuery($pdo, $sql_partidas_sem_fase, [$torneio_id]);
            $partidas_sem_fase = $stmt_partidas_sem_fase ? $stmt_partidas_sem_fase->fetchAll() : [];
            
            // Se encontrou partidas sem o filtro de fase, usar essas (pode ser que a fase não esteja sendo salva corretamente)
            if (!empty($partidas_sem_fase)) {
                $partidas = $partidas_sem_fase;
            }
        }
    }
    
    // Verificar se há eliminatórias geradas (antes de usar nas partidas)
    $tem_eliminatorias = false;
    if ($modalidade === 'todos_chaves') {
        $sql_chaves_check = "SELECT COUNT(*) as total FROM torneio_chaves_times WHERE torneio_id = ?";
        $stmt_chaves_check = executeQuery($pdo, $sql_chaves_check, [$torneio_id]);
        $chaves_check = $stmt_chaves_check ? $stmt_chaves_check->fetch() : ['total' => 0];
        $tem_eliminatorias = (int)$chaves_check['total'] > 0;
    }
    
    // Verificar se final e 3º lugar estão finalizadas (para mostrar botão encerrar)
    $pode_encerrar = false;
    $final_finalizada_check = false;
    $terceiro_finalizado_check = false;
    
    // Buscar grupos se for modalidade todos_chaves
    $grupos = [];
    if ($modalidade === 'todos_chaves') {
        $sql_grupos = "SELECT * FROM torneio_grupos WHERE torneio_id = ? ORDER BY ordem ASC";
        $stmt_grupos = executeQuery($pdo, $sql_grupos, [$torneio_id]);
        $grupos = $stmt_grupos ? $stmt_grupos->fetchAll() : [];
    }
    
    // Buscar integrantes dos times para cada partida
    $integrantes_por_time = [];
    foreach ($partidas as $partida) {
        $time1_id = $partida['time1_id'];
        $time2_id = $partida['time2_id'];
        
        // Buscar integrantes do time 1 se ainda não buscados
        if (!isset($integrantes_por_time[$time1_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time1_id]);
            $integrantes_por_time[$time1_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
        
        // Buscar integrantes do time 2 se ainda não buscados
        if (!isset($integrantes_por_time[$time2_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time2_id]);
            $integrantes_por_time[$time2_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-futbol me-2"></i>Jogos de Enfrentamento</h5>
                <div class="d-flex gap-2">
                    <?php 
                    // Verificar se há partidas (válidas ou não) para mostrar o botão
                    $sql_count_todas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
                    $stmt_count_todas = executeQuery($pdo, $sql_count_todas, [$torneio_id]);
                    $total_partidas = $stmt_count_todas ? (int)$stmt_count_todas->fetch()['total'] : 0;
                    ?>
                    <?php if ($modalidade && $torneio['status'] !== 'Finalizado' && empty($partidas)): ?>
                        <button type="button" class="btn btn-sm btn-success" id="btnIniciarJogos" onclick="iniciarJogos()">
                            <i class="fas fa-play me-1"></i>Iniciar Jogos
                        </button>
                    <?php elseif ($torneio['status'] !== 'Finalizado' && empty($partidas)): ?>
                        <button type="button" class="btn btn-sm btn-success" id="btnIniciarJogos" onclick="iniciarJogos()" style="display: none;">
                            <i class="fas fa-play me-1"></i>Iniciar Jogos
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($partidas)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirEnfrentamentos()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                    <?php if (($total_partidas > 0 || !empty($partidas)) && $torneio['status'] !== 'Finalizado'): ?>
                        <button class="btn btn-sm btn-danger" onclick="limparJogos()">
                            <i class="fas fa-trash me-1"></i>Limpar Jogos
                        </button>
                    <?php endif; ?>
                    <?php if ($torneio['status'] === 'Finalizado'): ?>
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-flag-checkered me-1"></i>Torneio Finalizado
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($partidas)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum jogo gerado ainda. Configure o formato do campeonato e clique em "Iniciar Jogos" no cabeçalho acima para gerar os confrontos.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" id="tabela-enfrentamentos">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rodada_atual = 0;
                                $grupo_atual = null;
                                foreach ($partidas as $partida): 
                                    // Mostrar cabeçalho de grupo se mudou
                                    if ($modalidade === 'todos_chaves' && $partida['grupo_id'] != $grupo_atual):
                                        $grupo_atual = $partida['grupo_id'];
                                ?>
                                    <tr class="table-info">
                                        <td colspan="5" class="text-center"><strong><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($partida['grupo_nome'] ?? 'Chave'); ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                    <?php if ($partida['rodada'] != $rodada_atual):
                                        $rodada_atual = $partida['rodada'];
                                ?>
                                    <tr class="table-secondary">
                                        <td colspan="5"><strong>Rodada <?php echo $rodada_atual; ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                    <tr id="partida_row_<?php echo $partida['id']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($partida['time1_nome']); ?></strong>
                                                <?php if (!empty($integrantes_por_time[$partida['time1_id']])): ?>
                                                    <?php 
                                                    $primeiro_integ = $integrantes_por_time[$partida['time1_id']][0];
                                                    $tem_foto_valida = false;
                                                    $avatar = '';
                                                    
                                                    if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                        $avatar = $primeiro_integ['foto_perfil'];
                                                        // Verificar se não é a logo padrão
                                                        if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            $tem_foto_valida = true;
                                                        endif;
                                                    endif;
                                                    
                                                    if ($tem_foto_valida):
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php 
                                                            // Mostrar apenas o primeiro nome do primeiro integrante
                                                            $primeiro_integ = $integrantes_por_time[$partida['time1_id']][0];
                                                            $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                            $primeiro_nome = explode(' ', trim($nome))[0];
                                                            echo htmlspecialchars($primeiro_nome);
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time1_id']): ?>
                                                    <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 justify-content-center">
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time1_<?php echo $partida['id']; ?>" 
                                                       value="<?php echo $partida['pontos_time1']; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $partida['id']; ?>"
                                                       readonly
                                                       disabled>
                                                <span class="mx-1">x</span>
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time2_<?php echo $partida['id']; ?>" 
                                                       value="<?php echo $partida['pontos_time2']; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $partida['id']; ?>"
                                                       readonly
                                                       disabled>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($partida['time2_nome']); ?></strong>
                                                <?php if (!empty($integrantes_por_time[$partida['time2_id']])): ?>
                                                    <?php 
                                                    $primeiro_integ = $integrantes_por_time[$partida['time2_id']][0];
                                                    $tem_foto_valida = false;
                                                    $avatar = '';
                                                    
                                                    if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                        $avatar = $primeiro_integ['foto_perfil'];
                                                        // Verificar se não é a logo padrão
                                                        if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            $tem_foto_valida = true;
                                                        endif;
                                                    endif;
                                                    
                                                    if ($tem_foto_valida):
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php 
                                                            // Mostrar apenas o primeiro nome do primeiro integrante
                                                            $primeiro_integ = $integrantes_por_time[$partida['time2_id']][0];
                                                            $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                            $primeiro_nome = explode(' ', trim($nome))[0];
                                                            echo htmlspecialchars($primeiro_nome);
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time2_id']): ?>
                                                    <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $partida['status'] === 'Finalizada' ? 'success' : ($partida['status'] === 'Em Andamento' ? 'warning' : 'secondary'); ?>">
                                                <?php echo $partida['status']; ?>
                                            </span>
                                            <select class="form-select form-select-sm status-select d-none" 
                                                    id="status_<?php echo $partida['id']; ?>" 
                                                    data-partida-id="<?php echo $partida['id']; ?>"
                                                    disabled>
                                                <option value="Agendada" <?php echo $partida['status'] === 'Agendada' ? 'selected' : ''; ?>>Agendada</option>
                                                <option value="Em Andamento" <?php echo $partida['status'] === 'Em Andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                                <option value="Finalizada" <?php echo $partida['status'] === 'Finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                            </select>
                                        </td>
                                        <td>
                                            <?php if ($torneio['status'] !== 'Finalizado' && !$tem_eliminatorias): ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-success btn-salvar-partida" 
                                                        id="btn_salvar_<?php echo $partida['id']; ?>" 
                                                        onclick="salvarResultadoPartidaInline(<?php echo $partida['id']; ?>)"
                                                        style="display: none;">
                                                    <i class="fas fa-save"></i> Salvar
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary btn-editar-partida" 
                                                        id="btn_editar_<?php echo $partida['id']; ?>" 
                                                        onclick="habilitarEdicaoPartida(<?php echo $partida['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                            </div>
                                            <?php elseif ($tem_eliminatorias): ?>
                                                <span class="text-muted"><i class="fas fa-lock"></i> Eliminatórias geradas</span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-lock"></i> Torneio Finalizado</span>
                                            <?php endif; ?>
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

<!-- Estatísticas dos Jogos -->
<?php if ($timesSalvos && $modalidade && !empty($partidas)): 
    // Calcular total de jogos (cada partida conta como 1 jogo)
    $total_jogos = count($partidas);
    
    // Coletar rodadas únicas
    $rodadas_unicas = [];
    foreach ($partidas as $partida) {
        $rodada = $partida['rodada'];
        if (!in_array($rodada, $rodadas_unicas)) {
            $rodadas_unicas[] = $rodada;
        }
    }
    $total_rodadas = count($rodadas_unicas);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr>
                                <td><strong>Total de jogos por time:</strong></td>
                                <td class="text-center"><strong><?php echo str_pad($total_jogos, 2, '0', STR_PAD_LEFT); ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>Total de rodadas:</strong></td>
                                <td class="text-center"><strong><?php echo str_pad($total_rodadas, 2, '0', STR_PAD_LEFT); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chaves Eliminatórias -->
<?php if ($modalidade === 'todos_chaves'): 
    // Buscar chaves do torneio
    $sql_chaves = "SELECT tc.*, 
                          t1.nome AS time1_nome, t1.cor AS time1_cor,
                          t2.nome AS time2_nome, t2.cor AS time2_cor,
                          tv.nome AS vencedor_nome
                   FROM torneio_chaves_times tc
                   LEFT JOIN torneio_times t1 ON t1.id = tc.time1_id
                   LEFT JOIN torneio_times t2 ON t2.id = tc.time2_id
                   LEFT JOIN torneio_times tv ON tv.id = tc.vencedor_id
                   WHERE tc.torneio_id = ?
                   ORDER BY FIELD(tc.fase, 'Quartas', 'Semi', 'Final', '3º Lugar'), tc.chave_numero ASC";
    $stmt_chaves = executeQuery($pdo, $sql_chaves, [$torneio_id]);
    $chaves = $stmt_chaves ? $stmt_chaves->fetchAll() : [];
    $tem_eliminatorias = !empty($chaves);
    
    // Buscar integrantes dos times para as chaves eliminatórias
    $integrantes_por_time_chaves = [];
    if (!empty($chaves)) {
        foreach ($chaves as $chave) {
            if ($chave['time1_id'] && !isset($integrantes_por_time_chaves[$chave['time1_id']])) {
                $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                   FROM torneio_time_integrantes tti
                                   JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                   LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                   WHERE tti.time_id = ?
                                   ORDER BY tp.nome_avulso, u.nome";
                $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$chave['time1_id']]);
                $integrantes_por_time_chaves[$chave['time1_id']] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
            }
            if ($chave['time2_id'] && !isset($integrantes_por_time_chaves[$chave['time2_id']])) {
                $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                   FROM torneio_time_integrantes tti
                                   JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                   LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                   WHERE tti.time_id = ?
                                   ORDER BY tp.nome_avulso, u.nome";
                $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$chave['time2_id']]);
                $integrantes_por_time_chaves[$chave['time2_id']] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
            }
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Chaves Eliminatórias</h5>
                <div>
                    <?php 
                    // Verificar se todas as partidas da fase de grupos estão finalizadas
                    $sql_partidas_grupos = "SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                           FROM torneio_partidas 
                                           WHERE torneio_id = ? AND fase = 'Grupos'";
                    $stmt_partidas_grupos = executeQuery($pdo, $sql_partidas_grupos, [$torneio_id]);
                    $info_partidas = $stmt_partidas_grupos ? $stmt_partidas_grupos->fetch() : ['total' => 0, 'finalizadas' => 0];
                    $todas_finalizadas = $info_partidas['total'] > 0 && $info_partidas['finalizadas'] == $info_partidas['total'];
                    $pode_gerar = empty($chaves) && $todas_finalizadas && $torneio['status'] !== 'Finalizado';
                    ?>
                    <?php if ($pode_gerar): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarEliminatorias()">
                            <i class="fas fa-play me-1"></i>Gerar Eliminatórias
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($chaves)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($todas_finalizadas): ?>
                            Clique em "Gerar Eliminatórias" para criar as chaves semi-final e final com os 2 melhores times de cada chave.
                        <?php else: ?>
                            Finalize todos os jogos da fase de grupos para gerar as chaves eliminatórias.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php 
                    $fases = ['Quartas', 'Semi', 'Final', '3º Lugar'];
                    foreach ($fases as $fase):
                        $chaves_fase = array_filter($chaves, function($c) use ($fase) { return $c['fase'] === $fase; });
                        if (!empty($chaves_fase)):
                    ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-center">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div class="rounded border border-primary border-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 100px; height: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 8px rgba(0,0,0,0.2); border-radius: 8px !important;">
                                                    <div class="text-center text-white">
                                                        <strong style="font-size: 12px; font-weight: bold;"><?php 
                                                            if ($fase === 'Semi') {
                                                                echo 'Semi-Final';
                                                            } elseif ($fase === 'Final') {
                                                                echo 'Final';
                                                            } elseif ($fase === '3º Lugar') {
                                                                echo '3º Lugar';
                                                            } else {
                                                                echo $fase;
                                                            }
                                                        ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chaves_fase as $chave): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                    <strong><?php echo htmlspecialchars($chave['time1_nome'] ?? 'Aguardando'); ?></strong>
                                                    <?php if ($chave['time1_id'] && !empty($integrantes_por_time_chaves[$chave['time1_id']])): ?>
                                                        <?php 
                                                        $primeiro_integ = $integrantes_por_time_chaves[$chave['time1_id']][0];
                                                        $tem_foto_valida = false;
                                                        $avatar = '';
                                                        
                                                        if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                            $avatar = $primeiro_integ['foto_perfil'];
                                                            // Verificar se não é a logo padrão
                                                            if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    if (strpos($avatar, '../../') !== 0) {
                                                                        if (strpos($avatar, '../') === 0) {
                                                                            $avatar = '../' . ltrim($avatar, '/');
                                                                        } else {
                                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                                        }
                                                                    }
                                                                } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                    $avatar = '../../assets/arquivos/' . $avatar;
                                                                }
                                                                $tem_foto_valida = true;
                                                            endif;
                                                        endif;
                                                        
                                                        if ($tem_foto_valida):
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                <?php 
                                                                // Mostrar apenas o primeiro nome do primeiro integrante
                                                                $primeiro_integ = $integrantes_por_time_chaves[$chave['time1_id']][0];
                                                                $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                                $primeiro_nome = explode(' ', trim($nome))[0];
                                                                echo htmlspecialchars($primeiro_nome);
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($chave['status'] === 'Finalizada' && $chave['vencedor_id'] == $chave['time1_id']): ?>
                                                        <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1 justify-content-center">
                                                    <?php if ($chave['time1_id'] && $chave['time2_id']): ?>
                                                        <input type="number" 
                                                               class="form-control form-control-sm pontos-chave-input" 
                                                               id="pontos_time1_chave_<?php echo $chave['id']; ?>" 
                                                               value="<?php echo $chave['pontos_time1']; ?>" 
                                                               min="0" 
                                                               style="width: 60px; height: 30px; text-align: center; padding: 2px 4px;"
                                                               data-chave-id="<?php echo $chave['id']; ?>"
                                                               readonly
                                                               disabled>
                                                        <span class="mx-1">x</span>
                                                        <input type="number" 
                                                               class="form-control form-control-sm pontos-chave-input" 
                                                               id="pontos_time2_chave_<?php echo $chave['id']; ?>" 
                                                               value="<?php echo $chave['pontos_time2']; ?>" 
                                                               min="0" 
                                                               style="width: 60px; height: 30px; text-align: center; padding: 2px 4px;"
                                                               data-chave-id="<?php echo $chave['id']; ?>"
                                                               readonly
                                                               disabled>
                                                <?php else: ?>
                                                        <span class="text-muted">Aguardando</span>
                                                <?php endif; ?>
                                            </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                    <strong><?php echo htmlspecialchars($chave['time2_nome'] ?? 'Aguardando'); ?></strong>
                                                    <?php if ($chave['time2_id'] && !empty($integrantes_por_time_chaves[$chave['time2_id']])): ?>
                                                        <?php 
                                                        $primeiro_integ = $integrantes_por_time_chaves[$chave['time2_id']][0];
                                                        $tem_foto_valida = false;
                                                        $avatar = '';
                                                        
                                                        if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                            $avatar = $primeiro_integ['foto_perfil'];
                                                            // Verificar se não é a logo padrão
                                                            if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    if (strpos($avatar, '../../') !== 0) {
                                                                        if (strpos($avatar, '../') === 0) {
                                                                            $avatar = '../' . ltrim($avatar, '/');
                                                                        } else {
                                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                                        }
                                                                    }
                                                                } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                    $avatar = '../../assets/arquivos/' . $avatar;
                                                                }
                                                                $tem_foto_valida = true;
                                                            endif;
                                                        endif;
                                                        
                                                        if ($tem_foto_valida):
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                <?php 
                                                                // Mostrar apenas o primeiro nome do primeiro integrante
                                                                $primeiro_integ = $integrantes_por_time_chaves[$chave['time2_id']][0];
                                                                $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                                $primeiro_nome = explode(' ', trim($nome))[0];
                                                                echo htmlspecialchars($primeiro_nome);
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($chave['status'] === 'Finalizada' && $chave['vencedor_id'] == $chave['time2_id']): ?>
                                                        <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                    <?php endif; ?>
                                            </div>
                                            </td>
                                            <td></td>
                                            <td>
                                                <span class="badge bg-<?php echo $chave['status'] === 'Finalizada' ? 'success' : ($chave['status'] === 'Em Andamento' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo $chave['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($chave['time1_id'] && $chave['time2_id'] && $torneio['status'] !== 'Finalizado'): ?>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-success btn-salvar-chave" 
                                                                id="btn_salvar_chave_<?php echo $chave['id']; ?>" 
                                                                onclick="salvarResultadoChave(<?php echo $chave['id']; ?>)"
                                                                style="display: none;">
                                                            <i class="fas fa-save"></i> Salvar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary btn-editar-chave" 
                                                                id="btn_editar_chave_<?php echo $chave['id']; ?>" 
                                                                onclick="habilitarEdicaoChave(<?php echo $chave['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Editar
                                                        </button>
                                                </div>
                                            <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    
                    // Verificar se final e 3º lugar estão finalizadas para mostrar pódio
                    $final_finalizada = false;
                    $terceiro_finalizado = false;
                    $vencedor_final = null;
                    $segundo_lugar = null;
                    $terceiro_lugar = null;
                    
                    foreach ($chaves as $chave) {
                        // Verificar Final
                        if (!empty($chave['fase']) && trim($chave['fase']) === 'Final' && 
                            !empty($chave['status']) && trim($chave['status']) === 'Finalizada' && 
                            !empty($chave['vencedor_id']) && (int)$chave['vencedor_id'] > 0) {
                            $final_finalizada = true;
                            // Buscar dados do vencedor
                            $sql_vencedor = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                            $stmt_vencedor = executeQuery($pdo, $sql_vencedor, [(int)$chave['vencedor_id']]);
                            if ($stmt_vencedor) {
                                $vencedor_final = $stmt_vencedor->fetch(PDO::FETCH_ASSOC);
                            }
                            
                            // Buscar o perdedor (2º lugar) - o time que perdeu a final
                            if (!empty($chave['time1_id']) && !empty($chave['time2_id']) && 
                                (int)$chave['time1_id'] > 0 && (int)$chave['time2_id'] > 0) {
                                $perdedor_id = ((int)$chave['time1_id'] == (int)$chave['vencedor_id']) ? (int)$chave['time2_id'] : (int)$chave['time1_id'];
                                $sql_segundo = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                                $stmt_segundo = executeQuery($pdo, $sql_segundo, [$perdedor_id]);
                                if ($stmt_segundo) {
                                    $segundo_lugar = $stmt_segundo->fetch(PDO::FETCH_ASSOC);
                                }
                            }
                        }
                        // Verificar 3º Lugar
                        if (!empty($chave['fase']) && trim($chave['fase']) === '3º Lugar' && 
                            !empty($chave['status']) && trim($chave['status']) === 'Finalizada' && 
                            !empty($chave['vencedor_id']) && (int)$chave['vencedor_id'] > 0) {
                            $terceiro_finalizado = true;
                            // Buscar dados do 3º lugar
                            $sql_terceiro = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                            $stmt_terceiro = executeQuery($pdo, $sql_terceiro, [(int)$chave['vencedor_id']]);
                            if ($stmt_terceiro) {
                                $terceiro_lugar = $stmt_terceiro->fetch(PDO::FETCH_ASSOC);
                            }
                        }
                    }
                    
                    // Verificar se todas as condições estão atendidas para exibir o pódio
                    // Debug temporário (descomente para debug)
                    /*
                    error_log("=== DEBUG PÓDIO ===");
                    error_log("final_finalizada: " . ($final_finalizada ? 'true' : 'false'));
                    error_log("terceiro_finalizado: " . ($terceiro_finalizado ? 'true' : 'false'));
                    error_log("vencedor_final: " . ($vencedor_final ? 'existe (id: ' . $vencedor_final['id'] . ')' : 'null'));
                    error_log("segundo_lugar: " . ($segundo_lugar ? 'existe (id: ' . $segundo_lugar['id'] . ')' : 'null'));
                    error_log("terceiro_lugar: " . ($terceiro_lugar ? 'existe (id: ' . $terceiro_lugar['id'] . ')' : 'null'));
                    error_log("Total chaves: " . count($chaves));
                    foreach ($chaves as $idx => $ch) {
                        error_log("Chave $idx - fase: " . ($ch['fase'] ?? 'NULL') . ", status: " . ($ch['status'] ?? 'NULL') . ", vencedor_id: " . ($ch['vencedor_id'] ?? 'NULL'));
                    }
                    */
                    
                    if ($final_finalizada && $terceiro_finalizado && !empty($vencedor_final) && !empty($segundo_lugar) && !empty($terceiro_lugar)):
                        // Buscar integrantes dos times do pódio
                        $integrantes_podio = [];
                        $times_podio = [$vencedor_final['id'], $segundo_lugar['id'], $terceiro_lugar['id']];
                        foreach ($times_podio as $time_id) {
                            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                               FROM torneio_time_integrantes tti
                                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                               WHERE tti.time_id = ?
                                               ORDER BY tp.nome_avulso, u.nome";
                            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
                            $integrantes_podio[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
                        }
                ?>
                    <div class="mt-4">
                        <h5 class="mb-3"><i class="fas fa-trophy me-2"></i>Pódio</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 80px;">Posição</th>
                                        <th>Time</th>
                                        <th>Participantes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background-color: #ffd700;">
                                        <td class="text-center">
                                            <i class="fas fa-trophy text-warning" style="font-size: 24px;"></i>
                                            <br><strong>1º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($vencedor_final['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($vencedor_final['nome']); ?></strong>
                                    </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$vencedor_final['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                </div>
                                        </td>
                                    </tr>
                                    <tr style="background-color: #c0c0c0;">
                                        <td class="text-center">
                                            <i class="fas fa-medal" style="font-size: 24px; color: #808080;"></i>
                                            <br><strong>2º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($segundo_lugar['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($segundo_lugar['nome']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$segundo_lugar['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                                        </td>
                                    </tr>
                                    <tr style="background-color: #cd7f32;">
                                        <td class="text-center">
                                            <i class="fas fa-medal" style="font-size: 24px; color: #d4a574 !important;"></i>
                                            <br><strong>3º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($terceiro_lugar['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($terceiro_lugar['nome']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$terceiro_lugar['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                    <?php 
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classificação dos Times -->
<?php if ($timesSalvos && $modalidade): 
    // Buscar classificação
    $sql_classificacao = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                         FROM torneio_classificacao tc
                         JOIN torneio_times tt ON tt.id = tc.time_id
                         WHERE tc.torneio_id = ?
                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
    $stmt_classificacao = executeQuery($pdo, $sql_classificacao, [$torneio_id]);
    $classificacao = $stmt_classificacao ? $stmt_classificacao->fetchAll() : [];
    
    // Buscar integrantes dos times para a classificação
    $integrantes_classificacao = [];
    foreach ($classificacao as $class) {
        $time_id = $class['time_id'];
        if (!isset($integrantes_classificacao[$time_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome
                               LIMIT 1";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
            $integrantes_classificacao[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetch() : null;
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação dos Times</h5>
                <div>
                    <?php if (!empty($classificacao)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirClassificacao()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($classificacao)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        A classificação será atualizada automaticamente conforme os jogos são finalizados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" id="tabela-classificacao">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">Pos</th>
                                    <th>Time</th>
                                    <th class="text-center">Jogos</th>
                                    <th class="text-center">V</th>
                                    <th class="text-center">D</th>
                                    <th class="text-center">PF</th>
                                    <th class="text-center">PS</th>
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
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($class['time_nome']); ?></strong>
                                                <?php if (!empty($integrantes_classificacao[$class['time_id']])): ?>
                                                    <?php 
                                                    $primeiro_integ = $integrantes_classificacao[$class['time_id']];
                                                    if ($primeiro_integ && $primeiro_integ['usuario_id']):
                                                        $avatar = $primeiro_integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                        if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                            if (strpos($avatar, '../../') !== 0) {
                                                                if (strpos($avatar, '../') === 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                } else {
                                                                    $avatar = '../../' . ltrim($avatar, '/');
                                                                }
                                                            }
                                                        } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                            $avatar = '../../assets/arquivos/' . $avatar;
                                                        }
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome']); ?>">
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $total_jogos_jogados = (int)$class['vitorias'] + (int)$class['derrotas'];
                                            ?>
                                            <span class="badge bg-info"><?php echo $total_jogos_jogados; ?></span>
                                        </td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $class['vitorias']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $class['derrotas']; ?></span></td>
                                        <td class="text-center"><?php echo $class['pontos_pro']; ?></td>
                                        <td class="text-center"><?php echo $class['pontos_contra']; ?></td>
                                        <td class="text-center <?php echo $class['saldo_pontos'] > 0 ? 'text-success' : ($class['saldo_pontos'] < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo $class['saldo_pontos'] > 0 ? '+' : ''; ?><?php echo $class['saldo_pontos']; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($class['average'], 2); ?></td>
                                        <td class="text-center"><strong><?php echo $class['pontos_total']; ?></strong></td>
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
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Modal Adicionar Participante -->
<div class="modal fade" id="modalAdicionarParticipante" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Adicionar Participante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAdicionarParticipante">
                <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                <div class="modal-body">
                    <?php if ($tipoTorneio === 'grupo'): ?>
                        <?php
                        $maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
                        $totalAtual = count($participantes);
                        $vagasDisponiveis = $maxParticipantes > 0 ? ($maxParticipantes - $totalAtual) : 999;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-label mb-0 fw-bold">Selecione os membros do grupo:</div>
                                <?php if ($maxParticipantes > 0): ?>
                                    <small class="text-muted">Vagas disponíveis: <?php echo max(0, $vagasDisponiveis); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($membros_grupo)): ?>
                                    <p class="text-muted mb-0">Nenhum membro no grupo.</p>
                                <?php else: ?>
                                    <?php
                                    $membrosDisponiveis = [];
                                    foreach ($membros_grupo as $membro) {
                                        $jaParticipa = false;
                                        foreach ($participantes as $p) {
                                            if ($p['usuario_id'] == $membro['id']) {
                                                $jaParticipa = true;
                                                break;
                                            }
                                        }
                                        if (!$jaParticipa) {
                                            $membrosDisponiveis[] = $membro;
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($membrosDisponiveis)): ?>
                                        <div class="form-check mb-2 pb-2 border-bottom">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="marcarTodos" 
                                                   onchange="marcarTodosParticipantes(this)">
                                            <label class="form-check-label fw-bold" for="marcarTodos">
                                                Marcar Todos
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($membrosDisponiveis as $membro): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input participante-checkbox" type="checkbox" 
                                                   name="participantes[]" value="<?php echo (int)$membro['id']; ?>" 
                                                   id="membro_<?php echo (int)$membro['id']; ?>">
                                            <label class="form-check-label d-flex align-items-center gap-2" 
                                                   for="membro_<?php echo (int)$membro['id']; ?>">
                                                <?php 
                                                $avatar = $membro['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                // Corrigir caminho da foto de perfil
                                                if (!empty($membro['foto_perfil'])) {
                                                    // Se já começa com http ou /, usar como está
                                                    if (strpos($membro['foto_perfil'], 'http') === 0 || strpos(trim($membro['foto_perfil']), '/') === 0) {
                                                        $avatar = $membro['foto_perfil'];
                                                    } elseif (strpos($membro['foto_perfil'], '../../assets/') === 0 || strpos($membro['foto_perfil'], '../assets/') === 0 || strpos($membro['foto_perfil'], 'assets/') === 0) {
                                                        // Se já tem assets/, garantir que comece com ../../
                                                        if (strpos($membro['foto_perfil'], '../../') !== 0) {
                                                            if (strpos($membro['foto_perfil'], '../') === 0) {
                                                                $avatar = '../' . ltrim($membro['foto_perfil'], '/');
                                                            } else {
                                                                $avatar = '../../' . ltrim($membro['foto_perfil'], '/');
                                                        }
                                                    } else {
                                                            $avatar = $membro['foto_perfil'];
                                                    }
                                                } else {
                                                        // Se não tem caminho, adicionar caminho completo
                                                        $avatar = '../../assets/arquivos/' . ltrim($membro['foto_perfil'], '/');
                                                    }
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                     class="rounded-circle" width="24" height="24" 
                                                     style="object-fit:cover;" alt="Avatar">
                                                <span><?php echo htmlspecialchars($membro['nome']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($membrosDisponiveis)): ?>
                                        <p class="text-muted mb-0">Todos os membros do grupo já estão inscritos.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="nome_avulso" class="form-label">Nome do Participante *</label>
                            <input type="text" class="form-control" id="nome_avulso" name="nome_avulso" 
                                   placeholder="Ex: João ou João, Marcos, Josué" required>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Você pode adicionar múltiplos participantes de uma vez separando os nomes por vírgula. 
                                Exemplo: <strong>João, Marcos, Josué</strong>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Torneio -->
<div class="modal fade" id="modalEditarTorneio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Editar Torneio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTorneio">
                <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                <div class="modal-body">
                    <!-- Informações do Torneio (somente leitura) -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Data:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                                echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'N/A';
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Tipo:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                if (isset($torneio['tipo'])) {
                                    echo $torneio['tipo'] === 'grupo' ? 'Torneio do Grupo' : 'Torneio Avulso';
                                } else {
                                    echo $torneio['grupo_id'] ? 'Torneio do Grupo' : 'Torneio Avulso';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Grupo:</strong></label>
                            <div class="form-control-plaintext">
                                <?php echo $torneio['grupo_nome'] ? htmlspecialchars($torneio['grupo_nome']) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Participantes:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                $maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
                                echo count($participantes); ?> / <?php echo (int)$maxParticipantes; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <!-- Campos editáveis -->
                    <div class="mb-3">
                        <label for="edit_nome_torneio" class="form-label">Nome do Torneio *</label>
                        <input type="text" class="form-control" id="edit_nome_torneio" name="nome" 
                               value="<?php echo htmlspecialchars($torneio['nome']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_data_torneio" class="form-label">Data do Torneio *</label>
                        <input type="date" class="form-control" id="edit_data_torneio" name="data_torneio" 
                               value="<?php 
                               $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                               echo $dataTorneio ? date('Y-m-d', strtotime($dataTorneio)) : '';
                               ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_descricao_torneio" class="form-label">Descrição</label>
                        <textarea class="form-control" id="edit_descricao_torneio" name="descricao" rows="3"><?php echo htmlspecialchars($torneio['descricao'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="excluirTorneio(<?php echo $torneio_id; ?>); bootstrap.Modal.getInstance(document.getElementById('modalEditarTorneio')).hide();">
                        <i class="fas fa-trash me-1"></i>Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Perfil do Usuário -->
<div class="modal fade" id="modalVerPerfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Perfil do Participante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudoPerfil">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Integrante ao Time -->
<div class="modal fade" id="modalAdicionarIntegrante" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Integrante ao Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="listaParticipantesDisponiveis">
                    <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Fallback para showAlert
if (typeof showAlert === 'undefined') {
    function showAlert(message, type) {
        type = type || 'info';
        var alertClass = 'alert-' + type;
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
        var alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alert-container';
            alertContainer.className = 'position-fixed top-0 end-0 p-3';
            alertContainer.style.zIndex = '9999';
            document.body.appendChild(alertContainer);
        }
        alertContainer.innerHTML = alertHtml;
        setTimeout(function() {
            var alert = alertContainer.querySelector('.alert');
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
}

let timeAtualId = null;
let timeAtualNumero = null;
let maxParticipantesTorneio = <?php echo (int)($torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0); ?>;
let totalParticipantesAtual = <?php echo count($participantes); ?>;
let listaParticipantesExpandida = false; // Começar recolhida
let secaoTimesExpandida = false; // Começar recolhida
let secaoInformacoesExpandida = true; // Começar expandida
let secaoConfiguracoesExpandida = true; // Começar expandida
let secaoFormatoExpandida = true; // Começar expandida

// Estilos CSS para drag and drop
const style = document.createElement('style');
style.textContent = `
    .time-participantes.drag-over {
        background-color: #e3f2fd !important;
        border: 2px dashed #2196F3 !important;
        border-radius: 4px;
    }
    .participante-item {
        cursor: move;
        transition: opacity 0.2s;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
    .participante-item:hover {
        background-color: #f8f9fa;
    }
    .participante-item[draggable="true"]:active {
        cursor: grabbing;
    }
    .participante-item.selecionado {
        background-color: #cfe2ff !important;
        border: 2px solid #0d6efd !important;
        transform: scale(1.02);
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .participante-item:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
    }
    .time-participantes {
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
`;
document.head.appendChild(style);

function toggleListaParticipantes() {
    const corpo = document.getElementById('corpoListaParticipantes');
    const icone = document.getElementById('iconeParticipantes');
    
    if (corpo && icone) {
        if (listaParticipantesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            listaParticipantesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            listaParticipantesExpandida = true;
        }
    }
}

function toggleSecaoTimes() {
    const corpo = document.getElementById('corpoSecaoTimes');
    const icone = document.getElementById('iconeSecaoTimes');
    
    if (corpo && icone) {
        if (secaoTimesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoTimesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoTimesExpandida = true;
        }
    }
}

function toggleSecaoInformacoes() {
    const corpo = document.getElementById('corpoSecaoInformacoes');
    const icone = document.getElementById('iconeSecaoInformacoes');
    
    if (corpo && icone) {
        if (secaoInformacoesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoInformacoesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoInformacoesExpandida = true;
        }
    }
}

function toggleSecaoConfiguracoes() {
    const corpo = document.getElementById('corpoSecaoConfiguracoes');
    const icone = document.getElementById('iconeSecaoConfiguracoes');
    
    if (corpo && icone) {
        if (secaoConfiguracoesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoConfiguracoesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoConfiguracoesExpandida = true;
        }
    }
}

function toggleSecaoFormato() {
    const corpo = document.getElementById('corpoSecaoFormato');
    const icone = document.getElementById('iconeSecaoFormato');
    
    if (corpo && icone) {
        if (secaoFormatoExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoFormatoExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoFormatoExpandida = true;
        }
    }
}

function recolherSecoesTorneio() {
    // Recolher Informações do Torneio
    if (secaoInformacoesExpandida) {
        toggleSecaoInformacoes();
    }
    
    // Recolher Configurações do Torneio
    if (secaoConfiguracoesExpandida) {
        toggleSecaoConfiguracoes();
    }
    
    // Recolher Formato de Campeonato
    if (secaoFormatoExpandida) {
        toggleSecaoFormato();
    }
}

// Inicializar estado: lista começa recolhida
document.addEventListener('DOMContentLoaded', function() {
    const corpo = document.getElementById('corpoListaParticipantes');
    const icone = document.getElementById('iconeParticipantes');
    
    if (corpo && icone) {
        corpo.style.display = 'none';
        icone.classList.remove('fa-chevron-down');
        icone.classList.add('fa-chevron-right');
        listaParticipantesExpandida = false;
    }
    
    // Seção de times começa recolhida
    const corpoSecaoTimes = document.getElementById('corpoSecaoTimes');
    const iconeSecaoTimes = document.getElementById('iconeSecaoTimes');
    if (corpoSecaoTimes && iconeSecaoTimes) {
        corpoSecaoTimes.style.display = 'none';
        iconeSecaoTimes.classList.remove('fa-chevron-down');
        iconeSecaoTimes.classList.add('fa-chevron-right');
        secaoTimesExpandida = false;
    }
});

function abrirModalEditarTorneio() {
    const modal = new bootstrap.Modal(document.getElementById('modalEditarTorneio'));
    modal.show();
}

function abrirModalAdicionarParticipante() {
    const modal = new bootstrap.Modal(document.getElementById('modalAdicionarParticipante'));
    modal.show();
    // Atualizar contador ao abrir modal
    atualizarContadorVagas();
}

function marcarTodosParticipantes(checkbox) {
    const checkboxes = Array.from(document.querySelectorAll('.participante-checkbox'));
    
    if (checkbox.checked) {
        // Marcar todos, respeitando o limite
        if (maxParticipantesTorneio > 0) {
            const vagasDisponiveis = maxParticipantesTorneio - totalParticipantesAtual;
            const quantidadeMarcar = Math.min(vagasDisponiveis, checkboxes.length);
            
            for (let i = 0; i < quantidadeMarcar; i++) {
                checkboxes[i].checked = true;
            }
            
            if (checkboxes.length > vagasDisponiveis) {
                showAlert('Apenas ' + quantidadeMarcar + ' participante(s) foram marcados devido ao limite de ' + maxParticipantesTorneio + ' participantes.', 'info');
            }
        } else {
            // Sem limite, marcar todos
            checkboxes.forEach(function(cb) {
                cb.checked = true;
            });
        }
    } else {
        // Desmarcar todos
        checkboxes.forEach(function(cb) {
            cb.checked = false;
        });
    }
    
    validarQuantidadeMaxima();
}

function validarQuantidadeMaxima() {
    if (maxParticipantesTorneio <= 0) {
        atualizarContadorVagas();
        return; // Sem limite
    }
    
    const selecionados = document.querySelectorAll('.participante-checkbox:checked').length;
    const total = totalParticipantesAtual + selecionados;
    
    if (total > maxParticipantesTorneio) {
        const excedente = total - maxParticipantesTorneio;
        showAlert('Você selecionou ' + excedente + ' participante(s) a mais do que o limite permitido (' + maxParticipantesTorneio + '). Alguns participantes foram desmarcados automaticamente.', 'warning');
        
        // Desmarcar os últimos checkboxes até respeitar o limite
        const checkboxes = Array.from(document.querySelectorAll('.participante-checkbox:checked'));
        const quantidadeManter = selecionados - excedente;
        for (let i = quantidadeManter; i < checkboxes.length; i++) {
            if (checkboxes[i]) {
                checkboxes[i].checked = false;
            }
        }
        
        // Atualizar checkbox "marcar todos" se necessário
        const checkboxMarcarTodos = document.getElementById('marcarTodos');
        if (checkboxMarcarTodos) {
            const todosMarcados = document.querySelectorAll('.participante-checkbox:checked').length === document.querySelectorAll('.participante-checkbox').length;
            checkboxMarcarTodos.checked = todosMarcados;
        }
    }
    
    atualizarContadorVagas();
}

function atualizarContadorVagas() {
    if (maxParticipantesTorneio > 0) {
        const selecionados = document.querySelectorAll('.participante-checkbox:checked').length;
        const vagasRestantes = maxParticipantesTorneio - totalParticipantesAtual - selecionados;
        const labelVagas = document.querySelector('.modal-body .text-muted');
        if (labelVagas) {
            labelVagas.textContent = 'Vagas disponíveis: ' + Math.max(0, vagasRestantes);
            if (vagasRestantes < 0) {
                labelVagas.classList.remove('text-muted');
                labelVagas.classList.add('text-danger');
            } else {
                labelVagas.classList.remove('text-danger');
                labelVagas.classList.add('text-muted');
            }
        }
    }
}

function adicionarIntegranteTime(timeId) {
    timeAtualId = timeId;
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível para este time.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarIntegranteAoTime(' + timeId + ', ' + p.id + '); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            // Corrigir caminho da foto
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    // Já tem assets/, garantir que comece com ../../
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    // Apenas nome do arquivo, adicionar caminho completo
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('modalAdicionarIntegrante'));
                modal.show();
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarParticipanteAoTime(timeId, timeNumero) {
    timeAtualId = timeId;
    timeAtualNumero = timeNumero;
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeId || 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarParticipanteAoTimeSelecionado(' + p.id + ', \'' + p.nome.replace(/'/g, "\\'") + '\', \'' + (p.foto_perfil || '') + '\'); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    // Já tem assets/, garantir que comece com ../../
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    // Apenas nome do arquivo, adicionar caminho completo
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('modalAdicionarIntegrante'));
                modal.show();
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarParticipanteAoTimeSelecionado(participanteId, nome, foto) {
    const timeContainerId = timeAtualId ? '#time-' + timeAtualId : '#time-novo_' + timeAtualNumero;
    const timeContainer = document.querySelector(timeContainerId);
    
    if (!timeContainer) {
        showAlert('Time não encontrado', 'danger');
        return;
    }
    
    // Verificar se já está no time
    const jaExiste = timeContainer.querySelector('[data-participante-id="' + participanteId + '"]');
    if (jaExiste) {
        showAlert('Este participante já está neste time.', 'warning');
        return;
    }
    
    // Verificar limite de integrantes por time
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    const totalIntegrantes = timeContainer.querySelectorAll('.participante-item').length;
    if (integrantesPorTime > 0 && totalIntegrantes >= integrantesPorTime) {
        showAlert('Limite de integrantes por time atingido (' + integrantesPorTime + ').', 'warning');
        return;
    }
    
    // Obter informações do time
    const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    const timeNumero = timeContainer.getAttribute('data-time-numero');
    
    // Se o time já existe no banco (tem timeId válido), salvar imediatamente
    if (timeId && !timeId.toString().startsWith('novo_')) {
        $.ajax({
            url: '../ajax/adicionar_integrante_time.php',
            method: 'POST',
            data: {
                time_id: parseInt(timeId),
                participante_id: parseInt(participanteId)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Adicionar participante ao DOM
                    const participanteHtml = criarHtmlParticipante(participanteId, nome, foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    
                    // Atualizar visibilidade do botão
                    if (timeNumero) {
                        atualizarVisibilidadeBotaoAdicionar(timeNumero);
                    }
                    
                    // Recarregar lista de participantes disponíveis no modal
                    recarregarListaParticipantesDisponiveis();
                    
                    showAlert('Participante adicionado ao time!', 'success');
                } else {
                    showAlert(response.message || 'Erro ao adicionar participante.', 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao adicionar participante ao time.', 'danger');
            }
        });
    } else {
        // Se o time ainda não existe, criar o time primeiro e depois adicionar o participante
        $.ajax({
            url: '../ajax/adicionar_integrante_time.php',
            method: 'POST',
            data: {
                time_id: 0,
                participante_id: parseInt(participanteId),
                torneio_id: <?php echo $torneio_id; ?>,
                time_numero: parseInt(timeNumero)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Atualizar o data-time-id do card com o ID retornado
                    if (response.time_id && timeCard) {
                        timeCard.setAttribute('data-time-id', response.time_id);
                        // Atualizar o ID do container também
                        timeContainer.id = 'time-' + response.time_id;
                    }
                    
                    // Adicionar participante ao DOM
                    const participanteHtml = criarHtmlParticipante(participanteId, nome, foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    
                    // Atualizar visibilidade do botão
                    if (timeNumero) {
                        atualizarVisibilidadeBotaoAdicionar(timeNumero);
                    }
                    
                    // Recarregar lista de participantes disponíveis no modal
                    recarregarListaParticipantesDisponiveis();
                    
                    showAlert('Participante adicionado ao time!', 'success');
                } else {
                    showAlert(response.message || 'Erro ao adicionar participante.', 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao adicionar participante ao time.', 'danger');
            }
        });
    }
}

function recarregarListaParticipantesDisponiveis() {
    // Recarregar lista de participantes disponíveis no modal se estiver aberto
    const modalElement = document.getElementById('modalAdicionarIntegrante');
    if (!modalElement) return;
    
    // Verificar se o modal está visível
    const modalInstance = bootstrap.Modal.getInstance(modalElement);
    if (!modalInstance) return;
    
    // Verificar se o modal está aberto (Bootstrap 5)
    const isShown = modalElement.classList.contains('show') || modalElement.style.display === 'block';
    if (!isShown) return;
    
    // Recarregar a lista via AJAX
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeAtualId || 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarParticipanteAoTimeSelecionado(' + p.id + ', \'' + p.nome.replace(/'/g, "\\'") + '\', \'' + (p.foto_perfil || '') + '\'); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                            fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
            }
        },
        error: function() {
            // Silenciar erro, não mostrar alerta
        }
    });
}

function adicionarIntegranteAoTime(timeId, participanteId) {
    $.ajax({
        url: '../ajax/adicionar_integrante_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            participante_id: participanteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                var modalElement = document.getElementById('modalAdicionarIntegrante');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) modalInstance.hide();
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao adicionar integrante', 'danger');
        }
    });
}

function removerIntegrante(timeId, participanteId) {
    if (!confirm('Remover este integrante do time?')) return;
    
    $.ajax({
        url: '../ajax/remover_integrante_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            participante_id: participanteId
        },
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
            showAlert('Erro ao remover integrante', 'danger');
        }
    });
}

function removerParticipante(participanteId) {
    if (!confirm('Remover este participante do torneio?')) return;
    
    $.ajax({
        url: '../ajax/remover_participante_torneio.php',
        method: 'POST',
        data: { participante_id: participanteId },
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
            showAlert('Erro ao remover participante', 'danger');
        }
    });
}

// Função para alternar inscrições
function toggleInscricoes() {
    var btn = document.getElementById('btnToggleInscricoes');
    var inscricoesAtuais = btn.classList.contains('btn-success');
    var novaSituacao = inscricoesAtuais ? 0 : 1;
    
    $.ajax({
        url: '../ajax/toggle_inscricoes_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            inscricoes_abertas: novaSituacao
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Atualizar botão
                if (response.inscricoes_abertas == 1) {
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-success');
                    btn.querySelector('i').classList.remove('fa-lock');
                    btn.querySelector('i').classList.add('fa-unlock');
                    btn.title = 'Inscrições Abertas - Clique para fechar';
                } else {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-success');
                    btn.querySelector('i').classList.remove('fa-unlock');
                    btn.querySelector('i').classList.add('fa-lock');
                    btn.title = 'Inscrições Fechadas - Clique para abrir';
                }
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao atualizar inscrições', 'danger');
        }
    });
}

// Função para carregar solicitações pendentes
function carregarSolicitacoes() {
    var container = document.getElementById('listaSolicitacoes');
    if (!container) {
        console.error('Container listaSolicitacoes não encontrado');
        return;
    }
    
    $.ajax({
        url: '../ajax/listar_solicitacoes_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            console.log('Resposta do servidor:', response);
            if (response.success) {
                var solicitacoes = response.solicitacoes || [];
                var badge = document.getElementById('badgeSolicitacoes');
                
                if (badge) {
                    badge.textContent = solicitacoes.length;
                }
                
                if (solicitacoes.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center mb-0">Nenhuma solicitação pendente.</p>';
                } else {
                    var html = '<div class="list-group">';
                    solicitacoes.forEach(function(sol) {
                        var avatar = sol.foto_perfil || '';
                        // Ajustar caminho da imagem
                        if (avatar && avatar.trim() !== '') {
                            if (avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
                                if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
                                    // Já tem caminho relativo, garantir que seja ../../assets/
                                    if (avatar.indexOf('../../assets/') !== 0) {
                                        if (avatar.indexOf('../assets/') === 0) {
                                            avatar = '../' + avatar;
                                        } else if (avatar.indexOf('assets/') === 0) {
                                            avatar = '../../' + avatar;
                                        }
                                    }
                                } else {
                                    avatar = '../../assets/arquivos/' + avatar;
                                }
                            }
                        } else {
                            avatar = '../../assets/arquivos/logo.png';
                        }
                        
                        var inicialNome = sol.usuario_nome ? sol.usuario_nome.charAt(0).toUpperCase() : '?';
                        
                        html += '<div class="list-group-item">';
                        html += '<div class="d-flex justify-content-between align-items-center">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<div style="position:relative;width:40px;height:40px;">';
                        html += '<img src="' + avatar + '" class="rounded-circle" width="40" height="40" style="object-fit:cover;position:absolute;top:0;left:0;" alt="' + (sol.usuario_nome || '') + '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                        html += '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:40px;height:40px;font-weight:bold;position:absolute;top:0;left:0;display:none;" title="' + (sol.usuario_nome || '') + '">' + inicialNome + '</div>';
                        html += '</div>';
                        html += '<div>';
                        html += '<strong>' + (sol.usuario_nome || 'Usuário') + '</strong><br>';
                        html += '<small class="text-muted">' + (sol.email || '') + '</small><br>';
                        html += '<small class="text-muted"><i class="fas fa-clock me-1"></i>' + new Date(sol.data_solicitacao).toLocaleString('pt-BR') + '</small>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="d-flex gap-2">';
                        html += '<button class="btn btn-sm btn-info" onclick="verPerfilUsuario(' + sol.usuario_id + ')" title="Ver Perfil">';
                        html += '<i class="fas fa-user"></i>';
                        html += '</button>';
                        html += '<button class="btn btn-sm btn-success" onclick="responderSolicitacao(' + sol.id + ', \'aprovar\')" title="Aprovar">';
                        html += '<i class="fas fa-check"></i>';
                        html += '</button>';
                        html += '<button class="btn btn-sm btn-danger" onclick="responderSolicitacao(' + sol.id + ', \'rejeitar\')" title="Rejeitar">';
                        html += '<i class="fas fa-times"></i>';
                        html += '</button>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                }
            } else {
                console.error('Erro na resposta:', response.message || 'Erro desconhecido');
                container.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar solicitações: ' + (response.message || 'Erro desconhecido') + '</p>';
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX:', status, error, xhr.responseText);
            container.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar solicitações. Verifique o console para mais detalhes.</p>';
        }
    });
}

// Função para responder solicitação
function responderSolicitacao(solicitacaoId, acao) {
    var acaoTexto = acao === 'aprovar' ? 'aprovar' : 'rejeitar';
    if (!confirm('Tem certeza que deseja ' + acaoTexto + ' esta solicitação?')) return;
    
    $.ajax({
        url: '../ajax/responder_solicitacao_torneio.php',
        method: 'POST',
        data: {
            solicitacao_id: solicitacaoId,
            acao: acao
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarSolicitacoes();
                // Recarregar página após 1 segundo para atualizar lista de participantes
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao responder solicitação', 'danger');
        }
    });
}

function limparTodosParticipantes() {
    if (!confirm('ATENÇÃO: Isso removerá TODOS os participantes do torneio. Esta ação não pode ser desfeita. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/limpar_todos_participantes_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
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
            showAlert('Erro ao limpar participantes', 'danger');
        }
    });
}

function criarTimes(btnElement) {
    if (!confirm('Isso criará os times baseado na configuração. Times existentes serão removidos. Deseja continuar?')) return;
    
    // Desabilitar botão para evitar duplo clique
    let btn = btnElement;
    let originalText = '';
    if (!btn && event && event.target) {
        btn = event.target;
    }
    if (btn) {
        originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';
    }
    
    $.ajax({
        url: '../ajax/criar_times_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        },
        error: function() {
            showAlert('Erro ao criar times', 'danger');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    });
}

function sortearTimes() {
    if (!confirm('Isso irá sortear todos os participantes nos times. Deseja continuar?')) return;
    
    // Obter todos os participantes
    const participantes = [];
    <?php foreach ($participantes as $p): ?>
    <?php
    // Preparar foto do participante
    $fotoParticipante = '';
    if (!empty($p['usuario_foto'])) {
        $fotoParticipante = $p['usuario_foto'];
        // Corrigir caminho se necessário
        if (strpos($fotoParticipante, '../assets/') !== 0 && strpos($fotoParticipante, 'assets/') === 0) {
            $fotoParticipante = '../' . $fotoParticipante;
        } elseif (strpos($fotoParticipante, 'http') !== 0 && strpos($fotoParticipante, '/') !== 0 && strpos($fotoParticipante, '../assets/') !== 0) {
            $fotoParticipante = '../assets/arquivos/' . $fotoParticipante;
        }
    }
    $nomeParticipante = $p['usuario_nome'] ?? $p['nome_avulso'] ?? 'Participante #' . $p['id'];
    ?>
    participantes.push({
        id: <?php echo $p['id']; ?>,
        nome: '<?php echo addslashes($nomeParticipante); ?>',
        foto: '<?php echo addslashes($fotoParticipante); ?>'
    });
    <?php endforeach; ?>
    
    if (participantes.length === 0) {
        showAlert('Não há participantes para sortear.', 'warning');
        return;
    }
    
    // Embaralhar participantes
    for (let i = participantes.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [participantes[i], participantes[j]] = [participantes[j], participantes[i]];
    }
    
    // Distribuir nos times
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    if (integrantesPorTime === 0) {
        showAlert('Configure a quantidade de integrantes por time primeiro.', 'warning');
        return;
    }
    
    // Verificar se há times suficientes
    const totalVagas = quantidadeTimes * integrantesPorTime;
    if (participantes.length > totalVagas) {
        showAlert('Há mais participantes (' + participantes.length + ') do que vagas disponíveis (' + totalVagas + ').', 'warning');
        return;
    }
    
    // Limpar todos os times
    document.querySelectorAll('.time-participantes').forEach(function(container) {
        container.innerHTML = '';
    });
    
    // Distribuir participantes de forma equilibrada
    let participanteIndex = 0;
    
    // Função auxiliar para encontrar o container do time
    function encontrarTimeContainer(timeNum) {
        // Tentar pelo data-time-numero primeiro (mais confiável)
        let container = document.querySelector('[data-time-numero="' + timeNum + '"]');
        if (container) return container;
        
        // Tentar pelos IDs
        container = document.querySelector('#time-novo_' + timeNum);
        if (container) return container;
        
        container = document.querySelector('#time-' + timeNum);
        if (container) return container;
        
        // Tentar encontrar qualquer time com o número
        const allContainers = document.querySelectorAll('.time-participantes');
        for (let i = 0; i < allContainers.length; i++) {
            const num = parseInt(allContainers[i].getAttribute('data-time-numero'));
            if (num === timeNum) {
                return allContainers[i];
            }
        }
        
        return null;
    }
    
    // Primeiro, distribuir até preencher todos os times com a quantidade mínima
    for (let timeNum = 1; timeNum <= quantidadeTimes && participanteIndex < participantes.length; timeNum++) {
        const timeContainer = encontrarTimeContainer(timeNum);
        if (!timeContainer) continue;
        
        // Distribuir integrantesPorTime participantes neste time
        for (let i = 0; i < integrantesPorTime && participanteIndex < participantes.length; i++) {
            const p = participantes[participanteIndex];
            const participanteHtml = criarHtmlParticipante(p.id, p.nome, p.foto);
            timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
            participanteIndex++;
        }
    }
    
    // Se ainda sobraram participantes, distribuir de forma rotativa
    if (participanteIndex < participantes.length) {
        let timeNum = 1;
        let tentativas = 0;
        const maxTentativas = quantidadeTimes * integrantesPorTime * 2; // Evitar loop infinito
        
        while (participanteIndex < participantes.length && tentativas < maxTentativas) {
            const timeContainer = encontrarTimeContainer(timeNum);
            
            if (timeContainer) {
                // Verificar quantos participantes já tem neste time
                const participantesNoTime = timeContainer.querySelectorAll('.participante-item').length;
                
                // Se ainda pode adicionar mais um
                if (participantesNoTime < integrantesPorTime) {
                    const p = participantes[participanteIndex];
                    const participanteHtml = criarHtmlParticipante(p.id, p.nome, p.foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    participanteIndex++;
                }
            }
            
            timeNum++;
            if (timeNum > quantidadeTimes) timeNum = 1;
            tentativas++;
        }
    }
    
    // Salvar automaticamente no banco de dados
    const torneioId = <?php echo $torneio_id; ?>;
    
    $.ajax({
        url: '../ajax/sortear_times_torneio.php',
        method: 'POST',
        data: {
            torneio_id: torneioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message || 'Participantes sorteados e salvos com sucesso!', 'success');
                // Recarregar página após 1 segundo para atualizar a interface
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message || 'Erro ao salvar sorteio', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar sorteio no banco de dados', 'danger');
        }
    });
}

function criarHtmlParticipante(participanteId, nome, foto) {
    // Corrigir caminho da foto
    let avatar = foto && foto !== '' ? foto : '../../assets/arquivos/logo.png';
    
    // Se não começa com http ou /, ajustar caminho
    if (avatar && avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
        if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
            // Já tem assets/, garantir que comece com ../../
            if (avatar.indexOf('../../') !== 0) {
                if (avatar.indexOf('../') === 0) {
            avatar = '../' + avatar;
                } else {
                    avatar = '../../' + avatar;
                }
            }
        } else {
            // Apenas nome do arquivo, adicionar caminho completo
            avatar = '../../assets/arquivos/' + avatar;
        }
    }
    
    return '<div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" ' +
           'data-participante-id="' + participanteId + '" onclick="event.stopPropagation(); selecionarParticipante(this, event)" ' +
           'style="cursor: pointer; user-select: none; -webkit-user-select: none;" ' +
           'oncontextmenu="return false;">' +
           '<div class="d-flex align-items-center gap-2">' +
           '<img src="' + avatar + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">' +
           '<small>' + nome + '</small>' +
           '</div>' +
           '<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removerParticipanteDoTime(this)">' +
           '<i class="fas fa-times"></i>' +
           '</button>' +
           '</div>';
}

function limparTimes() {
    if (!confirm('Tem certeza que deseja excluir TODOS os times e seus integrantes? Esta ação não pode ser desfeita!')) {
        return;
    }
    
    $.ajax({
        url: '../ajax/limpar_times_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
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
            showAlert('Erro ao limpar times', 'danger');
        }
    });
}

function salvarTimes() {
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    // Coletar dados de todos os times
    const timesData = [];
    
    for (let timeNum = 1; timeNum <= quantidadeTimes; timeNum++) {
        let timeContainer = document.querySelector('#time-novo_' + timeNum + ', #time-' + timeNum);
        if (!timeContainer) {
            // Tentar encontrar pelo data-time-numero
            timeContainer = document.querySelector('[data-time-numero="' + timeNum + '"]');
        }
        
        if (!timeContainer) continue;
        
        const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
        const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
        
        const participantes = [];
        timeContainer.querySelectorAll('.participante-item').forEach(function(item) {
            const participanteId = item.getAttribute('data-participante-id');
            if (participanteId) {
                participantes.push(parseInt(participanteId));
            }
        });
        
        timesData.push({
            time_id: timeId && timeId !== 'novo_' + timeNum ? parseInt(timeId) : null,
            numero: timeNum,
            participantes: participantes
        });
    }
    
    $.ajax({
        url: '../ajax/salvar_times_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            times_data: JSON.stringify(timesData)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Bloquear botões após salvar
                $('.times-action-btn').prop('disabled', true);
                $('#btnEditarTimes').show();
                // Scroll para o topo e recarregar após salvar
                setTimeout(function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar times', 'danger');
        }
    });
}

function atualizarVisibilidadeBotaoAdicionar(timeNumero) {
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    const timeContainer = document.querySelector('[data-time-numero="' + timeNumero + '"]');
    
    if (!timeContainer) return;
    
    const totalIntegrantes = timeContainer.querySelectorAll('.participante-item').length;
    const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    const btnId = timeId ? 'btn-adicionar-' + timeId : 'btn-adicionar-novo_' + timeNumero;
    const btn = document.getElementById(btnId);
    
    if (!btn) return;
    
    // Se não há limite configurado (0), sempre mostrar o botão
    // Se há limite, mostrar apenas se não atingiu o limite
    if (integrantesPorTime === 0) {
        btn.style.display = 'block';
    } else {
        if (totalIntegrantes >= integrantesPorTime) {
            btn.style.display = 'none';
        } else {
            btn.style.display = 'block';
        }
    }
}

function removerParticipanteDoTime(button) {
    if (!confirm('Remover este participante do time?')) return;
    
    const item = button.closest('.participante-item');
    if (!item) return;
    
    const participanteId = item.getAttribute('data-participante-id');
    const timeContainer = item.closest('.time-participantes');
    const timeNumero = timeContainer ? timeContainer.getAttribute('data-time-numero') : null;
    const timeCard = timeContainer ? timeContainer.closest('.col-md-6, .col-lg-4') : null;
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    
    // Se não tem timeId válido, apenas remover do DOM (será salvo quando clicar em Salvar Times)
    if (!timeId || timeId.toString().startsWith('novo_')) {
        item.remove();
        if (timeNumero) {
            atualizarVisibilidadeBotaoAdicionar(timeNumero);
        }
        return;
    }
    
    // Se tem timeId válido, remover do banco imediatamente
    $.ajax({
        url: '../ajax/remover_integrante_time.php',
        method: 'POST',
        data: {
            time_id: parseInt(timeId),
            participante_id: parseInt(participanteId)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                item.remove();
                if (timeNumero) {
                    atualizarVisibilidadeBotaoAdicionar(timeNumero);
                }
                showAlert('Participante removido do time.', 'success');
            } else {
                showAlert(response.message || 'Erro ao remover participante.', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao remover participante do time.', 'danger');
        }
    });
}

// Sistema de seleção e troca por clique
let participanteSelecionado = null;
let timeOrigemSelecionado = null;

function selecionarParticipante(elemento, evt) {
    // Prevenir propagação do evento
    if (evt) {
        evt.stopPropagation();
        // Se clicou no botão de remover, não fazer nada
        if (evt.target.closest('button')) {
            return;
        }
    }
    
    const participante = elemento.closest('.participante-item');
    if (!participante) return;
    
    const timeAtual = participante.closest('.time-participantes');
    
    // Se já tem um participante selecionado
    if (participanteSelecionado) {
        // Se clicou no mesmo participante, deselecionar
        if (participanteSelecionado === participante) {
            participanteSelecionado.classList.remove('selecionado');
            participanteSelecionado = null;
            timeOrigemSelecionado = null;
            return;
        }
        
        // Se clicou em outro participante, fazer troca
        const timeDestino = participante.closest('.time-participantes');
        const timeOrigem = participanteSelecionado.closest('.time-participantes');
        
        // Se está no mesmo time, apenas trocar a ordem
        if (timeOrigem === timeDestino) {
            // Trocar posição exata no mesmo time
            const participante1 = participanteSelecionado;
            const participante2 = participante;
            const parent = participante1.parentNode;
            
            // Criar marcadores invisíveis para preservar posições exatas
            const marcador1 = document.createElement('span');
            marcador1.style.display = 'none';
            marcador1.style.visibility = 'hidden';
            marcador1.style.position = 'absolute';
            
            const marcador2 = document.createElement('span');
            marcador2.style.display = 'none';
            marcador2.style.visibility = 'hidden';
            marcador2.style.position = 'absolute';
            
            // Inserir marcadores ANTES de remover os participantes
            const proximo1 = participante1.nextSibling;
            const proximo2 = participante2.nextSibling;
            
            parent.insertBefore(marcador1, proximo1);
            parent.insertBefore(marcador2, proximo2);
            
            // Remover participantes
            participante1.remove();
            participante2.remove();
            
            // Inserir na posição trocada usando os marcadores
            parent.insertBefore(participante2, marcador1);
            parent.insertBefore(participante1, marcador2);
            
            // Remover marcadores
            marcador1.remove();
            marcador2.remove();
        } else {
            // Trocar entre times diferentes
            trocarParticipantesEntreTimes(participanteSelecionado, participante, timeOrigem, timeDestino);
        }
        
        // Deselecionar
        participanteSelecionado.classList.remove('selecionado');
        participanteSelecionado = null;
        timeOrigemSelecionado = null;
    } else {
        // Selecionar o participante
        participanteSelecionado = participante;
        timeOrigemSelecionado = timeAtual;
        participante.classList.add('selecionado');
    }
}

function trocarParticipantesEntreTimes(participante1, participante2, timeOrigem, timeDestino) {
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    
    // Verificar se o time de destino está cheio
    const totalIntegrantesDestino = timeDestino.querySelectorAll('.participante-item').length;
    const totalIntegrantesOrigem = timeOrigem.querySelectorAll('.participante-item').length;
    
    // Obter a posição exata do participante2 no time de destino
    const proximoParticipante2 = participante2.nextSibling;
    
    // Se o time de destino está cheio e não é o mesmo time, fazer troca
    if (integrantesPorTime > 0 && totalIntegrantesDestino >= integrantesPorTime && timeOrigem !== timeDestino) {
        // Obter a posição exata do participante1 no time de origem
        const proximoParticipante1 = participante1.nextSibling;
        
        // Remover ambos
        participante1.remove();
        participante2.remove();
        
        // Inserir na posição exata do outro
        if (proximoParticipante2) {
            timeDestino.insertBefore(participante1, proximoParticipante2);
        } else {
            timeDestino.appendChild(participante1);
        }
        
        if (proximoParticipante1) {
            timeOrigem.insertBefore(participante2, proximoParticipante1);
        } else {
            timeOrigem.appendChild(participante2);
        }
    } else if (timeOrigem !== timeDestino) {
        // Se não está cheio, apenas mover para a posição exata
        participante1.remove();
        
        if (proximoParticipante2) {
            timeDestino.insertBefore(participante1, proximoParticipante2);
        } else {
            timeDestino.appendChild(participante1);
        }
    }
}

// Deselecionar ao clicar fora
document.addEventListener('click', function(event) {
    if (participanteSelecionado && !event.target.closest('.participante-item')) {
        participanteSelecionado.classList.remove('selecionado');
        participanteSelecionado = null;
        timeOrigemSelecionado = null;
    }
});
function excluirTime(timeId) {
    if (!confirm('Tem certeza que deseja excluir este time?')) return;
    
    $.ajax({
        url: '../ajax/excluir_time_torneio.php',
        method: 'POST',
        data: { time_id: timeId },
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
            showAlert('Erro ao excluir time', 'danger');
        }
    });
}

function excluirTorneio(torneioId) {
    if (!confirm('Tem certeza que deseja excluir este torneio?\n\nEsta ação não pode ser desfeita e excluirá todos os participantes e times associados.')) return;
    
    $.ajax({
        url: '../ajax/excluir_torneio.php',
        method: 'POST',
        data: { torneio_id: torneioId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = '../torneios.php';
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao excluir torneio', 'danger');
        }
    });
}

// Formulário adicionar participante
$('#formAdicionarParticipante').on('submit', function(e) {
    e.preventDefault();
    
    <?php if ($tipoTorneio === 'grupo'): ?>
    var participantes = $('input[name="participantes[]"]:checked');
    if (participantes.length === 0) {
        showAlert('Selecione pelo menos um participante.', 'warning');
        return;
    }
    
    // Validar quantidade máxima
    if (maxParticipantesTorneio > 0) {
        var totalAposAdicao = totalParticipantesAtual + participantes.length;
        if (totalAposAdicao > maxParticipantesTorneio) {
            showAlert('O limite máximo de participantes (' + maxParticipantesTorneio + ') será excedido. Selecione menos participantes.', 'danger');
            return;
        }
    }
    <?php endif; ?>
    
    $.ajax({
        url: '../ajax/adicionar_participante_torneio.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                var modalElement = document.getElementById('modalAdicionarParticipante');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) modalInstance.hide();
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao adicionar participante', 'danger');
        }
    });
});

// Atualizar checkbox "marcar todos" quando checkboxes individuais mudarem
$(document).on('change', '.participante-checkbox', function() {
    validarQuantidadeMaxima();
    
    // Atualizar estado do checkbox "marcar todos"
    const checkboxMarcarTodos = document.getElementById('marcarTodos');
    if (checkboxMarcarTodos) {
        const totalCheckboxes = document.querySelectorAll('.participante-checkbox').length;
        const totalMarcados = document.querySelectorAll('.participante-checkbox:checked').length;
        checkboxMarcarTodos.checked = (totalMarcados === totalCheckboxes && totalCheckboxes > 0);
        checkboxMarcarTodos.indeterminate = (totalMarcados > 0 && totalMarcados < totalCheckboxes);
    }
});

// Função para editar configurações
function editarConfiguracoes() {
    $('.config-field').prop('disabled', false);
    $('#btnSalvarConfig').show();
    $('#btnEditarConfig').hide();
}

// Função para editar times
function editarTimes() {
    $('.times-action-btn').prop('disabled', false);
    $('#btnEditarTimes').hide();
}

// Função para mostrar/ocultar campo de quantidade de grupos
function toggleQuantidadeGrupos() {
    if ($('#modalidade_todos_chaves').is(':checked') && !$('#modalidade_todos_chaves').prop('disabled')) {
        $('#divQuantidadeGrupos').show();
        $('input[name="quantidade_grupos"]').prop('required', true);
    } else {
        $('#divQuantidadeGrupos').hide();
        $('input[name="quantidade_grupos"]').prop('required', false);
    }
}

// Função para gerar eliminatórias
function gerarEliminatorias() {
    if (!confirm('Tem certeza que deseja gerar as chaves eliminatórias?\n\nApós gerar, não será mais possível editar os resultados da fase de grupos.')) return;
    
    $.ajax({
        url: '../ajax/gerar_eliminatorias.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar eliminatórias', 'danger');
        }
    });
}

// Inicializar ao carregar página
// Função para ver perfil do usuário
function verPerfilUsuario(usuarioId) {
    const modal = new bootstrap.Modal(document.getElementById('modalVerPerfil'));
    const conteudo = document.getElementById('conteudoPerfil');
    
    // Mostrar loading
    conteudo.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
    modal.show();
    
    // Buscar detalhes do usuário
    $.ajax({
        url: '../ajax/obter_detalhes_usuario.php',
        method: 'GET',
        data: { usuario_id: usuarioId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.usuario) {
                const u = response.usuario;
                
                // Ajustar caminho da foto
                let avatar = u.foto_perfil || '../../assets/arquivos/logo.png';
                if (avatar && avatar.trim() !== '') {
                    if (avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
                        if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
                            if (avatar.indexOf('../../assets/') !== 0) {
                                if (avatar.indexOf('../assets/') === 0) {
                                    avatar = '../' + avatar;
                                } else if (avatar.indexOf('assets/') === 0) {
                                    avatar = '../../' + avatar;
                                }
                            }
                        } else {
                            avatar = '../../assets/arquivos/' + avatar;
                        }
                    }
                } else {
                    avatar = '../../assets/arquivos/logo.png';
                }
                
                const inicialNome = u.nome ? u.nome.charAt(0).toUpperCase() : '?';
                const dataNasc = u.data_aniversario ? new Date(u.data_aniversario).toLocaleDateString('pt-BR') : 'Não informado';
                const dataCadastro = u.data_cadastro ? new Date(u.data_cadastro).toLocaleDateString('pt-BR') : 'Não informado';
                
                let html = '<div class="text-center mb-4">';
                html += '<div style="position:relative;display:inline-block;">';
                html += '<img src="' + avatar + '" class="rounded-circle" width="100" height="100" style="object-fit:cover;border:3px solid #007bff;" alt="' + (u.nome || '') + '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                html += '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:100px;height:100px;font-weight:bold;font-size:2.5rem;border:3px solid #007bff;display:none;position:absolute;top:0;left:50%;transform:translateX(-50%);" title="' + (u.nome || '') + '">' + inicialNome + '</div>';
                html += '</div>';
                html += '<h4 class="mt-3 mb-0">' + (u.nome || 'Usuário') + '</h4>';
                html += '</div>';
                
                html += '<div class="list-group">';
                html += '<div class="list-group-item"><strong><i class="fas fa-envelope me-2"></i>E-mail:</strong><br>' + (u.email || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-phone me-2"></i>Telefone:</strong><br>' + (u.telefone || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-star me-2"></i>Nível:</strong><br>' + (u.nivel || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-venus-mars me-2"></i>Gênero:</strong><br>' + (u.genero || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-birthday-cake me-2"></i>Data de Nascimento:</strong><br>' + dataNasc + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-trophy me-2"></i>Reputação:</strong><br>' + (u.reputacao || 0) + ' pontos</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-calendar me-2"></i>Data de Cadastro:</strong><br>' + dataCadastro + '</div>';
                html += '</div>';
                
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do usuário.</div>';
            }
        },
        error: function() {
            conteudo.innerHTML = '<div class="alert alert-danger">Erro ao buscar dados do usuário.</div>';
        }
    });
}

$(document).ready(function() {
    toggleQuantidadeGrupos();
    <?php if ($inscricoes_abertas || $tem_solicitacoes_pendentes): ?>
    carregarSolicitacoes();
    <?php endif; ?>
});

// Formulário modalidade do torneio
$('#formModalidadeTorneio').on('submit', function(e) {
    e.preventDefault();
    
    // Validar quantidade de grupos se for modalidade todos_chaves
    if ($('#modalidade_todos_chaves').is(':checked')) {
        const quantidadeGrupos = $('input[name="quantidade_grupos"]:checked').val();
        if (!quantidadeGrupos) {
            showAlert('Selecione a quantidade de chaves', 'danger');
            return;
        }
        
        // Verificar se o radio está desabilitado (número ímpar de times)
        if ($('#modalidade_todos_chaves').prop('disabled')) {
            showAlert('Não é possível criar chaves com número ímpar de times', 'danger');
            return;
        }
    }
    
    $.ajax({
        url: '../ajax/configurar_modalidade_torneio.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Mostrar botão Iniciar Jogos após salvar
                $('#btnIniciarJogos').show();
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar formato', 'danger');
        }
    });
});

// Função para iniciar jogos (gerar todos contra todos)
function iniciarJogos() {
    if (!confirm('Isso gerará todos os jogos de enfrentamento entre os times. Deseja continuar?')) return;
    
    // Recolher as seções de Informações, Configurações e Formato
    recolherSecoesTorneio();
    
    // Verificar se é modalidade todos_chaves e validar quantidade de times
    const modalidade = '<?php echo $torneio['modalidade'] ?? ''; ?>';
    const quantidadeGrupos = $('input[name="quantidade_grupos"]:checked').val() || <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
    const quantidadeTimes = <?php echo $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0); ?>;
    
    if (modalidade === 'todos_chaves' && quantidadeGrupos > 0) {
        // Verificar se o radio está desabilitado (número ímpar de times)
        if ($('#modalidade_todos_chaves').prop('disabled')) {
            showAlert('Não é possível criar chaves com número ímpar de times', 'danger');
            return;
        }
        
        // Verificar se a quantidade de times é divisível pela quantidade de chaves
        if (quantidadeTimes % quantidadeGrupos !== 0) {
            showAlert(
                'A quantidade total de times (' + quantidadeTimes + ') não é divisível pela quantidade de chaves (' + quantidadeGrupos + ').\n\n' +
                'Para criar chaves, a quantidade de times deve ser divisível pela quantidade de chaves.',
                'danger'
            );
            return;
        }
    }
    
    $.ajax({
        url: '../ajax/iniciar_jogos_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao iniciar jogos', 'danger');
        }
    });
}

// Função para habilitar edição da partida
function habilitarEdicaoPartida(partidaId) {
    // Verificar se eliminatórias foram geradas
    var temEliminatorias = <?php echo isset($tem_eliminatorias) && $tem_eliminatorias ? 'true' : 'false'; ?>;
    if (temEliminatorias) {
        showAlert('Não é possível editar partidas após gerar as eliminatórias.', 'warning');
        return;
    }
    // Habilitar apenas campos de pontos (status será sempre Finalizada ao salvar)
    $('#pontos_time1_' + partidaId).prop('disabled', false).prop('readonly', false);
    $('#pontos_time2_' + partidaId).prop('disabled', false).prop('readonly', false);
    
    // Status sempre será Finalizada, então não precisa habilitar o campo
    // Mas vamos definir o valor como Finalizada no select oculto
    $('#status_' + partidaId).val('Finalizada');
    
    // Mostrar botão salvar e esconder editar
    $('#btn_salvar_' + partidaId).show();
    $('#btn_editar_' + partidaId).hide();
}

// Função para salvar resultado inline
function salvarResultadoPartidaInline(partidaId) {
    const pontosTime1 = parseInt($('#pontos_time1_' + partidaId).val()) || 0;
    const pontosTime2 = parseInt($('#pontos_time2_' + partidaId).val()) || 0;
    // Sempre finalizar ao salvar
    const status = 'Finalizada';
    
    if (pontosTime1 < 0 || pontosTime2 < 0) {
        showAlert('Os pontos não podem ser negativos', 'danger');
        return;
    }
    
    // Atualizar o select para mostrar "Finalizada"
    $('#status_' + partidaId).val('Finalizada');
    
    $.ajax({
        url: '../ajax/salvar_resultado_partida.php',
        method: 'POST',
        data: {
            partida_id: partidaId,
            pontos_time1: pontosTime1,
            pontos_time2: pontosTime2,
            status: status
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                
                // Bloquear campos novamente (disabled e readonly)
                $('#pontos_time1_' + partidaId).prop('disabled', true).prop('readonly', true);
                $('#pontos_time2_' + partidaId).prop('disabled', true).prop('readonly', true);
                $('#status_' + partidaId).prop('disabled', true);
                
                // Esconder botão salvar e mostrar editar
                $('#btn_salvar_' + partidaId).hide();
                $('#btn_editar_' + partidaId).show();
                
                // Recarregar após 1 segundo para atualizar classificação
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar resultado', 'danger');
        }
    });
}

// Função para habilitar edição da chave eliminatória
function habilitarEdicaoChave(chaveId) {
    $('#pontos_time1_chave_' + chaveId).prop('disabled', false).prop('readonly', false);
    $('#pontos_time2_chave_' + chaveId).prop('disabled', false).prop('readonly', false);
    
    // Mostrar botão salvar e esconder editar
    $('#btn_salvar_chave_' + chaveId).show();
    $('#btn_editar_chave_' + chaveId).hide();
}

// Função para salvar resultado da chave eliminatória
function salvarResultadoChave(chaveId) {
    const pontosTime1 = parseInt($('#pontos_time1_chave_' + chaveId).val()) || 0;
    const pontosTime2 = parseInt($('#pontos_time2_chave_' + chaveId).val()) || 0;
    const status = 'Finalizada';
    
    if (pontosTime1 < 0 || pontosTime2 < 0) {
        showAlert('Os pontos não podem ser negativos', 'danger');
        return;
    }
    
    $.ajax({
        url: '../ajax/salvar_resultado_chave.php',
        method: 'POST',
        data: {
            chave_id: chaveId,
            pontos_time1: pontosTime1,
            pontos_time2: pontosTime2,
            status: status
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                
                // Desabilitar campos novamente
                $('#pontos_time1_chave_' + chaveId).prop('disabled', true).prop('readonly', true);
                $('#pontos_time2_chave_' + chaveId).prop('disabled', true).prop('readonly', true);
                
                // Esconder botão salvar e mostrar editar
                $('#btn_salvar_chave_' + chaveId).hide();
                $('#btn_editar_chave_' + chaveId).show();
                
                // Recarregar após 1 segundo para atualizar final e 3º lugar
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar resultado', 'danger');
        }
    });
}

// Função antiga mantida para compatibilidade (redireciona para nova)
function editarChave(chaveId) {
    habilitarEdicaoChave(chaveId);
}

// Função para imprimir enfrentamentos
function imprimirEnfrentamentos() {
    try {
        var elemento = document.getElementById('tabela-enfrentamentos');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        
        // Criar uma cópia do elemento para modificar sem afetar o original
        var clone = elemento.cloneNode(true);
        
        // Remover botões e elementos de ação
        var elementosRemover = clone.querySelectorAll('.btn, button, .badge, select, .form-select, .no-print');
        elementosRemover.forEach(function(el) {
            el.remove();
        });
        
        // Remover ícones (FontAwesome e imagens)
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        // Remover divs de cor dos times (quadrados coloridos)
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        // Remover cores de fundo e bordas coloridas de outros elementos
        var elementosComCor = clone.querySelectorAll('[style*="border-left"], [style*="background-color"]');
        elementosComCor.forEach(function(el) {
            var style = el.getAttribute('style') || '';
            style = style.replace(/border-left[^;]*;?/gi, '');
            style = style.replace(/background-color[^;]*;?/gi, '');
            if (style.trim()) {
                el.setAttribute('style', style);
            } else {
                el.removeAttribute('style');
            }
        });
        
        // Processar inputs de placar: deixar em branco na impressão
        var containersPlacar = clone.querySelectorAll('.d-flex.align-items-center.gap-1.justify-content-center');
        containersPlacar.forEach(function(container) {
            var inputs = container.querySelectorAll('input[type="number"]');
            if (inputs.length === 2) {
                // Deixar placar em branco
                container.innerHTML = '&nbsp;&nbsp;&nbsp; x &nbsp;&nbsp;&nbsp;';
            }
        });
        
        // Remover inputs restantes que não foram processados
        var inputsRestantes = clone.querySelectorAll('input[type="number"]');
        inputsRestantes.forEach(function(input) {
            input.remove();
        });
        
        // Remover colunas Status e Ações - encontrar índices primeiro
        var headers = clone.querySelectorAll('thead th');
        var indicesRemover = [];
        headers.forEach(function(th, index) {
            var texto = th.textContent.trim().toLowerCase();
            if (texto === 'status' || texto === 'ações' || texto === 'acoes') {
                indicesRemover.push(index);
                th.remove();
            }
        });
        
        // Remover células correspondentes em ordem reversa para manter índices corretos
        if (indicesRemover.length > 0) {
            indicesRemover.sort(function(a, b) { return b - a; }); // Ordenar decrescente
            var linhas = clone.querySelectorAll('tbody tr, thead tr');
            linhas.forEach(function(linha) {
                var celulas = linha.querySelectorAll('td, th');
                indicesRemover.forEach(function(indice) {
                    if (celulas[indice]) {
                        celulas[indice].remove();
                    }
                });
            });
        }
        
        // Limpar espaços em branco e elementos vazios
        var elementosVazios = clone.querySelectorAll('div:empty, span:empty');
        elementosVazios.forEach(function(el) {
            if (!el.textContent.trim()) {
                el.remove();
            }
        });
        
        // Reorganizar estrutura para chaves lado a lado
        var linhas = clone.querySelectorAll('tbody tr');
        var chaves = {};
        var chaveAtual = null;
        var rodadaAtual = null;
        
        linhas.forEach(function(linha) {
            var classes = linha.className;
            var texto = linha.textContent.trim();
            
            // Verificar se é cabeçalho de chave
            if (classes.includes('table-info')) {
                var matchChave = texto.match(/chave\s*(\d+)/i);
                if (matchChave) {
                    chaveAtual = 'Chave ' + matchChave[1];
                } else {
                    chaveAtual = texto.replace(/[^\w\s]/g, '').trim() || 'Chave 1';
                }
                if (!chaves[chaveAtual]) {
                    chaves[chaveAtual] = {};
                }
                rodadaAtual = null;
            }
            // Verificar se é cabeçalho de rodada
            else if (classes.includes('table-secondary')) {
                var matchRodada = texto.match(/rodada\s*(\d+)/i);
                if (matchRodada) {
                    rodadaAtual = 'Rodada ' + matchRodada[1];
                } else {
                    rodadaAtual = texto.replace(/[^\w\s]/g, '').trim() || 'Rodada 1';
                }
                if (chaveAtual && !chaves[chaveAtual][rodadaAtual]) {
                    chaves[chaveAtual][rodadaAtual] = [];
                }
            }
            // É uma linha de partida
            else if (chaveAtual && rodadaAtual) {
                if (!chaves[chaveAtual][rodadaAtual]) {
                    chaves[chaveAtual][rodadaAtual] = [];
                }
                chaves[chaveAtual][rodadaAtual].push(linha.outerHTML);
            }
        });
        
        // Se não encontrou chaves, usar estrutura original
        var conteudo = '';
        if (Object.keys(chaves).length === 0) {
            conteudo = clone.innerHTML;
        } else {
            // Criar nova estrutura com chaves lado a lado
            var chavesArray = Object.keys(chaves);
            var maxRodadas = 0;
            
            // Encontrar o máximo de rodadas em qualquer chave
            chavesArray.forEach(function(chave) {
                var rodadas = Object.keys(chaves[chave]);
                maxRodadas = Math.max(maxRodadas, rodadas.length);
            });
            
            // Criar tabela com colunas para cada chave
            conteudo = '<table style="width: 100%; border-collapse: collapse;">';
            
            // Cabeçalho com nomes das chaves
            conteudo += '<thead><tr>';
            chavesArray.forEach(function(chave) {
                conteudo += '<th style="width: ' + (100 / chavesArray.length) + '%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f2f2f2;"><strong>' + chave + '</strong></th>';
            });
            conteudo += '</tr></thead>';
            
            // Corpo com rodadas alinhadas
            conteudo += '<tbody>';
            for (var r = 1; r <= maxRodadas; r++) {
                var rodadaNome = 'Rodada ' + r;
                var temRodada = false;
                
                // Verificar se alguma chave tem esta rodada
                chavesArray.forEach(function(chave) {
                    if (chaves[chave][rodadaNome]) {
                        temRodada = true;
                    }
                });
                
                if (temRodada) {
                    // Linha de cabeçalho da rodada
                    conteudo += '<tr>';
                    chavesArray.forEach(function(chave) {
                        if (chaves[chave][rodadaNome]) {
                            conteudo += '<td style="border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #e9ecef;"><strong>' + rodadaNome + '</strong></td>';
                        } else {
                            conteudo += '<td style="border: 1px solid #ddd; padding: 6px;"></td>';
                        }
                    });
                    conteudo += '</tr>';
                    
                    // Linhas de partidas - encontrar o máximo de partidas nesta rodada
                    var maxPartidas = 0;
                    chavesArray.forEach(function(chave) {
                        if (chaves[chave][rodadaNome]) {
                            maxPartidas = Math.max(maxPartidas, chaves[chave][rodadaNome].length);
                        }
                    });
                    
                    for (var p = 0; p < maxPartidas; p++) {
                        conteudo += '<tr>';
                        chavesArray.forEach(function(chave) {
                            if (chaves[chave][rodadaNome] && chaves[chave][rodadaNome][p]) {
                                // Extrair apenas o conteúdo das células (sem a tag tr)
                                var linhaHTML = chaves[chave][rodadaNome][p];
                                
                                // Processar HTML como string para extrair texto das células
                                var time1 = '';
                                var placar = 'x';
                                var time2 = '';
                                
                                // Extrair texto de cada célula usando regex
                                var matchCelulas = linhaHTML.match(/<td[^>]*>([\s\S]*?)<\/td>/gi);
                                if (matchCelulas) {
                                    matchCelulas.forEach(function(celulaHTML, idx) {
                                        // Remover tags HTML e extrair apenas texto
                                        var texto = celulaHTML.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                                        
                                        if (idx === 0 && texto) {
                                            // Primeira célula: Time 1
                                            time1 = texto;
                                        } else if (idx === 1 && texto) {
                                            // Segunda célula: Placar (já processado como "x")
                                            placar = texto.includes('x') ? 'x' : texto;
                                        } else if (idx === 2 && texto) {
                                            // Terceira célula: Time 2
                                            time2 = texto;
                                        }
                                    });
                                }
                                
                                // Criar estrutura com Time 1 à esquerda, x no meio, Time 2 à direita
                                var textoCompleto = '<table style="width: 100%; border-collapse: collapse;"><tr>' +
                                    '<td style="text-align: left; width: 40%; padding: 0;">' + time1 + '</td>' +
                                    '<td style="text-align: center; width: 20%; padding: 0;">&nbsp; x &nbsp;</td>' +
                                    '<td style="text-align: right; width: 40%; padding: 0;">' + time2 + '</td>' +
                                    '</tr></table>';
                                
                                // Criar uma única célula com todo o conteúdo
                                conteudo += '<td style="border: 1px solid #ddd; padding: 6px; white-space: nowrap;">' + textoCompleto + '</td>';
                            } else {
                                // Célula vazia
                                conteudo += '<td style="border: 1px solid #ddd; padding: 6px;"></td>';
                            }
                        });
                        conteudo += '</tr>';
                    }
                }
            }
            conteudo += '</tbody></table>';
        }
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Jogos de Enfrentamento';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 11px; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; font-size: 16px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }');
        janela.document.write('th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; }');
        janela.document.write('td { background-color: #fff !important; }');
        janela.document.write('.table-info { background-color: #f2f2f2 !important; }');
        janela.document.write('.table-info td { text-align: center !important; }');
        janela.document.write('.table-secondary { background-color: #f2f2f2 !important; }');
        janela.document.write('.table-secondary td { text-align: center !important; }');
        janela.document.write('td { white-space: nowrap; }');
        janela.document.write('td div { display: inline !important; margin: 0 3px !important; }');
        janela.document.write('td strong { display: inline !important; }');
        janela.document.write('td span { display: inline !important; }');
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write(conteudo);
        janela.document.write('</body></html>');
        janela.document.close();
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir enfrentamentos:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para imprimir times
function imprimirTimes() {
    try {
        var container = document.getElementById('containerTimes');
        if (!container) {
            alert('Elemento não encontrado!');
            return;
        }
        
        // Buscar todos os cards de times
        var cards = container.querySelectorAll('.col-md-6.col-lg-4');
        if (cards.length === 0) {
            alert('Nenhum time encontrado para imprimir!');
            return;
        }
        
        var totalTimes = cards.length;
        var colunasPorLinha = totalTimes >= 6 ? 3 : 2; // 3 colunas se 6+ times, senão 2
        var larguraColuna = colunasPorLinha === 3 ? '33.33%' : '50%';
        
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Times do Torneio';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 11px; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 15px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; font-size: 18px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; page-break-inside: avoid; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; padding: 8px; border: 1px solid #ddd; text-align: left; }');
        janela.document.write('td { padding: 6px 8px; border: 1px solid #ddd; }');
        janela.document.write('.time-header { background-color: #e9ecef; font-weight: bold; }');
        janela.document.write('.integrante-name { padding-left: 20px; }');
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write('<table>');
        
        // Processar times conforme o layout definido
        for (var i = 0; i < cards.length; i += colunasPorLinha) {
            janela.document.write('<tr>');
            
            // Processar até colunasPorLinha times por linha
            for (var col = 0; col < colunasPorLinha; col++) {
                var idx = i + col;
                
                if (idx < cards.length) {
                    var card = cards[idx];
                    var timeHeader = card.querySelector('.card-header strong');
                    var timeName = timeHeader ? timeHeader.textContent.trim() : 'Time ' + (idx + 1);
                    var integrantes = card.querySelectorAll('.participante-item small, .participante-item .d-flex small');
                    
                    janela.document.write('<td style="width: ' + larguraColuna + '; vertical-align: top;">');
                    janela.document.write('<table style="width: 100%;">');
                    janela.document.write('<tr><th class="time-header">' + timeName + '</th></tr>');
                    if (integrantes.length > 0) {
                        for (var j = 0; j < integrantes.length; j++) {
                            var nome = integrantes[j].textContent.trim();
                            if (nome) {
                                janela.document.write('<tr><td class="integrante-name">' + nome + '</td></tr>');
                            }
                        }
                    } else {
                        janela.document.write('<tr><td class="integrante-name">Nenhum integrante</td></tr>');
                    }
                    janela.document.write('</table>');
                    janela.document.write('</td>');
                } else {
                    // Se não houver time nesta posição, deixar célula vazia
                    janela.document.write('<td style="width: ' + larguraColuna + ';"></td>');
                }
            }
            
            janela.document.write('</tr>');
        }
        
        janela.document.write('</table>');
        janela.document.write('</body></html>');
        janela.document.close();
        
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir times:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para imprimir classificação
function imprimirClassificacao() {
    try {
        var elemento = document.getElementById('tabela-classificacao');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        // Criar uma cópia do elemento para modificar sem afetar o original
        var clone = elemento.cloneNode(true);
        
        // Remover ícones (FontAwesome e imagens)
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        // Remover divs de cor dos times (quadrados coloridos)
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        // Remover cores de fundo e bordas coloridas de outros elementos
        var elementosComCor = clone.querySelectorAll('[style*="border-left"], [style*="background-color"]');
        elementosComCor.forEach(function(el) {
            var style = el.getAttribute('style') || '';
            style = style.replace(/border-left[^;]*;?/gi, '');
            style = style.replace(/background-color[^;]*;?/gi, '');
            if (style.trim()) {
                el.setAttribute('style', style);
            } else {
                el.removeAttribute('style');
            }
        });
        
        var conteudo = clone.innerHTML;
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Classificação Geral';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 12px; } .no-print { display: none !important; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }');
        janela.document.write('th, td { border: 1px solid #ddd; padding: 8px; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; text-align: center; }');
        janela.document.write('td { background-color: #fff !important; text-align: center; }');
        janela.document.write('td:first-child { text-align: left; }'); // Primeira coluna (Posição) alinhada à esquerda
        janela.document.write('td:nth-child(2) { text-align: left; }'); // Segunda coluna (Time) alinhada à esquerda
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write(conteudo);
        janela.document.write('</body></html>');
        janela.document.close();
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir classificação:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para limpar todos os jogos do torneio
function limparJogos() {
    if (!confirm('Tem certeza que deseja limpar todos os jogos?\n\nEsta ação não pode ser desfeita e excluirá todos os jogos, resultados e classificação do torneio.')) return;
    
    $.ajax({
        url: '../ajax/limpar_jogos_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
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
            showAlert('Erro ao limpar jogos', 'danger');
        }
    });
}

// Função para encerrar torneio
function encerrarTorneio() {
    if (!confirm('Tem certeza que deseja encerrar este torneio?\n\nApós encerrado, o torneio ficará apenas para visualização e não poderá mais ser editado (exceto pelo botão Editar Torneio).')) return;
    
    $.ajax({
        url: '../ajax/encerrar_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao encerrar torneio', 'danger');
        }
    });
}

// Função para calcular participantes necessários
function calcularParticipantesNecessarios() {
    const tipoTime = document.getElementById('tipo_time');
    const quantidadeTimes = parseInt(document.getElementById('quantidade_times').value) || 0;
    const integrantesInput = document.getElementById('integrantes_por_time');
    const maxParticipantesInput = document.getElementById('max_participantes');
    const infoParticipantes = document.getElementById('info_participantes_necessarios');
    const participantesNecessarios = document.getElementById('participantes_necessarios');
    
    if (tipoTime && tipoTime.value) {
        const selectedOption = tipoTime.options[tipoTime.selectedIndex];
        const integrantes = parseInt(selectedOption.getAttribute('data-integrantes')) || 0;
        
        // Atualizar campo oculto
        if (integrantesInput) {
            integrantesInput.value = integrantes;
        }
        
        // Calcular e atualizar quantidade máxima de participantes
        if (quantidadeTimes > 0 && integrantes > 0) {
            const totalNecessario = quantidadeTimes * integrantes;
            
            // Atualizar campo de quantidade máxima de participantes
            if (maxParticipantesInput) {
                maxParticipantesInput.value = totalNecessario;
            }
            
            // Atualizar texto de participantes necessários
            if (participantesNecessarios) {
                participantesNecessarios.textContent = totalNecessario;
            }
            if (infoParticipantes) {
                infoParticipantes.style.display = 'block';
            }
        } else {
            if (maxParticipantesInput) {
                maxParticipantesInput.value = 0;
            }
            if (infoParticipantes) {
                infoParticipantes.style.display = 'none';
            }
        }
    } else {
        if (integrantesInput) {
            integrantesInput.value = '';
        }
        if (maxParticipantesInput) {
            maxParticipantesInput.value = 0;
        }
        if (infoParticipantes) {
            infoParticipantes.style.display = 'none';
        }
    }
}

// Calcular ao carregar a página
$(document).ready(function() {
    calcularParticipantesNecessarios();
});

// Formulário configurar torneio (inclui participantes, times, etc)
$('#formConfigTorneio').on('submit', function(e) {
    e.preventDefault();
    
    // Garantir que integrantes_por_time está atualizado antes de enviar
    calcularParticipantesNecessarios();
    
    // Garantir que integrantes_por_time está preenchido
    const tipoTime = $('#tipo_time').val();
    const integrantesPorTime = $('#integrantes_por_time').val();
    const quantidadeTimes = $('#quantidade_times').val();
    
    if (!tipoTime) {
        showAlert('Selecione o tipo de time', 'danger');
        return;
    }
    
    if (!integrantesPorTime || integrantesPorTime <= 0) {
        showAlert('Erro: Tipo de time não configurado corretamente. Recarregue a página e tente novamente.', 'danger');
        return;
    }
    
    if (!quantidadeTimes || quantidadeTimes < 2) {
        showAlert('Informe a quantidade de times (mínimo 2)', 'danger');
        return;
    }
    
    $.ajax({
        url: '../ajax/configurar_torneio.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Bloquear campos após salvar
                $('.config-field').prop('disabled', true);
                $('#btnSalvarConfig').hide();
                $('#btnEditarConfig').show();
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar configurações', 'danger');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

