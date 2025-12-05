<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Gerenciar Torneio';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
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


// Query básica - funciona mesmo sem nome_avulso
$sql = "SELECT tp.id, tp.torneio_id, tp.usuario_id, tp.data_inscricao";
if ($tem_nome_avulso) {
    $sql .= ", tp.nome_avulso";
}
if ($tem_ordem) {
    $sql .= ", tp.ordem";
}
$sql .= ", u.nome AS usuario_nome, u.foto_perfil AS usuario_foto, u.genero AS usuario_genero
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

// Buscar times ordenados por ordem
$sql = "SELECT * FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times = $stmt ? $stmt->fetchAll() : [];


// Corrigir ordens duplicadas se houver
if (count($times) > 0) {
    $ordens = array_column($times, 'ordem');
    $ordensUnicas = array_unique($ordens);
    if (count($ordens) !== count($ordensUnicas)) {
        error_log("AVISO: Times com ordens duplicadas encontradas no torneio ID: " . $torneio_id);
        // Corrigir ordens duplicadas
        $ordensUsadas = [];
        foreach ($times as &$time) {
            $ordemOriginal = (int)$time['ordem'];
            if (in_array($ordemOriginal, $ordensUsadas)) {
                // Encontrar próxima ordem disponível
                $novaOrdem = $ordemOriginal;
                while (in_array($novaOrdem, $ordensUsadas)) {
                    $novaOrdem++;
                }
                // Atualizar no banco
                $sqlUpdate = "UPDATE torneio_times SET ordem = ? WHERE id = ?";
                executeQuery($pdo, $sqlUpdate, [$novaOrdem, $time['id']]);
                $time['ordem'] = $novaOrdem;
            }
            $ordensUsadas[] = (int)$time['ordem'];
        }
        unset($time);
        // Reordenar array após correção
        usort($times, function($a, $b) {
            return (int)$a['ordem'] <=> (int)$b['ordem'];
        });
    }
}

// Buscar integrantes dos times
foreach ($times as &$time) {
    $sql = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil, u.genero AS usuario_genero
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
    
    // Ordenar por ID da inserção para manter a ordem que foi salva
    $sql .= " ORDER BY tti.id ASC";
    
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $integrantes = $stmt ? $stmt->fetchAll() : [];
    $time['integrantes'] = $integrantes;
}

include '../includes/header.php';
?>

<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i>Gerenciar Torneio: <?php echo htmlspecialchars($torneio['nome']); ?>
        </h2>
        <div>
            <a href="../torneios.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <button class="btn btn-danger" onclick="excluirTorneio(<?php echo $torneio_id; ?>)">
                <i class="fas fa-trash me-1"></i>Excluir Torneio
            </button>
        </div>
    </div>
</div>

<!-- Informações do Torneio -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoInformacoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoInformacoes"></i>
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Torneio</h5>
                </div>
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
                        $maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
                        echo count($participantes); ?> / <?php echo (int)$maxParticipantes; 
                        ?>
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
            <div class="card-header">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoConfiguracoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoConfiguracoes"></i>
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Configurações do Torneio</h5>
                </div>
            </div>
            <div class="card-body" id="corpoSecaoConfiguracoes">
                <form id="formConfigTorneio">
                    <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="max_participantes" class="form-label">Quantidade Máxima de Participantes</label>
                            <input type="number" class="form-control" id="max_participantes" name="max_participantes" 
                                   min="1" value="<?php echo (int)($torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0); ?>">
                            <small class="text-muted">Atual: <?php echo count($participantes); ?> participantes</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantidade_times" class="form-label">Quantidade de Times</label>
                            <input type="number" class="form-control" id="quantidade_times" name="quantidade_times" 
                                   min="2" value="<?php echo (int)($torneio['quantidade_times'] ?? 0); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="integrantes_por_time" class="form-label">Integrantes por Time</label>
                            <input type="number" class="form-control" id="integrantes_por_time" name="integrantes_por_time" 
                                   min="1" value="<?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>">
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-save me-1"></i>Salvar Configurações
                                </button>
                                <?php 
                                $quantidadeTimes = $torneio['quantidade_times'] ?? null;
                                $integrantesPorTime = $torneio['integrantes_por_time'] ?? null;
                                if (!empty($participantes) && $quantidadeTimes && $integrantesPorTime): 
                                ?>
                                    <button type="button" class="btn btn-success" onclick="criarTimes(this)">
                                        <i class="fas fa-magic me-1"></i>Criar Times
                                    </button>
                                <?php endif; ?>
                            </div>
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
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Participantes</h5>
                </div>
                <button class="btn btn-sm btn-primary" onclick="abrirModalAdicionarParticipante()">
                    <i class="fas fa-plus me-1"></i>Adicionar
                </button>
            </div>
            <div class="card-body" id="corpoListaParticipantes" style="display: none;">
                <div id="listaParticipantes">
                    <?php 
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
                                        
                                        if ($temUsuario): 
                                            $avatar = $p['usuario_foto'] ?: '../assets/arquivos/logo.png';
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
</div>

