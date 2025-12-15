<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Jogo do Grupo';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
$jogo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug: verificar se os parâmetros estão sendo recebidos corretamente
error_log("DEBUG jogo_grupo.php - grupo_id: " . $grupo_id . ", jogo_id: " . $jogo_id);
error_log("DEBUG jogo_grupo.php - GET completo: " . print_r($_GET, true));

if ($grupo_id <= 0) {
    $_SESSION['mensagem'] = 'Grupo inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

// Verificar se o grupo existe e se o usuário é admin
$sql = "SELECT g.*, u.nome AS admin_nome FROM grupos g LEFT JOIN usuarios u ON u.id=g.administrador_id WHERE g.id=? AND g.ativo=1";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$grupo = $stmt ? $stmt->fetch() : false;
if ($stmt) {
    $stmt->closeCursor(); // Fechar cursor
}

if (!$grupo) {
    $_SESSION['mensagem'] = 'Grupo não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

$sou_admin = ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['mensagem'] = 'Acesso negado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

// Verificar se as tabelas existem
try {
    $check_stmt = $pdo->query("SELECT 1 FROM grupo_jogos LIMIT 1");
    if ($check_stmt) {
        $check_stmt->closeCursor(); // Fechar cursor imediatamente
    }
} catch (Exception $e) {
    // Tabelas não existem, criar
    $sql_file = dirname(__DIR__) . '/../sql/grupo_jogos_tables.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        // Executar cada comando separadamente para evitar erros
        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
        foreach ($statements as $statement) {
            if (!empty($statement) && stripos($statement, 'CREATE TABLE') !== false) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $ex) {
                    // Ignorar erros individuais (tabela pode já existir)
                }
            }
        }
    }
}

$jogo = null;
$participantes = [];
$times = [];
$partidas = [];
$classificacao = [];

if ($jogo_id > 0) {
    // Carregar jogo existente
    // Primeiro, garantir que todas as queries anteriores estejam fechadas
    try {
        // Fechar qualquer query pendente
        while ($pdo->inTransaction()) {
            // Se houver transação, não fazemos nada aqui
        }
        
        error_log("DEBUG - Iniciando busca do jogo. jogo_id: $jogo_id, grupo_id: $grupo_id");
        
        // Usar fetchAll() para garantir que a query seja completamente executada
        $sql = "SELECT * FROM grupo_jogos WHERE id = ? AND grupo_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            $error = $pdo->errorInfo();
            error_log("DEBUG - Erro ao preparar: " . print_r($error, true));
            throw new Exception('Erro ao preparar query: ' . ($error[2] ?? 'Erro desconhecido'));
        }
        
        $result = $stmt->execute([$jogo_id, $grupo_id]);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            error_log("DEBUG - Erro ao executar: " . print_r($error, true));
            $stmt->closeCursor();
            throw new Exception('Erro ao executar query: ' . ($error[2] ?? 'Erro desconhecido'));
        }
        
        // Usar fetchAll() e pegar o primeiro resultado
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        $jogo = !empty($resultados) ? $resultados[0] : null;
        
        error_log("DEBUG - Resultado: " . ($jogo ? "Jogo encontrado: " . $jogo['nome'] : 'Jogo não encontrado'));
        
        if (!$jogo || empty($jogo)) {
            $_SESSION['mensagem'] = 'Jogo não encontrado. Verifique se o jogo existe e pertence a este grupo.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header('Location: ../jogos_grupo.php?grupo_id=' . $grupo_id);
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("DEBUG - Erro PDO: " . $e->getMessage());
        $_SESSION['mensagem'] = 'Erro ao buscar jogo. Tente novamente.';
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: ../jogos_grupo.php?grupo_id=' . $grupo_id);
        exit();
    } catch (Exception $e) {
        error_log("DEBUG - Erro: " . $e->getMessage());
        $_SESSION['mensagem'] = 'Erro ao buscar jogo. Verifique se o jogo existe.';
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: ../jogos_grupo.php?grupo_id=' . $grupo_id);
        exit();
    }
    
    // Buscar participantes
    $sql = "SELECT gjp.*, u.id AS usuario_id, u.nome AS usuario_nome, u.foto_perfil
            FROM grupo_jogo_participantes gjp
            LEFT JOIN usuarios u ON u.id = gjp.usuario_id
            WHERE gjp.jogo_id = ?
            ORDER BY gjp.data_inscricao";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $participantes = $stmt ? $stmt->fetchAll() : [];
    
    // Limpar times duplicados primeiro (manter apenas o primeiro de cada nome/ordem)
    $sql = "DELETE t1 FROM grupo_jogo_times t1
            INNER JOIN grupo_jogo_times t2 
            WHERE t1.id > t2.id 
            AND t1.jogo_id = t2.jogo_id 
            AND t1.nome = t2.nome";
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Corrigir ordem dos times (garantir que a ordem seja sequencial 1, 2, 3...)
    $sql = "SELECT id FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem, id";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $times_ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
    if (!empty($times_ids)) {
        $ordem = 1;
        foreach ($times_ids as $time_id) {
            $sql = "UPDATE grupo_jogo_times SET ordem = ? WHERE id = ?";
            executeQuery($pdo, $sql, [$ordem, $time_id]);
            $ordem++;
        }
    }
    
    // Buscar times ordenados por ordem - usar DISTINCT para evitar duplicatas na query (igual ao torneio)
    $sql = "SELECT DISTINCT id, jogo_id, nome, cor, ordem FROM grupo_jogo_times WHERE jogo_id = ? ORDER BY ordem ASC, id ASC";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $times_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Debug: verificar quantos times foram encontrados no banco
    error_log("DEBUG - Total de times encontrados no banco (raw): " . count($times_raw));
    foreach ($times_raw as $t) {
        error_log("DEBUG - Time raw: ID=" . $t['id'] . ", Nome=" . $t['nome'] . ", Ordem=" . $t['ordem']);
    }
    
    // Remover duplicatas baseado em ID (manter apenas um de cada ID) - igual ao sistema de torneios
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
    
    // Debug: verificar quantos times únicos após remoção
    error_log("DEBUG - Total de times únicos após remoção: " . count($times));
    foreach ($times as $t) {
        error_log("DEBUG - Time único: ID=" . $t['id'] . ", Nome=" . $t['nome'] . ", Ordem=" . $t['ordem']);
    }
    
    // Limpar duplicatas antes de buscar integrantes
    // Remover registros duplicados (manter apenas o primeiro)
    $sql = "DELETE t1 FROM grupo_jogo_time_integrantes t1
            INNER JOIN grupo_jogo_time_integrantes t2 
            WHERE t1.id > t2.id 
            AND t1.participante_id = t2.participante_id 
            AND t1.time_id = t2.time_id";
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Ignorar erro se a sintaxe não funcionar
    }
    
    // Remover participantes que estão em múltiplos times (manter apenas o primeiro)
    $sql = "DELETE t1 FROM grupo_jogo_time_integrantes t1
            INNER JOIN grupo_jogo_time_integrantes t2 
            WHERE t1.id > t2.id 
            AND t1.participante_id = t2.participante_id";
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Buscar integrantes dos times
    foreach ($times as &$time) {
        $sql = "SELECT gjti.*, gjp.usuario_id, u.nome AS usuario_nome, u.foto_perfil
                FROM grupo_jogo_time_integrantes gjti
                JOIN grupo_jogo_participantes gjp ON gjp.id = gjti.participante_id
                LEFT JOIN usuarios u ON u.id = gjp.usuario_id
                WHERE gjti.time_id = ?
                ORDER BY u.nome";
        $stmt = executeQuery($pdo, $sql, [$time['id']]);
        $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
    }
    
    // Buscar partidas
    $sql = "SELECT gjp.*, 
                   t1.nome AS time1_nome, t1.cor AS time1_cor,
                   t2.nome AS time2_nome, t2.cor AS time2_cor
            FROM grupo_jogo_partidas gjp
            LEFT JOIN grupo_jogo_times t1 ON t1.id = gjp.time1_id
            LEFT JOIN grupo_jogo_times t2 ON t2.id = gjp.time2_id
            WHERE gjp.jogo_id = ?
            ORDER BY gjp.rodada, gjp.id";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $partidas = $stmt ? $stmt->fetchAll() : [];
    
    // Buscar classificação
    $sql = "SELECT gjc.*, gjt.nome AS time_nome, gjt.cor AS time_cor
            FROM grupo_jogo_classificacao gjc
            JOIN grupo_jogo_times gjt ON gjt.id = gjc.time_id
            WHERE gjc.jogo_id = ?
            ORDER BY gjc.pontos_total DESC, gjc.vitorias DESC, gjc.average DESC, gjc.saldo_pontos DESC";
    $stmt = executeQuery($pdo, $sql, [$jogo_id]);
    $classificacao = $stmt ? $stmt->fetchAll() : [];
}

