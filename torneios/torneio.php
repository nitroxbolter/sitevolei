<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$titulo = 'Detalhes do Torneio';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$torneio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($torneio_id <= 0) {
    $_SESSION['mensagem'] = 'Torneio inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: torneios.php');
    exit();
}

// Carregar torneio
$sql = "SELECT t.*, g.nome AS grupo_nome, g.local_principal AS grupo_local, u.nome AS criado_por_nome
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
    header('Location: torneios.php');
    exit();
}

// Buscar participantes
$sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id
        WHERE tp.torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$participantes = $stmt ? $stmt->fetchAll() : [];

// Verificar se o usuário atual já está participando
$usuario_ja_participa = false;
foreach ($participantes as $p) {
    if (!empty($p['usuario_id']) && (int)$p['usuario_id'] === (int)$_SESSION['user_id']) {
        $usuario_ja_participa = true;
        break;
    }
}

// Verificar se o usuário tem uma solicitação pendente
$tem_solicitacao_pendente = false;
try {
    $sql_solicitacao = "SELECT id, status FROM torneio_solicitacoes WHERE torneio_id = ? AND usuario_id = ?";
    $stmt_solicitacao = executeQuery($pdo, $sql_solicitacao, [$torneio_id, $_SESSION['user_id']]);
    if ($stmt_solicitacao) {
        $solicitacao = $stmt_solicitacao->fetch();
        if ($solicitacao && $solicitacao['status'] === 'Pendente') {
            $tem_solicitacao_pendente = true;
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda
    $tem_solicitacao_pendente = false;
}

// Buscar times
$sql = "SELECT * FROM torneio_times WHERE torneio_id = ? ORDER BY ordem, nome";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times = $stmt ? $stmt->fetchAll() : [];

// Buscar integrantes dos times
foreach ($times as &$time) {
    $sql = "SELECT tp.*, u.nome AS usuario_nome, u.foto_perfil
            FROM torneio_time_integrantes tti
            JOIN torneio_participantes tp ON tp.id = tti.participante_id
            LEFT JOIN usuarios u ON u.id = tp.usuario_id
            WHERE tti.time_id = ?";
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
}

// Buscar partidas do torneio (para visualização)
$partidas = [];
$modalidade = $torneio['modalidade'] ?? null;
if ($modalidade) {
    $sql_check_partidas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
    $stmt_check = executeQuery($pdo, $sql_check_partidas, [$torneio_id]);
    $total_partidas_db = $stmt_check ? (int)$stmt_check->fetch()['total'] : 0;
    
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
    }
}

// Buscar classificação
$classificacao = [];
if ($modalidade) {
    $sql_classificacao = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                         FROM torneio_classificacao tc
                         JOIN torneio_times tt ON tt.id = tc.time_id
                         WHERE tc.torneio_id = ?
                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
    $stmt_classificacao = executeQuery($pdo, $sql_classificacao, [$torneio_id]);
    $classificacao = $stmt_classificacao ? $stmt_classificacao->fetchAll() : [];
}

// Buscar integrantes dos times para exibição nas partidas
$integrantes_por_time = [];
foreach ($partidas as $partida) {
    $time1_id = $partida['time1_id'] ?? null;
    $time2_id = $partida['time2_id'] ?? null;
    
    if ($time1_id && !isset($integrantes_por_time[$time1_id])) {
        $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                           FROM torneio_time_integrantes tti
                           JOIN torneio_participantes tp ON tp.id = tti.participante_id
                           LEFT JOIN usuarios u ON u.id = tp.usuario_id
                           WHERE tti.time_id = ?
                           ORDER BY tp.nome_avulso, u.nome";
        $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time1_id]);
        $integrantes_por_time[$time1_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
    }
    
    if ($time2_id && !isset($integrantes_por_time[$time2_id])) {
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

// Buscar chaves eliminatórias (semi-final, final, 3º lugar)
$chaves = [];
if ($modalidade === 'todos_chaves') {
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
}

// Verificar se final e 3º lugar estão finalizadas para mostrar pódio
$final_finalizada = false;
$terceiro_finalizado = false;
$vencedor_final = null;
$segundo_lugar = null;
$terceiro_lugar = null;

foreach ($chaves as $chave) {
    if (!empty($chave['fase']) && $chave['fase'] === 'Final' && !empty($chave['status']) && $chave['status'] === 'Finalizada' && !empty($chave['vencedor_id'])) {
        $final_finalizada = true;
        // Buscar dados do vencedor
        $sql_vencedor = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
        $stmt_vencedor = executeQuery($pdo, $sql_vencedor, [$chave['vencedor_id']]);
        $vencedor_final = $stmt_vencedor ? $stmt_vencedor->fetch() : null;
        
        // Buscar o perdedor (2º lugar)
        if (!empty($chave['time1_id']) && !empty($chave['time2_id'])) {
            $perdedor_id = ($chave['time1_id'] == $chave['vencedor_id']) ? $chave['time2_id'] : $chave['time1_id'];
            $sql_segundo = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
            $stmt_segundo = executeQuery($pdo, $sql_segundo, [$perdedor_id]);
            $segundo_lugar = $stmt_segundo ? $stmt_segundo->fetch() : null;
        }
    }
    if (!empty($chave['fase']) && $chave['fase'] === '3º Lugar' && !empty($chave['status']) && $chave['status'] === 'Finalizada' && !empty($chave['vencedor_id'])) {
        $terceiro_finalizado = true;
        // Buscar dados do 3º lugar
        $sql_terceiro = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
        $stmt_terceiro = executeQuery($pdo, $sql_terceiro, [$chave['vencedor_id']]);
        $terceiro_lugar = $stmt_terceiro ? $stmt_terceiro->fetch() : null;
    }
}

// Buscar integrantes dos times do pódio
$integrantes_podio = [];
if ($final_finalizada && $terceiro_finalizado && $vencedor_final && $segundo_lugar && $terceiro_lugar) {
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
}

include '../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i><?php echo htmlspecialchars($torneio['nome']); ?>
        </h2>
        <div>
            <a href="torneios.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <?php
            $sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
            $sou_admin_grupo = false;
            if ($torneio['grupo_id']) {
                $sql = "SELECT administrador_id FROM grupos WHERE id = ?";
                $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
                $grupo = $stmt ? $stmt->fetch() : false;
                $sou_admin_grupo = $grupo && ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
            }
            if ($sou_criador || $sou_admin_grupo || isAdmin($pdo, $_SESSION['user_id'])):
            ?>
                <a href="admin/gerenciar_torneio.php?id=<?php echo $torneio_id; ?>" class="btn btn-primary">
                    <i class="fas fa-cog me-1"></i>Gerenciar
                </a>
            <?php 
            else:
                // Verificar se o torneio está completo - usar a mesma lógica da exibição
                $maxParticipantes = (int)($torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0);
                $totalParticipantes = (int)count($participantes); // Usar o mesmo array $participantes já carregado
                // Verificar se está completo: máximo > 0 E total >= máximo
                $torneio_completo = false;
                if ($maxParticipantes > 0 && $totalParticipantes >= $maxParticipantes) {
                    $torneio_completo = true;
                }
                
                if ($inscricoes_abertas && !$usuario_ja_participa && !$tem_solicitacao_pendente): 
                    if ($torneio_completo): ?>
                        <span data-bs-toggle="tooltip" data-bs-placement="top" title="Vagas preenchidas">
                            <button class="btn btn-warning" disabled id="btnEntrarTorneio" style="pointer-events: none;">
                                <i class="fas fa-lock me-1"></i>Fechado
                            </button>
                        </span>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="entrarTorneio()" id="btnEntrarTorneio">
                            <i class="fas fa-sign-in-alt me-1"></i>Entrar
                        </button>
                    <?php endif; ?>
                <?php elseif ($tem_solicitacao_pendente): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-warning" disabled>
                            <i class="fas fa-clock me-1"></i>Aguardando Admin Aceitar
                        </button>
                        <button class="btn btn-outline-danger" onclick="cancelarSolicitacao()" id="btnCancelarSolicitacao">
                            <i class="fas fa-times me-1"></i>Cancelar Solicitação
                        </button>
                    </div>
                <?php endif; 
            endif; ?>
        </div>
    </div>
</div>

<!-- Informações do Torneio -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Torneio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-calendar me-2"></i>Data:</strong><br>
                        <?php 
                        $dataTorneio = $torneio['data_inicio'] ?? '';
                        echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'Não definida';
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-users me-2"></i>Quantidade de Jogadores:</strong><br>
                        <?php 
                        $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
                        echo (int)count($participantes);
                        if ($maxParticipantes > 0) {
                            echo ' / ' . (int)$maxParticipantes;
                        }
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Local:</strong><br>
                        <?php 
                        // Tentar obter local do grupo primeiro, depois do torneio
                        $local = 'Não definido';
                        if (!empty($torneio['grupo_local'])) {
                            $local = $torneio['grupo_local'];
                        } elseif (!empty($torneio['local'])) {
                            $local = $torneio['local'];
                        }
                        echo htmlspecialchars($local);
                        ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-tag me-2"></i>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $torneio['status'] === 'Inscrições Abertas' ? 'success' : 
                                ($torneio['status'] === 'Em Andamento' ? 'warning' : 
                                ($torneio['status'] === 'Criado' ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo htmlspecialchars($torneio['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-users me-2"></i>Grupo:</strong><br>
                        <?php echo htmlspecialchars($torneio['grupo_nome'] ?: 'Torneio Avulso'); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-user me-2"></i>Criado por:</strong><br>
                        <?php echo htmlspecialchars($torneio['criado_por_nome']); ?>
                    </div>
                </div>
                <?php if (!empty($torneio['descricao'])): ?>
                    <div class="mt-3">
                        <strong><i class="fas fa-align-left me-2"></i>Descrição:</strong>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($torneio['descricao'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Participantes</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($participantes)): ?>
                    <p class="text-muted mb-0">Nenhum participante inscrito ainda.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($participantes as $p): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($p['usuario_id']): ?>
                                        <?php
                                        $avatar = $p['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                        // Corrigir caminho da imagem (arquivo está em torneios/, precisa ../ para assets/)
                                        if (!empty($avatar)) {
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
                                             class="rounded-circle" width="32" height="32" 
                                             style="object-fit:cover;" alt="<?php echo htmlspecialchars($p['usuario_nome']); ?>">
                                        <span><?php echo htmlspecialchars($p['usuario_nome']); ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($p['nome_avulso']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Times do Torneio -->
<?php if (!empty($times)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header" style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($time['integrantes'])): ?>
                                        <p class="text-muted mb-0"><small>Nenhum integrante</small></p>
                                    <?php else: ?>
                                        <?php foreach ($time['integrantes'] as $integ): ?>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <?php if ($integ['usuario_id']): ?>
                                                    <?php
                                                    $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    // Corrigir caminho da imagem (arquivo está em torneios/, precisa ../ para assets/)
                                                    if (!empty($avatar)) {
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
                                                         style="object-fit:cover;" alt="<?php echo htmlspecialchars($integ['usuario_nome']); ?>">
                                                    <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                <?php else: ?>
                                                    <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
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

<!-- Jogos de Enfrentamento -->
<?php if (!empty($partidas)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-futbol me-2"></i>Jogos de Enfrentamento</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php 
                                $rodada_atual = 0;
                                $grupo_atual = null;
                                foreach ($partidas as $partida): 
                                    // Verificar se os dados necessários existem
                                    if (empty($partida['time1_id']) || empty($partida['time2_id'])) {
                                        continue; // Pular partidas sem times definidos
                                    }
                                    
                                    // Mostrar cabeçalho de grupo se mudou
                                    if ($modalidade === 'todos_chaves' && isset($partida['grupo_id']) && $partida['grupo_id'] != $grupo_atual):
                                        $grupo_atual = $partida['grupo_id'] ?? null;
                                ?>
                                    <tr class="table-info">
                                        <td colspan="4"><strong><?php echo htmlspecialchars($partida['grupo_nome'] ?? 'Chave'); ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                    <?php if (isset($partida['rodada']) && $partida['rodada'] != $rodada_atual):
                                        $rodada_atual = $partida['rodada'];
                                    ?>
                                    <tr class="table-secondary">
                                        <td colspan="4"><strong>Rodada <?php echo $rodada_atual; ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time1_nome'] ?? 'Time 1'); ?></strong>
                                            <?php if (!empty($integrantes_por_time[$partida['time1_id']])): ?>
                                                <?php 
                                                $primeiro_integ = $integrantes_por_time[$partida['time1_id']][0] ?? null;
                                                if ($primeiro_integ && !empty($primeiro_integ['usuario_id'])):
                                                    $avatar = $primeiro_integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    if (strpos($avatar, '../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                        if (strpos($avatar, '../') !== 0) {
                                                            if (strpos($avatar, '../') === 0) {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            } else {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            }
                                                        }
                                                    } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                        $avatar = '../assets/arquivos/' . $avatar;
                                                    }
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? ''); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? ''); ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($partida['status']) && $partida['status'] === 'Finalizada' && !empty($partida['vencedor_id']) && $partida['vencedor_id'] == $partida['time1_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo (int)($partida['pontos_time1'] ?? 0); ?> x <?php echo (int)($partida['pontos_time2'] ?? 0); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time2_nome'] ?? 'Time 2'); ?></strong>
                                            <?php if (!empty($integrantes_por_time[$partida['time2_id']])): ?>
                                                <?php 
                                                $primeiro_integ = $integrantes_por_time[$partida['time2_id']][0] ?? null;
                                                if ($primeiro_integ && !empty($primeiro_integ['usuario_id'])):
                                                    $avatar = $primeiro_integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    if (strpos($avatar, '../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                        if (strpos($avatar, '../') !== 0) {
                                                            if (strpos($avatar, '../') === 0) {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            } else {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            }
                                                        }
                                                    } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                        $avatar = '../assets/arquivos/' . $avatar;
                                                    }
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? ''); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? ''); ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($partida['status']) && $partida['status'] === 'Finalizada' && !empty($partida['vencedor_id']) && $partida['vencedor_id'] == $partida['time2_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo (!empty($partida['status']) && $partida['status'] === 'Finalizada') ? 'success' : ((!empty($partida['status']) && $partida['status'] === 'Em Andamento') ? 'warning' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars($partida['status'] ?? 'Agendada'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classificação Geral -->
<?php if (!empty($classificacao)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação Geral</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">Pos</th>
                                <th>Time</th>
                                <th class="text-center">Jogos</th>
                                <th class="text-center">V</th>
                                <th class="text-center">D</th>
                                <th class="text-center">PF</th>
                                <th class="text-center">PC</th>
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
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($class['time_nome'] ?? 'Time'); ?></strong>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo (int)($class['vitorias'] ?? 0) + (int)($class['derrotas'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['vitorias'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['derrotas'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['pontos_pro'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['pontos_contra'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int)($class['saldo_pontos'] ?? 0); ?></td>
                                    <td class="text-center"><?php echo number_format($class['average'] ?? 0, 2); ?></td>
                                    <td class="text-center"><strong><?php echo (int)($class['pontos_total'] ?? 0); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chaves Eliminatórias -->
<?php if (!empty($chaves)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Chaves Eliminatórias</h5>
            </div>
            <div class="card-body">
                <?php 
                $fases = ['Quartas', 'Semi', 'Final', '3º Lugar'];
                foreach ($fases as $fase):
                    $chaves_fase = array_filter($chaves, function($c) use ($fase) { return !empty($c['fase']) && $c['fase'] === $fase; });
                    if (!empty($chaves_fase)):
                ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th>
                                        <div class="d-inline-block">
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chaves_fase as $chave): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($chave['time1_nome'] ?? 'Aguardando'); ?></strong>
                                                <?php if (!empty($chave['status']) && $chave['status'] === 'Finalizada' && !empty($chave['vencedor_id']) && $chave['vencedor_id'] == $chave['time1_id']): ?>
                                                    <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($chave['status']) && $chave['status'] === 'Finalizada' && !empty($chave['time1_id']) && !empty($chave['time2_id'])): ?>
                                                <strong><?php echo (int)($chave['pontos_time1'] ?? 0); ?> x <?php echo (int)($chave['pontos_time2'] ?? 0); ?></strong>
                                            <?php elseif (!empty($chave['time1_id']) && !empty($chave['time2_id'])): ?>
                                                <span class="text-muted"><?php echo (int)($chave['pontos_time1'] ?? 0); ?> x <?php echo (int)($chave['pontos_time2'] ?? 0); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Aguardando</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($chave['time2_nome'] ?? 'Aguardando'); ?></strong>
                                                <?php if (!empty($chave['status']) && $chave['status'] === 'Finalizada' && !empty($chave['vencedor_id']) && $chave['vencedor_id'] == $chave['time2_id']): ?>
                                                    <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td></td>
                                        <td>
                                            <span class="badge bg-<?php echo (!empty($chave['status']) && $chave['status'] === 'Finalizada') ? 'success' : ((!empty($chave['status']) && $chave['status'] === 'Em Andamento') ? 'warning' : 'secondary'); ?>">
                                                <?php echo htmlspecialchars($chave['status'] ?? 'Agendada'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Pódio -->
<?php if ($final_finalizada && $terceiro_finalizado && $vencedor_final && $segundo_lugar && $terceiro_lugar): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Pódio</h5>
            </div>
            <div class="card-body">
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
                                        <?php if (!empty($integrantes_podio[$vencedor_final['id']])): ?>
                                            <?php foreach ($integrantes_podio[$vencedor_final['id']] as $integ): ?>
                                                <?php if (!empty($integ['usuario_id'])): ?>
                                                    <?php if (!empty($integ['foto_perfil'])): ?>
                                                        <?php
                                                        $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                        if (strpos($avatar, '../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                            if (strpos($avatar, '../') !== 0) {
                                                                if (strpos($avatar, '../') === 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                } else {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                }
                                                            }
                                                        } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                            $avatar = '../assets/arquivos/' . $avatar;
                                                        }
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                             class="rounded-circle" width="32" height="32" 
                                                             style="object-fit:cover;" 
                                                             alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso'] ?? ''); ?>" 
                                                             title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso'] ?? ''); ?>">
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso'] ?? ''); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'] ?? '', 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'] ?? '', 0, 1)); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                                    $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    if (strpos($avatar, '../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                        if (strpos($avatar, '../') !== 0) {
                                                            if (strpos($avatar, '../') === 0) {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            } else {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            }
                                                        }
                                                    } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                        $avatar = '../assets/arquivos/' . $avatar;
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
                                                    $avatar = $integ['foto_perfil'] ?: '../assets/arquivos/logo.png';
                                                    if (strpos($avatar, '../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                        if (strpos($avatar, '../') !== 0) {
                                                            if (strpos($avatar, '../') === 0) {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            } else {
                                                                $avatar = '../' . ltrim($avatar, '/');
                                                            }
                                                        }
                                                    } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                        $avatar = '../assets/arquivos/' . $avatar;
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
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Função para solicitar participação no torneio
function solicitarParticipacao() {
    var btn = document.getElementById('btnSolicitarParticipacao');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enviando...';
    }
    
    $.ajax({
        url: 'ajax/solicitar_participacao_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'success');
                } else {
                    alert(response.message);
                }
                // Recarregar página imediatamente para atualizar estado e mostrar botão de cancelar
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'danger');
                } else {
                    alert(response.message);
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-user-plus me-1"></i>Solicitar Participação';
                }
            }
        },
        error: function() {
            if (typeof showAlert === 'function') {
                showAlert('Erro ao solicitar participação', 'danger');
            } else {
                alert('Erro ao solicitar participação');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-plus me-1"></i>Solicitar Participação';
            }
        }
    });
}

// Função para entrar no torneio quando está completo
function entrarTorneio() {
    var btn = document.getElementById('btnEntrarTorneio');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enviando...';
    }
    
    $.ajax({
        url: 'ajax/solicitar_participacao_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert('Aguardando admin aceitar', 'info');
                } else {
                    alert('Aguardando admin aceitar');
                }
                // Atualizar botão para mostrar status pendente
                if (btn) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-warning');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-clock me-1"></i>Aguardando Admin Aceitar';
                }
            } else {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'danger');
                } else {
                    alert(response.message);
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i>Entrar';
                }
            }
        },
        error: function() {
            if (typeof showAlert === 'function') {
                showAlert('Erro ao solicitar entrada', 'danger');
            } else {
                alert('Erro ao solicitar entrada');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i>Entrar';
            }
        }
    });
}

// Função para cancelar solicitação de participação
function cancelarSolicitacao() {
    if (!confirm('Tem certeza que deseja cancelar sua solicitação de participação?')) {
        return;
    }
    
    var btn = document.getElementById('btnCancelarSolicitacao');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cancelando...';
    }
    
    $.ajax({
        url: 'ajax/cancelar_solicitacao_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'success');
                } else {
                    alert(response.message);
                }
                // Recarregar página após 1 segundo
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'danger');
                } else {
                    alert(response.message);
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-times me-1"></i>Cancelar Solicitação';
                }
            }
        },
        error: function() {
            if (typeof showAlert === 'function') {
                showAlert('Erro ao cancelar solicitação', 'danger');
            } else {
                alert('Erro ao cancelar solicitação');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times me-1"></i>Cancelar Solicitação';
            }
        }
    });
}

// Inicializar tooltips do Bootstrap
$(document).ready(function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../includes/footer.php'; ?>