<!-- Times do Torneio -->
<?php 
$quantidadeTimes = $torneio['quantidade_times'] ?? 0;
if ($quantidadeTimes > 0): 
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                <div>
                    <button class="btn btn-sm btn-warning me-2" onclick="sortearTimes()">
                        <i class="fas fa-random me-1"></i>Sortear
                    </button>
                    <button class="btn btn-sm btn-success" onclick="salvarTimes()">
                        <i class="fas fa-save me-1"></i>Salvar Times
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="containerTimes">
                    <?php 
                    // Buscar times existentes ou criar estrutura baseada na quantidade
                    $timesExistentes = [];
                    $cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6610f2'];
                    
                    if (!empty($times)) {
                        // Criar array indexado por ordem para facilitar busca
                        // Garantir que os integrantes sejam preservados
                        $timesPorOrdem = [];
                        foreach ($times as $time) {
                            $ordem = (int)$time['ordem'];
                            // Se já existe um time com esta ordem, manter apenas o primeiro (mais antigo)
                            // Mas garantir que os integrantes sejam preservados
                            if (!isset($timesPorOrdem[$ordem])) {
                                // Fazer uma cópia completa do time incluindo todos os campos e integrantes
                                // Usar array_merge para garantir cópia profunda
                                $timeCompleto = [
                                    'id' => $time['id'],
                                    'nome' => $time['nome'],
                                    'cor' => $time['cor'],
                                    'ordem' => $time['ordem'],
                                    'integrantes' => isset($time['integrantes']) && is_array($time['integrantes']) ? $time['integrantes'] : []
                                ];
                                $timesPorOrdem[$ordem] = $timeCompleto;
                            }
                        }
                        
                        // Verificar se há times no banco que não foram mapeados (pode ter ordem diferente ou NULL)
                        // Buscar todos os times do torneio novamente para garantir que não perdemos nenhum
                        $sql_verificar = "SELECT id, ordem FROM torneio_times WHERE torneio_id = ?";
                        $stmt_verificar = executeQuery($pdo, $sql_verificar, [$torneio_id]);
                        $todos_times_banco = $stmt_verificar ? $stmt_verificar->fetchAll() : [];
                        foreach ($todos_times_banco as $tb) {
                            $ordem_banco = (int)$tb['ordem'];
                            // Se a ordem está entre 1 e quantidadeTimes e não foi mapeada, buscar dados completos
                            if (!isset($timesPorOrdem[$ordem_banco]) && $ordem_banco >= 1 && $ordem_banco <= $quantidadeTimes) {
                                // Buscar dados completos deste time
                                $sql_time_completo = "SELECT * FROM torneio_times WHERE id = ?";
                                $stmt_time_completo = executeQuery($pdo, $sql_time_completo, [$tb['id']]);
                                $time_completo_banco = $stmt_time_completo ? $stmt_time_completo->fetch() : false;
                                if ($time_completo_banco) {
                                    // Buscar integrantes deste time
                                    $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil, u.genero AS usuario_genero
                                                        FROM torneio_time_integrantes tti
                                                        JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                                        LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                                        WHERE tti.time_id = ?
                                                        ORDER BY tti.id ASC";
                                    $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$tb['id']]);
                                    $integrantes_time = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
                                    
                                    $time_completo_banco['integrantes'] = $integrantes_time;
                                    $timesPorOrdem[$ordem_banco] = $time_completo_banco;
                                }
                            }
                        }
                        
                        // Preencher com times existentes e criar os faltantes
                        for ($i = 1; $i <= $quantidadeTimes; $i++) {
                            if (isset($timesPorOrdem[$i])) {
                                // Usar o time completo com integrantes (garantir que está presente)
                                $timeParaAdicionar = $timesPorOrdem[$i];
                                // Garantir que integrantes existe e é um array
                                if (!isset($timeParaAdicionar['integrantes']) || !is_array($timeParaAdicionar['integrantes'])) {
                                    $timeParaAdicionar['integrantes'] = [];
                                }
                                
                                // Fazer uma cópia explícita para garantir que os dados sejam preservados
                                $timesExistentes[] = [
                                    'id' => $timeParaAdicionar['id'],
                                    'nome' => $timeParaAdicionar['nome'],
                                    'cor' => $timeParaAdicionar['cor'],
                                    'ordem' => $timeParaAdicionar['ordem'],
                                    'integrantes' => $timeParaAdicionar['integrantes'] // Preservar array de integrantes
                                ];
                            } else {
                                // Criar time vazio para esta ordem
                                $timesExistentes[] = [
                                    'id' => null,
                                    'nome' => 'Time ' . $i,
                                    'cor' => $cores[($i - 1) % count($cores)],
                                    'ordem' => $i,
                                    'integrantes' => []
                                ];
                            }
                        }
                    } else {
                        // Criar estrutura vazia para os times
                        for ($i = 1; $i <= $quantidadeTimes; $i++) {
                            $timesExistentes[] = [
                                'id' => null,
                                'nome' => 'Time ' . $i,
                                'cor' => $cores[($i - 1) % count($cores)],
                                'ordem' => $i,
                                'integrantes' => []
                            ];
                        }
                    }
                    ?>
                    <?php foreach ($timesExistentes as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3" data-time-id="<?php echo $time['id'] ?? 'novo_' . $time['ordem']; ?>">
                            <div class="card h-100" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                    <button class="btn btn-sm btn-outline-warning" onclick="limparTime(<?php echo $time['id'] ?? 'null'; ?>, <?php echo $time['ordem']; ?>)" title="Limpar participantes deste time">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                </div>
                                <div class="card-body" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                                    <div class="mb-2">
                                        <small class="text-muted">Clique em um participante para selecionar, depois clique em outro para trocar</small>
                                    </div>
                                    <div id="time-<?php echo $time['id'] ?? 'novo_' . $time['ordem']; ?>" class="time-participantes" 
                                         data-time-numero="<?php echo $time['ordem']; ?>"
                                         style="min-height: 150px;">
                                        <?php 
                                        // Verificar se há integrantes (usar count para garantir)
                                        $temIntegrantes = isset($time['integrantes']) && is_array($time['integrantes']) && count($time['integrantes']) > 0;
                                        if ($temIntegrantes): ?>
                                            <?php foreach ($time['integrantes'] as $integ): ?>
                                                <div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" 
                                                     data-participante-id="<?php echo $integ['participante_id']; ?>"
                                                     onclick="event.stopPropagation(); selecionarParticipante(this, event)"
                                                     style="cursor: pointer; user-select: none; -webkit-user-select: none;"
                                                     oncontextmenu="return false;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($integ['usuario_id']): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../') !== 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../assets/arquivos/' . $avatar;
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
                                    <button class="btn btn-sm btn-outline-primary w-100 mt-2 btn-adicionar-participante" 
                                            id="btn-adicionar-<?php echo $time['id'] ?? 'novo_' . $time['ordem']; ?>"
                                            data-time-numero="<?php echo $time['ordem']; ?>"
                                            onclick="adicionarParticipanteAoTime(<?php echo $time['id'] ?? 'null'; ?>, <?php echo $time['ordem']; ?>)">
                                        <i class="fas fa-user-plus me-1"></i>Adicionar Participante
                                    </button>
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

<!-- Times (versão antiga - manter para compatibilidade) -->
<?php if (!empty($times) && $quantidadeTimes == 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                <button class="btn btn-sm btn-warning" onclick="sortearTimes()">
                    <i class="fas fa-random me-1"></i>Sortear Times
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirTime(<?php echo $time['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                                                            $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
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
                                    <button class="btn btn-sm btn-outline-primary w-100" onclick="adicionarIntegranteTime(<?php echo $time['id']; ?>)">
                                        <i class="fas fa-user-plus me-1"></i>Adicionar Integrante
                                    </button>
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
                                <label class="form-label mb-0">Selecione os membros do grupo:</label>
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
                                                $avatar = $membro['foto_perfil'] ?: '../assets/arquivos/logo.png';
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
                            <input type="text" class="form-control" id="nome_avulso" name="nome_avulso" required>
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
let listaParticipantesExpandida = false; // Começa fechada
let secaoInformacoesExpandida = true; // Começa expandida
let secaoConfiguracoesExpandida = true; // Começa expandida

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
                            var fotoPerfil = p.foto_perfil || '../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                } else if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') !== 0) {
                                    fotoPerfil = '../assets/arquivos/' + fotoPerfil;
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
                            var fotoPerfil = p.foto_perfil || '../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                } else if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') !== 0) {
                                    fotoPerfil = '../assets/arquivos/' + fotoPerfil;
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
                            var fotoPerfil = p.foto_perfil || '../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                } else if (fotoPerfil.indexOf('../assets/') !== 0 && fotoPerfil.indexOf('assets/') !== 0) {
                                    fotoPerfil = '../assets/arquivos/' + fotoPerfil;
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
        foto: '<?php echo addslashes($fotoParticipante); ?>',
        genero: '<?php echo addslashes($p['usuario_genero'] ?? ''); ?>'
    });
    <?php endforeach; ?>
    
    if (participantes.length === 0) {
        showAlert('Não há participantes para sortear.', 'warning');
        return;
    }
    
    // Separar participantes por gênero
    const mulheres = participantes.filter(p => p.genero === 'Feminino').sort((a, b) => a.nome.localeCompare(b.nome));
    const homens = participantes.filter(p => p.genero !== 'Feminino').sort((a, b) => a.nome.localeCompare(b.nome));
    
    // Distribuir nos times
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    // Limpar todos os times
    document.querySelectorAll('.time-participantes').forEach(function(container) {
        container.innerHTML = '';
    });
    
    // Primeiro, garantir que todos os containers de times existem
    const timeContainers = [];
    for (let timeNum = 1; timeNum <= quantidadeTimes; timeNum++) {
        const timeContainer = document.querySelector('[data-time-numero="' + timeNum + '"]');
        if (timeContainer) {
            timeContainers.push({
                numero: timeNum,
                container: timeContainer,
                participantes: []
            });
        }
    }
    
    if (timeContainers.length === 0) {
        showAlert('Não foi possível encontrar os containers dos times.', 'danger');
        return;
    }
    
    // Calcular distribuição proporcional
    const totalParticipantes = participantes.length;
    const participantesPorTimeCalculado = integrantesPorTime > 0 ? integrantesPorTime : Math.ceil(totalParticipantes / quantidadeTimes);
    
    // Calcular quantas mulheres por time (proporcional)
    const mulheresPorTime = Math.floor(mulheres.length / quantidadeTimes);
    const mulheresRestantes = mulheres.length % quantidadeTimes;
    
    // Distribuir mulheres proporcionalmente (preferência para times 1 e 2)
    let indexMulheres = 0;
    for (let i = 0; i < timeContainers.length && indexMulheres < mulheres.length; i++) {
        const timeInfo = timeContainers[i];
        let mulheresParaEsteTime = mulheresPorTime;
        
        // Dar preferência aos primeiros times para mulheres restantes
        if (i < mulheresRestantes) {
            mulheresParaEsteTime++;
        }
        
        for (let j = 0; j < mulheresParaEsteTime && indexMulheres < mulheres.length; j++) {
            timeInfo.participantes.push(mulheres[indexMulheres]);
            indexMulheres++;
        }
    }
    
    // Distribuir homens para completar os times
    let indexHomens = 0;
    for (let i = 0; i < timeContainers.length && indexHomens < homens.length; i++) {
        const timeInfo = timeContainers[i];
        const totalNoTime = timeInfo.participantes.length;
        const faltam = participantesPorTimeCalculado - totalNoTime;
        
        for (let j = 0; j < faltam && indexHomens < homens.length; j++) {
            timeInfo.participantes.push(homens[indexHomens]);
            indexHomens++;
        }
    }
    
    // Se ainda sobraram participantes, distribuir nos times que ainda têm espaço
    const participantesRestantes = [];
    if (indexMulheres < mulheres.length) {
        participantesRestantes.push(...mulheres.slice(indexMulheres));
    }
    if (indexHomens < homens.length) {
        participantesRestantes.push(...homens.slice(indexHomens));
    }
    
    // Distribuir participantes restantes
    let indexRestantes = 0;
    while (indexRestantes < participantesRestantes.length) {
        for (let i = 0; i < timeContainers.length && indexRestantes < participantesRestantes.length; i++) {
            const timeInfo = timeContainers[i];
            if (timeInfo.participantes.length < participantesPorTimeCalculado || integrantesPorTime === 0) {
                timeInfo.participantes.push(participantesRestantes[indexRestantes]);
                indexRestantes++;
            }
        }
        // Proteção contra loop infinito
        if (indexRestantes === 0) break;
    }
    
    // Ordenar alfabeticamente dentro de cada time e adicionar ao DOM
    for (let i = 0; i < timeContainers.length; i++) {
        const timeInfo = timeContainers[i];
        // Ordenar alfabeticamente
        timeInfo.participantes.sort((a, b) => a.nome.localeCompare(b.nome));
        
        // Adicionar ao DOM
        timeInfo.participantes.forEach(function(p) {
            const participanteHtml = criarHtmlParticipante(p.id, p.nome, p.foto);
            timeInfo.container.insertAdjacentHTML('beforeend', participanteHtml);
        });
        
        // Atualizar visibilidade do botão após adicionar participantes
        atualizarVisibilidadeBotaoAdicionar(timeInfo.numero);
    }
    
    showAlert('Participantes sorteados proporcionalmente por gênero e ordenados alfabeticamente! Clique em "Salvar Times" para confirmar.', 'success');
}