// Buscar membros do grupo para adicionar
$sql = "SELECT u.id, u.nome, u.foto_perfil
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        WHERE gm.grupo_id = ? AND gm.ativo = 1
        ORDER BY u.nome";
$stmt = executeQuery($pdo, $sql, [$grupo_id]);
$membros_grupo = $stmt ? $stmt->fetchAll() : [];

include '../../includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-volleyball-ball me-2"></i>
            <?php echo $jogo ? 'Gerenciar: ' . htmlspecialchars($jogo['nome']) : 'Novo Jogo do Grupo'; ?>
        </h2>
        <a href="../jogos_grupo.php?grupo_id=<?php echo $grupo_id; ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<div id="alert-container"></div>

<?php if (!$jogo): ?>
<!-- Formulário de Criação -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Criar Novo Jogo</h5>
            </div>
            <div class="card-body">
                <form id="formCriarJogo">
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Jogo *</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="data_jogo" class="form-label">Data e Hora *</label>
                        <input type="datetime-local" class="form-control" id="data_jogo" name="data_jogo" required>
                    </div>
                    <div class="mb-3">
                        <label for="local" class="form-label">Local</label>
                        <input type="text" class="form-control" id="local" name="local" value="<?php echo htmlspecialchars($grupo['local_principal'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Criar Jogo
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Gerenciamento do Jogo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Informações do Jogo</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" onclick="toggleEditarJogo()" id="btnEditarJogo">
                        <i class="fas fa-edit me-1"></i>Editar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="excluirJogo()">
                        <i class="fas fa-trash me-1"></i>Excluir
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Modo Visualização -->
                <div id="viewMode">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <strong>Nome:</strong><br>
                            <?php echo htmlspecialchars($jogo['nome']); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <strong>Data:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($jogo['data_jogo'])); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <strong>Local:</strong><br>
                            <?php echo htmlspecialchars($jogo['local'] ?? 'Não informado'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?php 
                                echo $jogo['status'] === 'Finalizado' || $jogo['status'] === 'Arquivado' ? 'secondary' : 
                                    ($jogo['status'] === 'Em Andamento' ? 'warning' : 
                                    ($jogo['status'] === 'Lista Fechada' ? 'info' : 'success')); 
                            ?>">
                                <?php echo htmlspecialchars($jogo['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($jogo['descricao']): ?>
                    <div class="row">
                        <div class="col-12">
                            <strong>Descrição:</strong><br>
                            <?php echo nl2br(htmlspecialchars($jogo['descricao'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row mt-2">
                        <div class="col-md-3">
                            <strong>Participantes:</strong><br>
                            <?php echo count($participantes); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Modo Edição -->
                <div id="editMode" style="display: none;">
                    <form id="formEditarJogo">
                        <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nome" class="form-label">Nome do Jogo *</label>
                                <input type="text" class="form-control" id="edit_nome" name="nome" 
                                       value="<?php echo htmlspecialchars($jogo['nome']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_data_jogo" class="form-label">Data e Hora *</label>
                                <input type="datetime-local" class="form-control" id="edit_data_jogo" name="data_jogo" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($jogo['data_jogo'])); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_local" class="form-label">Local</label>
                                <input type="text" class="form-control" id="edit_local" name="local" 
                                       value="<?php echo htmlspecialchars($jogo['local'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="edit_descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="edit_descricao" name="descricao" rows="3"><?php echo htmlspecialchars($jogo['descricao'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Salvar Alterações
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEditarJogo()">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Participantes -->
<?php if ($jogo['status'] === 'Lista Aberta' || $jogo['status'] === 'Lista Fechada' || !empty($times)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseParticipantes" aria-expanded="<?php echo empty($times) ? 'true' : 'false'; ?>" aria-controls="collapseParticipantes">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-chevron-<?php echo empty($times) ? 'down' : 'right'; ?> transition-all"></i>
                    Participantes <span class="badge bg-primary"><?php echo count($participantes); ?></span>
                </h5>
                <div onclick="event.stopPropagation();">
                    <?php if ($jogo['status'] === 'Lista Aberta'): ?>
                        <button class="btn btn-sm btn-warning" onclick="fecharLista()">
                            <i class="fas fa-lock me-1"></i>Fechar Lista
                        </button>
                    <?php elseif ($jogo['status'] === 'Lista Fechada'): ?>
                        <button class="btn btn-sm btn-success" onclick="reabrirLista()">
                            <i class="fas fa-unlock me-1"></i>Reabrir Lista
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="collapse <?php echo empty($times) ? 'show' : ''; ?>" id="collapseParticipantes">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Participantes Inscritos</h6>
                        <div id="listaParticipantes" class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($participantes as $p): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($p['usuario_id']): ?>
                                            <?php
                                            $avatar = '../../assets/arquivos/logo.png';
                                            if (!empty($p['foto_perfil'])) {
                                                if (strpos($p['foto_perfil'], 'http') === 0 || strpos($p['foto_perfil'], '/') === 0) {
                                                    // URL absoluta ou caminho absoluto
                                                    $avatar = $p['foto_perfil'];
                                                } elseif (strpos($p['foto_perfil'], '../../assets/') === 0 || strpos($p['foto_perfil'], '../assets/') === 0 || strpos($p['foto_perfil'], 'assets/') === 0) {
                                                    // Já tem assets/, garantir que comece com ../../
                                                    if (strpos($p['foto_perfil'], '../../') !== 0) {
                                                        if (strpos($p['foto_perfil'], '../') === 0) {
                                                            $avatar = '../' . ltrim($p['foto_perfil'], '/');
                                                        } else {
                                                            $avatar = '../../' . ltrim($p['foto_perfil'], '/');
                                                        }
                                                    } else {
                                                        $avatar = $p['foto_perfil'];
                                                    }
                                                } else {
                                                    // Apenas nome do arquivo, adicionar caminho completo
                                                    $avatar = '../../assets/arquivos/' . ltrim($p['foto_perfil'], '/');
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover;" alt="Avatar">
                                            <span><?php echo htmlspecialchars($p['usuario_nome']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="removerParticipante(<?php echo $p['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Adicionar do Grupo</h6>
                        <div id="listaMembros" class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <?php 
                            $participantes_ids = array_column($participantes, 'usuario_id');
                            foreach ($membros_grupo as $m): 
                                if (!in_array($m['id'], $participantes_ids)):
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php
                                            $avatar = '../../assets/arquivos/logo.png';
                                            if (!empty($m['foto_perfil'])) {
                                                if (strpos($m['foto_perfil'], 'http') === 0 || strpos($m['foto_perfil'], '/') === 0) {
                                                    // URL absoluta ou caminho absoluto
                                                    $avatar = $m['foto_perfil'];
                                                } elseif (strpos($m['foto_perfil'], '../../assets/') === 0 || strpos($m['foto_perfil'], '../assets/') === 0 || strpos($m['foto_perfil'], 'assets/') === 0) {
                                                    // Já tem assets/, garantir que comece com ../../
                                                    if (strpos($m['foto_perfil'], '../../') !== 0) {
                                                        if (strpos($m['foto_perfil'], '../') === 0) {
                                                            $avatar = '../' . ltrim($m['foto_perfil'], '/');
                                                        } else {
                                                            $avatar = '../../' . ltrim($m['foto_perfil'], '/');
                                                        }
                                                    } else {
                                                        $avatar = $m['foto_perfil'];
                                                    }
                                                } else {
                                                    // Apenas nome do arquivo, adicionar caminho completo
                                                    $avatar = '../../assets/arquivos/' . ltrim($m['foto_perfil'], '/');
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover;" alt="Avatar">
                                            <span><?php echo htmlspecialchars($m['nome']); ?></span>
                                        </div>
                                        <button class="btn btn-sm btn-success" onclick="adicionarParticipante(<?php echo $m['id']; ?>)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Configurar Modalidade -->
                <?php if (count($participantes) > 0): ?>
                <hr>
                <div class="mt-4">
                    <h6 class="mb-3">Configurar Modalidade</h6>
                    <form id="formConfigModalidadeParticipantes" class="form-config-modalidade">
                        <input type="hidden" name="jogo_id" value="<?php echo $jogo_id; ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="modalidade_participantes" class="form-label">Modalidade *</label>
                                <select class="form-control" id="modalidade_participantes" name="modalidade" required>
                                    <option value="">Selecione...</option>
                                    <option value="Dupla" <?php echo ($jogo['modalidade'] ?? '') === 'Dupla' ? 'selected' : ''; ?>>Dupla</option>
                                    <option value="Trio" <?php echo ($jogo['modalidade'] ?? '') === 'Trio' ? 'selected' : ''; ?>>Trio</option>
                                    <option value="Quarteto" <?php echo ($jogo['modalidade'] ?? '') === 'Quarteto' ? 'selected' : ''; ?>>Quarteto</option>
                                    <option value="Quinteto" <?php echo ($jogo['modalidade'] ?? '') === 'Quinteto' ? 'selected' : ''; ?>>Quinteto</option>
                                </select>
                                <small class="text-muted" id="avisoModalidade"></small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="quantidade_times_participantes" class="form-label">Quantidade de Times *</label>
                                <input type="number" class="form-control" id="quantidade_times_participantes" name="quantidade_times" min="2" required value="<?php echo $jogo['quantidade_times'] ?? ''; ?>" readonly>
                                <small class="text-muted"><i class="fas fa-info-circle"></i> Calculado automaticamente</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="integrantes_por_time_participantes" class="form-label">Integrantes por Time *</label>
                                <input type="number" class="form-control" id="integrantes_por_time_participantes" name="integrantes_por_time" min="2" required value="<?php echo $jogo['integrantes_por_time'] ?? ''; ?>" readonly>
                                <small class="text-muted">Definido pela modalidade</small>
                                <small class="text-muted d-block mt-1">Participantes disponíveis: <strong id="totalParticipantesDisponiveis"><?php echo count($participantes); ?></strong></small>
                                <small class="text-muted d-block mt-1">Total necessário: <span id="totalNecessarioParticipantes">0</span> participantes</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar Configuração
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Configurar Modalidade (versão antiga - apenas se não houver participantes) -->
<?php if ($jogo['status'] === 'Lista Fechada' && empty($jogo['modalidade']) && empty($participantes)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configurar Modalidade</h5>
            </div>
            <div class="card-body">
                <form id="formConfigModalidade">
                    <input type="hidden" name="jogo_id" value="<?php echo $jogo_id; ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="modalidade" class="form-label">Modalidade *</label>
                            <select class="form-control" id="modalidade" name="modalidade" required>
                                <option value="">Selecione...</option>
                                <option value="Dupla" <?php echo ($jogo['modalidade'] ?? '') === 'Dupla' ? 'selected' : ''; ?>>Dupla</option>
                                <option value="Trio" <?php echo ($jogo['modalidade'] ?? '') === 'Trio' ? 'selected' : ''; ?>>Trio</option>
                                <option value="Quarteto" <?php echo ($jogo['modalidade'] ?? '') === 'Quarteto' ? 'selected' : ''; ?>>Quarteto</option>
                                <option value="Quinteto" <?php echo ($jogo['modalidade'] ?? '') === 'Quinteto' ? 'selected' : ''; ?>>Quinteto</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quantidade_times" class="form-label">Quantidade de Times *</label>
                            <input type="number" class="form-control" id="quantidade_times" name="quantidade_times" min="2" required value="<?php echo $jogo['quantidade_times'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="integrantes_por_time" class="form-label">Integrantes por Time *</label>
                            <input type="number" class="form-control" id="integrantes_por_time" name="integrantes_por_time" min="2" required value="<?php echo $jogo['integrantes_por_time'] ?? ''; ?>">
                            <small class="text-muted">Total necessário: <span id="totalNecessario">0</span> participantes</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar Configuração
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Times -->
<?php if (!empty($jogo['modalidade']) && !empty($jogo['quantidade_times']) && !empty($jogo['integrantes_por_time']) && ($jogo['status'] === 'Lista Aberta' || $jogo['status'] === 'Lista Fechada' || $jogo['status'] === 'Times Criados' || $jogo['status'] === 'Em Andamento' || !empty($times))): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Times</h5>
                <div>
                    <?php if (empty($times)): ?>
                        <button class="btn btn-sm btn-primary" onclick="criarTimes()">
                            <i class="fas fa-users me-1"></i>Criar Times
                        </button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-danger" onclick="limparTimes()">
                            <i class="fas fa-trash me-1"></i>Limpar Times
                        </button>
                        <button class="btn btn-sm btn-success" onclick="sortearTimes()">
                            <i class="fas fa-random me-1"></i>Sortear Times
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="iniciarPartidas()">
                            <i class="fas fa-play me-1"></i>Iniciar Partidas
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php 
                // REMOVER DUPLICATAS POR ID antes de usar (segunda verificação de segurança) - igual ao sistema de torneios
                $times_unicos_por_id = [];
                $ids_ja_adicionados = [];
                
                foreach ($times as $time) {
                    $id = (int)($time['id'] ?? 0);
                    if ($id > 0 && !in_array($id, $ids_ja_adicionados)) {
                        $times_unicos_por_id[] = $time;
                        $ids_ja_adicionados[] = $id;
                    }
                }
                
                // Ordenar times por ordem antes de exibir
                usort($times_unicos_por_id, function($a, $b) {
                    $ordemA = isset($a['ordem']) ? (int)$a['ordem'] : 999;
                    $ordemB = isset($b['ordem']) ? (int)$b['ordem'] : 999;
                    if ($ordemA == $ordemB) {
                        $idA = isset($a['id']) ? (int)$a['id'] : 0;
                        $idB = isset($b['id']) ? (int)$b['id'] : 0;
                        return $idA - $idB;
                    }
                    return $ordemA - $ordemB;
                });
                
                // Debug: mostrar no console do navegador
                echo "<script>";
                echo "console.log('DEBUG - Total de times únicos para exibir: " . count($times_unicos_por_id) . "');";
                foreach ($times_unicos_por_id as $t) {
                    echo "console.log('DEBUG - Time para exibir: ID=" . $t['id'] . ", Nome=" . $t['nome'] . ", Ordem=" . $t['ordem'] . "');";
                }
                echo "</script>";
                ?>
                <?php if (empty($times_unicos_por_id)): ?>
                    <p class="text-muted">Crie os times primeiro.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($times_unicos_por_id as $time): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;" data-time-id="<?php echo $time['id']; ?>">
                                    <div class="card-header" style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                                <strong id="time-nome-<?php echo $time['id']; ?>"><?php echo htmlspecialchars($time['nome']); ?></strong>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary" onclick="editarTimeGrupo(<?php echo $time['id']; ?>, '<?php echo htmlspecialchars($time['nome']); ?>', '<?php echo htmlspecialchars($time['cor']); ?>')" title="Editar Time">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="time-participantes" data-time-id="<?php echo $time['id']; ?>" id="integrantes-time-<?php echo $time['id']; ?>">
                                            <?php if (empty($time['integrantes'])): ?>
                                                <p class="text-muted mb-2"><small>Nenhum integrante</small></p>
                                            <?php else: ?>
                                                <?php foreach ($time['integrantes'] as $integ): ?>
                                                    <div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" 
                                                         data-participante-id="<?php echo $integ['participante_id']; ?>" 
                                                         data-usuario-id="<?php echo $integ['usuario_id']; ?>"
                                                         onclick="event.stopPropagation(); selecionarParticipanteGrupo(this, event)"
                                                         style="cursor: pointer; user-select: none; -webkit-user-select: none;"
                                                         oncontextmenu="return false;">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <?php
                                                            $avatar = '../../assets/arquivos/logo.png';
                                                            if (!empty($integ['foto_perfil'])) {
                                                                if (strpos($integ['foto_perfil'], 'http') === 0 || strpos($integ['foto_perfil'], '/') === 0) {
                                                                    $avatar = $integ['foto_perfil'];
                                                                } elseif (strpos($integ['foto_perfil'], '../../assets/') === 0 || strpos($integ['foto_perfil'], '../assets/') === 0 || strpos($integ['foto_perfil'], 'assets/') === 0) {
                                                                    if (strpos($integ['foto_perfil'], '../../') !== 0) {
                                                                        if (strpos($integ['foto_perfil'], '../') === 0) {
                                                                            $avatar = '../' . ltrim($integ['foto_perfil'], '/');
                                                                        } else {
                                                                            $avatar = '../../' . ltrim($integ['foto_perfil'], '/');
                                                                        }
                                                                    } else {
                                                                        $avatar = $integ['foto_perfil'];
                                                                    }
                                                                } else {
                                                                    $avatar = '../../assets/arquivos/' . ltrim($integ['foto_perfil'], '/');
                                                                }
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="24" height="24" style="object-fit:cover;" alt="Avatar">
                                                            <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                        </div>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removerParticipanteTime(<?php echo $integ['participante_id']; ?>, <?php echo $time['id']; ?>)" title="Remover do time">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm btn-success w-100 mt-2" onclick="adicionarParticipanteTime(<?php echo $time['id']; ?>)" title="Adicionar Participante">
                                            <i class="fas fa-plus me-1"></i>Adicionar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Partidas -->
<?php if (!empty($partidas)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Partidas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rodada</th>
                                <th>Time 1</th>
                                <th>Placar</th>
                                <th>Time 2</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rodada_atual = 0;
                            foreach ($partidas as $partida): 
                                if (isset($partida['rodada']) && $partida['rodada'] != $rodada_atual):
                                    $rodada_atual = $partida['rodada'];
                            ?>
                                <tr class="table-secondary">
                                    <td colspan="6"><strong>Rodada <?php echo $rodada_atual; ?></strong></td>
                                </tr>
                            <?php endif; ?>
                                <tr>
                                    <td><?php echo $partida['rodada'] ?? 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time1_nome'] ?? 'Time 1'); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($partida['status'] === 'Finalizada'): ?>
                                            <strong><?php echo (int)($partida['pontos_time1'] ?? 0); ?> x <?php echo (int)($partida['pontos_time2'] ?? 0); ?></strong>
                                        <?php else: ?>
                                            <input type="number" class="form-control form-control-sm d-inline-block" style="width: 60px;" 
                                                   id="pts1_<?php echo $partida['id']; ?>" value="<?php echo (int)($partida['pontos_time1'] ?? 0); ?>" min="0">
                                            <span class="mx-1">x</span>
                                            <input type="number" class="form-control form-control-sm d-inline-block" style="width: 60px;" 
                                                   id="pts2_<?php echo $partida['id']; ?>" value="<?php echo (int)($partida['pontos_time2'] ?? 0); ?>" min="0">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time2_nome'] ?? 'Time 2'); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo (!empty($partida['status']) && $partida['status'] === 'Finalizada') ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($partida['status'] ?? 'Agendada'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($partida['status'] !== 'Finalizada'): ?>
                                            <button class="btn btn-sm btn-success" onclick="salvarResultado(<?php echo $partida['id']; ?>)">
                                                <i class="fas fa-save"></i> Salvar
                                            </button>
                                        <?php endif; ?>
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

<!-- Classificação -->
<?php if (!empty($classificacao)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Classificação Geral</h5>
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

<!-- Finalizar e Arquivar -->
<?php if ($jogo['status'] === 'Em Andamento' || $jogo['status'] === 'Finalizado'): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Finalizar Jogo</h5>
            </div>
            <div class="card-body">
                <?php if ($jogo['status'] !== 'Arquivado'): ?>
                    <button class="btn btn-warning" onclick="arquivarJogo()">
                        <i class="fas fa-archive me-1"></i>Arquivar Jogo
                    </button>
                    <small class="text-muted d-block mt-2">Ao arquivar, o jogo ficará disponível apenas para visualização.</small>
                <?php else: ?>
                    <p class="text-muted">Este jogo está arquivado e disponível apenas para visualização.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
const jogoId = <?php echo $jogo_id ?: 0; ?>;
const grupoId = <?php echo $grupo_id; ?>;

// Criar jogo
<?php if (!$jogo): ?>
$('#formCriarJogo').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: '../ajax/criar_jogo_grupo.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => {
                    // Redirecionar para a página de gerenciamento (admin)
                    window.location.href = 'jogo_grupo.php?id=' + response.jogo_id + '&grupo_id=' + grupoId;
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao criar jogo:', error);
            showAlert('Erro ao criar jogo. Tente novamente.', 'danger');
        }
    });
});
<?php endif; ?>

// Fechar lista
function fecharLista() {
    if (!confirm('Deseja fechar a lista de participantes?')) return;
    $.ajax({
        url: '../ajax/fechar_lista_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Reabrir lista
function reabrirLista() {
    if (!confirm('Deseja reabrir a lista de participantes?')) return;
    $.ajax({
        url: '../ajax/reabrir_lista_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Adicionar participante
function adicionarParticipante(usuarioId) {
    $.ajax({
        url: '../ajax/adicionar_participante_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId, usuario_id: usuarioId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Atualizar contador antes de recarregar
                atualizarContadorParticipantes();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Remover participante
function removerParticipante(participanteId) {
    if (!confirm('Deseja remover este participante?')) return;
    $.ajax({
        url: '../ajax/remover_participante_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId, participante_id: participanteId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Atualizar contador antes de recarregar
                atualizarContadorParticipantes();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Atualizar contador de participantes
function atualizarContadorParticipantes() {
    const totalParticipantes = $('#listaParticipantes .list-group-item').length;
    $('h5:contains("Participantes") .badge').text(totalParticipantes);
}

// Configurar modalidade (formulário dentro de participantes)
$('#formConfigModalidadeParticipantes').on('submit', function(e) {
    e.preventDefault();
    
    // Validar novamente antes de enviar
    const modalidade = $('#modalidade_participantes').val();
    const integrantesPorTime = modalidadeIntegrantes[modalidade] || 0;
    let quantidadeTimes = parseInt($('#quantidade_times_participantes').val()) || 0;
    
    // Se não tiver quantidade de times, calcular novamente
    if (!quantidadeTimes && modalidade && integrantesPorTime > 0 && totalParticipantes > 0) {
        quantidadeTimes = Math.floor(totalParticipantes / integrantesPorTime);
    }
    
    const totalUtilizado = quantidadeTimes * integrantesPorTime;
    const sobra = totalParticipantes - totalUtilizado;
    
    if (quantidadeTimes < 2) {
        showAlert('É necessário pelo menos 2 times para esta modalidade.', 'danger');
        return;
    }
    
    if (sobra > 0) {
        showAlert('Esta modalidade não é viável. Sobrariam ' + sobra + ' participante' + (sobra > 1 ? 's' : '') + ' de fora. Escolha outra modalidade.', 'danger');
        return;
    }
    
    // Garantir que os valores estão corretos antes de enviar
    $('#quantidade_times_participantes').val(quantidadeTimes);
    $('#integrantes_por_time_participantes').val(integrantesPorTime);
    
    $.ajax({
        url: '../ajax/configurar_modalidade_jogo_grupo.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
});

// Mapeamento de modalidades para integrantes por time
const modalidadeIntegrantes = {
    'Dupla': 2,
    'Trio': 3,
    'Quarteto': 4,
    'Quinteto': 5
};

// Total de participantes disponíveis
const totalParticipantes = <?php echo count($participantes); ?>;

// Calcular quantidade de times automaticamente
function calcularQuantidadeTimes() {
    const modalidade = $('#modalidade_participantes').val();
    const integrantesPorTime = modalidadeIntegrantes[modalidade] || 0;
    
    if (modalidade && integrantesPorTime > 0 && totalParticipantes > 0) {
        // Definir integrantes por time
        $('#integrantes_por_time_participantes').val(integrantesPorTime);
        
        // Calcular quantidade de times (arredondar para baixo)
        const quantidadeTimes = Math.floor(totalParticipantes / integrantesPorTime);
        const totalUtilizado = quantidadeTimes * integrantesPorTime;
        const sobra = totalParticipantes - totalUtilizado;
        
        // Verificar se é viável (divisível sem sobras e mínimo 2 times)
        const viavel = (quantidadeTimes >= 2 && sobra === 0);
        
        if (viavel) {
            $('#quantidade_times_participantes').val(quantidadeTimes);
            $('#totalNecessarioParticipantes').text(totalUtilizado);
            $('#avisoModalidade').text('✓ Todos os ' + totalParticipantes + ' participantes serão utilizados em ' + quantidadeTimes + ' times.').removeClass('text-danger').addClass('text-success');
            $('#formConfigModalidadeParticipantes button[type="submit"]').prop('disabled', false);
        } else if (quantidadeTimes >= 2 && sobra > 0) {
            // Se tem times suficientes mas sobra participantes, mostrar aviso mas permitir tentar salvar
            $('#quantidade_times_participantes').val(quantidadeTimes);
            $('#totalNecessarioParticipantes').text(totalUtilizado);
            $('#avisoModalidade').text('⚠ Esta modalidade não é viável. Sobrariam ' + sobra + ' participante' + (sobra > 1 ? 's' : '') + ' de fora. Escolha outra modalidade.').removeClass('text-success').addClass('text-danger');
            $('#formConfigModalidadeParticipantes button[type="submit"]').prop('disabled', true);
        } else {
            $('#quantidade_times_participantes').val('');
            $('#totalNecessarioParticipantes').text('0');
            if (quantidadeTimes < 2) {
                $('#avisoModalidade').text('⚠ Não há participantes suficientes. Mínimo necessário: ' + (integrantesPorTime * 2) + ' participantes.').removeClass('text-success').addClass('text-danger');
            } else if (sobra > 0) {
                $('#avisoModalidade').text('⚠ Esta modalidade não é viável. Sobrariam ' + sobra + ' participante' + (sobra > 1 ? 's' : '') + ' de fora. Escolha outra modalidade.').removeClass('text-success').addClass('text-danger');
            }
            $('#formConfigModalidadeParticipantes button[type="submit"]').prop('disabled', true);
        }
    } else {
        $('#integrantes_por_time_participantes').val('');
        $('#quantidade_times_participantes').val('');
        $('#totalNecessarioParticipantes').text('0');
        $('#avisoModalidade').text('').removeClass('text-success text-danger');
        $('#formConfigModalidadeParticipantes button[type="submit"]').prop('disabled', false);
    }
}

// Quando a modalidade mudar, calcular automaticamente
$('#modalidade_participantes').on('change', function() {
    calcularQuantidadeTimes();
});

// Atualizar ícone da seta quando collapse é expandido/recolhido
$('#collapseParticipantes').on('show.bs.collapse', function() {
    $(this).closest('.card').find('.fa-chevron-right').removeClass('fa-chevron-right').addClass('fa-chevron-down');
});

$('#collapseParticipantes').on('hide.bs.collapse', function() {
    $(this).closest('.card').find('.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-right');
});

// Calcular ao carregar a página se já houver modalidade selecionada
$(document).ready(function() {
    if ($('#modalidade_participantes').length && $('#modalidade_participantes').val()) {
        calcularQuantidadeTimes();
    }
});

// Configurar modalidade (formulário antigo - manter para compatibilidade)
<?php if ($jogo && $jogo['status'] === 'Lista Fechada' && empty($jogo['modalidade']) && count($participantes) === 0): ?>
$('#formConfigModalidade').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: '../ajax/configurar_modalidade_jogo_grupo.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
});

$('#quantidade_times, #integrantes_por_time').on('input', function() {
    const qtd = parseInt($('#quantidade_times').val()) || 0;
    const integrantes = parseInt($('#integrantes_por_time').val()) || 0;
    $('#totalNecessario').text(qtd * integrantes);
});
<?php endif; ?>

// Criar times
function criarTimes() {
    $.ajax({
        url: '../ajax/criar_times_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Mostrar debug no console
                if (response.debug) {
                    console.log('DEBUG - Times criados:', response.debug);
                    console.log('IDs criados:', response.debug.ids_criados);
                    console.log('Times verificados:', response.debug.times_verificados);
                    console.log('Total criados:', response.debug.total_criados);
                    console.log('Total verificado:', response.debug.total_verificado);
                }
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao criar times:', error);
            console.error('Resposta:', xhr.responseText);
            showAlert('Erro ao criar times. Verifique o console para mais detalhes.', 'danger');
        }
    });
}

// Editar time (nome e cor)
function editarTimeGrupo(timeId, nomeAtual, corAtual) {
    const novoNome = prompt('Nome do time:', nomeAtual);
    if (!novoNome || novoNome.trim() === '') return;
    
    // Criar input de cor
    const cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6610f2', '#343a40', '#6c757d'];
    let corEscolhida = corAtual;
    
    if (confirm('Deseja alterar a cor do time?')) {
        const coresHtml = cores.map(c => `<button type="button" class="btn m-1" style="background-color: ${c}; width: 40px; height: 40px; border: 2px solid ${c === corAtual ? '#000' : 'transparent'};" onclick="document.getElementById('corSelecionada').value='${c}'; this.style.border='2px solid #000'; document.querySelectorAll('[data-cor-btn]').forEach(b => { if (b !== this) b.style.border='2px solid transparent'; })" data-cor-btn></button>`).join('');
        const modalHtml = `
            <div class="modal fade" id="modalCorTime" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Escolher Cor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="corSelecionada" value="${corAtual}">
                            <div class="d-flex flex-wrap justify-content-center">
                                ${coresHtml}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('corSelecionada').value && salvarEdicaoTime(${timeId}, '${novoNome}')">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('modalCorTime'));
        modal.show();
        $('#modalCorTime').on('hidden.bs.modal', function() {
            $(this).remove();
        });
        return;
    }
    
    salvarEdicaoTime(timeId, novoNome, corAtual);
}

function salvarEdicaoTime(timeId, nome, cor) {
    if (!cor) {
        cor = document.getElementById('corSelecionada')?.value || '#007bff';
        $('#modalCorTime').modal('hide');
    }
    
    $.ajax({
        url: '../ajax/editar_time_jogo_grupo.php',
        method: 'POST',
        data: {
            time_id: timeId,
            nome: nome,
            cor: cor
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Adicionar participante ao time
function adicionarParticipanteTime(timeId) {
    // Buscar participantes disponíveis (que não estão em nenhum time ou estão em outros times)
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_time.php',
        method: 'GET',
        data: { jogo_id: jogoId, time_id: timeId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.participantes.length > 0) {
                let options = response.participantes.map(function(p) {
                    return `<option value="${p.participante_id}">${p.nome}</option>`;
                }).join('');
                
                const select = document.createElement('select');
                select.className = 'form-select mb-2';
                select.innerHTML = '<option value="">Selecione um participante...</option>' + options;
                
                const div = document.createElement('div');
                div.className = 'd-flex gap-2 mb-2';
                div.appendChild(select);
                
                const btnSalvar = document.createElement('button');
                btnSalvar.className = 'btn btn-sm btn-primary';
                btnSalvar.innerHTML = '<i class="fas fa-check"></i>';
                btnSalvar.onclick = function() {
                    const participanteId = select.value;
                    if (!participanteId) {
                        showAlert('Selecione um participante', 'warning');
                        return;
                    }
                    adicionarParticipanteAoTime(timeId, participanteId);
                    div.remove();
                };
                div.appendChild(btnSalvar);
                
                const btnCancelar = document.createElement('button');
                btnCancelar.className = 'btn btn-sm btn-secondary';
                btnCancelar.innerHTML = '<i class="fas fa-times"></i>';
                btnCancelar.onclick = function() { div.remove(); };
                div.appendChild(btnCancelar);
                
                document.getElementById('integrantes-time-' + timeId).appendChild(div);
            } else {
                showAlert('Nenhum participante disponível', 'warning');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarParticipanteAoTime(timeId, participanteId) {
    $.ajax({
        url: '../ajax/adicionar_participante_time_jogo_grupo.php',
        method: 'POST',
        data: {
            time_id: timeId,
            participante_id: participanteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Remover participante do time
function removerParticipanteTime(participanteId, timeId) {
    if (!confirm('Deseja remover este participante do time?')) return;
    
    $.ajax({
        url: '../ajax/remover_participante_time_jogo_grupo.php',
        method: 'POST',
        data: {
            participante_id: participanteId,
            time_id: timeId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Sistema de seleção e troca por clique (igual aos torneios)
let participanteSelecionadoGrupo = null;
let timeOrigemSelecionadoGrupo = null;

function selecionarParticipanteGrupo(elemento, evt) {
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
    if (participanteSelecionadoGrupo) {
        // Se clicou no mesmo participante, deselecionar
        if (participanteSelecionadoGrupo === participante) {
            participanteSelecionadoGrupo.classList.remove('selecionado');
            participanteSelecionadoGrupo = null;
            timeOrigemSelecionadoGrupo = null;
            return;
        }
        
        // Se clicou em outro participante, fazer troca
        const timeDestino = participante.closest('.time-participantes');
        const timeOrigem = participanteSelecionadoGrupo.closest('.time-participantes');
        
        // Se está no mesmo time, apenas trocar a ordem
        if (timeOrigem === timeDestino) {
            // Trocar posição exata no mesmo time
            const participante1 = participanteSelecionadoGrupo;
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
            trocarParticipantesEntreTimesGrupo(participanteSelecionadoGrupo, participante, timeOrigem, timeDestino);
        }
        
        // Deselecionar
        participanteSelecionadoGrupo.classList.remove('selecionado');
        participanteSelecionadoGrupo = null;
        timeOrigemSelecionadoGrupo = null;
    } else {
        // Selecionar o participante
        participanteSelecionadoGrupo = participante;
        timeOrigemSelecionadoGrupo = timeAtual;
        participante.classList.add('selecionado');
    }
}

function trocarParticipantesEntreTimesGrupo(participante1, participante2, timeOrigem, timeDestino) {
    const participante1Id = participante1.getAttribute('data-participante-id');
    const participante2Id = participante2.getAttribute('data-participante-id');
    const timeOrigemId = timeOrigem.getAttribute('data-time-id');
    const timeDestinoId = timeDestino.getAttribute('data-time-id');
    
    // Obter a posição exata do participante2 no time de destino
    const proximoParticipante2 = participante2.nextSibling;
    const proximoParticipante1 = participante1.nextSibling;
    
    // Remover ambos do DOM
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
    
    // Salvar troca completa no banco
    $.ajax({
        url: '../ajax/trocar_participantes_time_jogo_grupo.php',
        method: 'POST',
        data: {
            participante1_id: participante1Id,
            participante2_id: participante2Id,
            time1_id: timeOrigemId,
            time2_id: timeDestinoId
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showAlert('Erro ao trocar participantes: ' + response.message, 'danger');
                location.reload(); // Recarregar em caso de erro
            }
        },
        error: function() {
            showAlert('Erro ao trocar participantes', 'danger');
            location.reload();
        }
    });
}

// Deselecionar ao clicar fora
document.addEventListener('click', function(event) {
    if (participanteSelecionadoGrupo && !event.target.closest('.participante-item')) {
        participanteSelecionadoGrupo.classList.remove('selecionado');
        participanteSelecionadoGrupo = null;
        timeOrigemSelecionadoGrupo = null;
    }
});

// Limpar times
function limparTimes() {
    if (!confirm('Deseja limpar todos os times? Isso irá remover todos os times e seus integrantes. Esta ação não pode ser desfeita.')) return;
    $.ajax({
        url: '../ajax/limpar_times_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Sortear times
function sortearTimes() {
    if (!confirm('Deseja sortear os participantes nos times? Isso irá remover a distribuição atual.')) return;
    $.ajax({
        url: '../ajax/sortear_times_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Iniciar partidas
function iniciarPartidas() {
    if (!confirm('Deseja iniciar as partidas? Isso criará todos os confrontos entre os times.')) return;
    $.ajax({
        url: '../ajax/iniciar_partidas_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Salvar resultado
function salvarResultado(partidaId) {
    const pts1 = parseInt($('#pts1_' + partidaId).val()) || 0;
    const pts2 = parseInt($('#pts2_' + partidaId).val()) || 0;
    
    $.ajax({
        url: '../ajax/salvar_resultado_partida_jogo_grupo.php',
        method: 'POST',
        data: {
            partida_id: partidaId,
            pontos_time1: pts1,
            pontos_time2: pts2
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

// Arquivar jogo
function arquivarJogo() {
    if (!confirm('Deseja arquivar este jogo? Ele ficará disponível apenas para visualização.')) return;
    $.ajax({
        url: '../ajax/arquivar_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        }
    });
}

function showAlert(message, type) {
    const alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>');
    $('#alert-container').append(alert);
    setTimeout(() => alert.fadeOut(), 5000);
}

// Toggle modo edição
function toggleEditarJogo() {
    const viewMode = $('#viewMode');
    const editMode = $('#editMode');
    const btnEditar = $('#btnEditarJogo');
    
    if (viewMode.is(':visible')) {
        viewMode.hide();
        editMode.show();
        btnEditar.html('<i class="fas fa-eye me-1"></i>Visualizar');
    } else {
        viewMode.show();
        editMode.hide();
        btnEditar.html('<i class="fas fa-edit me-1"></i>Editar');
    }
}

// Editar jogo
$('#formEditarJogo').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: '../ajax/editar_jogo_grupo.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao editar jogo:', error);
            showAlert('Erro ao editar jogo. Tente novamente.', 'danger');
        }
    });
});

// Excluir jogo
function excluirJogo() {
    if (!confirm('ATENÇÃO: Deseja realmente excluir este jogo? Esta ação não pode ser desfeita e todos os dados relacionados (participantes, times, partidas, classificação) serão permanentemente removidos.')) {
        return;
    }
    
    if (!confirm('Tem certeza? Esta é uma ação irreversível!')) {
        return;
    }
    
    $.ajax({
        url: '../ajax/excluir_jogo_grupo.php',
        method: 'POST',
        data: { jogo_id: jogoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(() => {
                    window.location.href = '../jogos_grupo.php?grupo_id=' + grupoId;
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao excluir jogo:', error);
            showAlert('Erro ao excluir jogo. Tente novamente.', 'danger');
        }
    });
}
</script>

<style>
.participante-item {
    cursor: pointer;
    transition: opacity 0.2s;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}
.participante-item:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
}
.participante-item.selecionado {
    background-color: #cfe2ff !important;
    border: 2px solid #0d6efd !important;
    transform: scale(1.02);
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.time-participantes {
    user-select: none;
    min-height: 50px;
}
.transition-all {
    transition: transform 0.3s ease;
}
.card-header[data-bs-toggle="collapse"] {
    user-select: none;
}
.card-header[data-bs-toggle="collapse"]:hover {
    background-color: rgba(0,0,0,0.02);
}
</style>

<?php include '../../includes/footer.php'; ?>

