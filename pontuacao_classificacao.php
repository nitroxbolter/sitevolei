<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Classificação de Pontuação';

if (!isLoggedIn()) {
    header('Location: auth/login.php');
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

// Buscar classificação
$sql = "SELECT 
            u.id,
            u.nome,
            u.foto_perfil,
            COALESCE(SUM(sp.pontos), 0) AS total_pontos,
            COUNT(DISTINCT sp.jogo_id) AS jogos_participados
        FROM grupo_membros gm
        JOIN usuarios u ON u.id = gm.usuario_id
        LEFT JOIN sistema_pontuacao_pontos sp ON sp.usuario_id = u.id 
            AND sp.jogo_id IN (SELECT id FROM sistema_pontuacao_jogos WHERE sistema_id = ?)
        WHERE gm.grupo_id = ? AND gm.ativo = 1
        GROUP BY u.id, u.nome, u.foto_perfil
        ORDER BY total_pontos DESC, u.nome";
$stmt = executeQuery($pdo, $sql, [$sistema['id'], $grupo_id]);
$classificacao = $stmt ? $stmt->fetchAll() : [];

// Buscar jogos do sistema
$sql = "SELECT * FROM sistema_pontuacao_jogos WHERE sistema_id = ? ORDER BY numero_jogo";
$stmt = executeQuery($pdo, $sql, [$sistema['id']]);
$jogos = $stmt ? $stmt->fetchAll() : [];

include 'includes/header.php';
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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Classificação Geral</h5>
            </div>
            <div class="card-body">
                <?php if (empty($classificacao)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-trophy fa-3x mb-3"></i>
                        <p>Nenhuma pontuação registrada ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Pos.</th>
                                    <th>Jogador</th>
                                    <th class="text-center">Jogos</th>
                                    <th class="text-end">Pontos</th>
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
                                                $avatar = $jogador['foto_perfil'] ?: 'assets/arquivos/logo.png';
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
                <?php endif; ?>
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pontosJogo as $p): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php 
                                                                    // Corrigir caminho da foto de perfil
                                                                    if (!empty($p['foto_perfil'])) {
                                                                        if (strpos($p['foto_perfil'], 'http') === 0 || strpos($p['foto_perfil'], '/') === 0) {
                                                                            $avatar = $p['foto_perfil'];
                                                                        } elseif (strpos($p['foto_perfil'], 'assets/') === 0) {
                                                                            $avatar = $p['foto_perfil'];
                                                                        } else {
                                                                            $avatar = 'assets/arquivos/' . $p['foto_perfil'];
                                                                        }
                                                                    } else {
                                                                        $avatar = 'assets/arquivos/logo.png';
                                                                    }
                                                                    ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="28" height="28" style="object-fit:cover;">
                                                                    <?php echo htmlspecialchars($p['nome']); ?>
                                                                </div>
                                                            </td>
                                                            <td class="text-end">
                                                                <strong><?php 
                                                                    $pontos = (float)$p['pontos'];
                                                                    $pontosFormatados = $pontos == (int)$pontos 
                                                                        ? number_format($pontos, 0, ',', '.') 
                                                                        : number_format($pontos, 2, ',', '.');
                                                                    echo $pontosFormatados;
                                                                ?></strong>
                                                            </td>
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

<?php include 'includes/footer.php'; ?>