function criarHtmlParticipante(participanteId, nome, foto) {
    // Corrigir caminho da foto
    let avatar = foto && foto !== '' ? foto : '../assets/arquivos/logo.png';
    
    // Se não começa com http ou /, ajustar caminho
    if (avatar && avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
        if (avatar.indexOf('../assets/') !== 0 && avatar.indexOf('assets/') === 0) {
            avatar = '../' + avatar;
        } else if (avatar.indexOf('../assets/') !== 0 && avatar.indexOf('assets/') !== 0) {
            avatar = '../assets/arquivos/' + avatar;
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

function salvarTimes() {
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    // Coletar dados de todos os times
    const timesData = [];
    const timesProcessados = new Set(); // Para evitar duplicatas
    
    for (let timeNum = 1; timeNum <= quantidadeTimes; timeNum++) {
        // Usar apenas o seletor data-time-numero que é mais confiável
        const timeContainer = document.querySelector('[data-time-numero="' + timeNum + '"]');
        
        if (!timeContainer) continue;
        
        // Verificar se já processamos este container para evitar duplicatas
        if (timesProcessados.has(timeContainer)) continue;
        timesProcessados.add(timeContainer);
        
        const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
        let timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
        
        // Se o timeId começa com "novo_", tentar buscar o ID real do time existente no banco
        // Verificar se há um time com esta ordem no banco
        if (!timeId || timeId.toString().startsWith('novo_')) {
            // O time pode existir no banco mas não ter o ID no DOM
            // Vamos buscar pelo data-time-numero que é mais confiável
            const timeNumero = timeContainer.getAttribute('data-time-numero');
            if (timeNumero) {
                // Tentar encontrar o ID do time pelo número/ordem
                // Isso será feito no backend, mas vamos marcar que precisa verificar
                timeId = null; // Forçar busca no backend
            }
        }
        
        const participantes = [];
        timeContainer.querySelectorAll('.participante-item').forEach(function(item) {
            const participanteId = item.getAttribute('data-participante-id');
            if (participanteId) {
                participantes.push(parseInt(participanteId));
            }
        });
        
        timesData.push({
            time_id: timeId && timeId !== 'novo_' + timeNum && !timeId.toString().startsWith('novo_') ? parseInt(timeId) : null,
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
                setTimeout(function() {
                    location.reload();
                }, 1000);
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

function atualizarTodosBotoesAdicionar() {
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    for (let timeNum = 1; timeNum <= quantidadeTimes; timeNum++) {
        atualizarVisibilidadeBotaoAdicionar(timeNum);
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

function limparTime(timeId, ordem) {
    if (!confirm('Deseja remover todos os participantes deste time?')) return;

    // Encontrar o container do time pelo data-time-numero
    const timeContainer = document.querySelector('[data-time-numero="' + ordem + '"]');
    if (timeContainer) {
        // Remover todos os participantes
        timeContainer.querySelectorAll('.participante-item').forEach(function(item) {
            item.remove();
        });
        atualizarVisibilidadeBotaoAdicionar(ordem);
        showAlert('Participantes removidos do time.', 'success');
    }
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
    
    const timeNumeroOrigem = timeOrigem.getAttribute('data-time-numero');
    const timeNumeroDestino = timeDestino.getAttribute('data-time-numero');
    
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
        
        // Atualizar visibilidade dos botões
        if (timeNumeroOrigem) atualizarVisibilidadeBotaoAdicionar(timeNumeroOrigem);
        if (timeNumeroDestino) atualizarVisibilidadeBotaoAdicionar(timeNumeroDestino);
    } else if (timeOrigem !== timeDestino) {
        // Se não está cheio, apenas mover para a posição exata
        participante1.remove();
        
        if (proximoParticipante2) {
            timeDestino.insertBefore(participante1, proximoParticipante2);
        } else {
            timeDestino.appendChild(participante1);
        }
        
        // Atualizar visibilidade dos botões
        if (timeNumeroOrigem) atualizarVisibilidadeBotaoAdicionar(timeNumeroOrigem);
        if (timeNumeroDestino) atualizarVisibilidadeBotaoAdicionar(timeNumeroDestino);
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

// Formulário configurar torneio (inclui participantes, times, etc)
$('#formConfigTorneio').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: '../ajax/configurar_torneio.php',
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
            showAlert('Erro ao salvar configurações', 'danger');
        }
    });
});

// Atualizar visibilidade dos botões quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarTodosBotoesAdicionar();
});

// Também atualizar após um pequeno delay para garantir que tudo foi renderizado
setTimeout(function() {
    atualizarTodosBotoesAdicionar();
}, 100);
</script>

<?php include '../includes/footer.php'; ?>



