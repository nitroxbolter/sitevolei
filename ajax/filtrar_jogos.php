<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Coleta filtros (apenas status por enquanto)
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Base SQL
$sql = "SELECT j.*, g.nome as grupo_nome, g.local_principal, u.nome as criado_por_nome,
               COUNT(cp.id) as total_confirmacoes,
               SUM(CASE WHEN cp.status = 'Confirmado' THEN 1 ELSE 0 END) as confirmados
        FROM jogos j
        LEFT JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN usuarios u ON j.criado_por = u.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id";
$params = [];
$where = [];

if ($status !== '') {
    $where[] = 'j.status = ?';
    $params[] = $status;
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY j.id ORDER BY j.data_jogo DESC';

$stmt = executeQuery($pdo, $sql, $params);
$jogos = $stmt ? $stmt->fetchAll() : [];

ob_start();
?>
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
                <?php $confirmadosSemCriador = max(0, (int)$jogo['confirmados'] - 1); ?>
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
                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($jogo['max_jogadores'] > 0 ? ($confirmadosSemCriador / $jogo['max_jogadores']) * 100 : 0); ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="../jogo.php?id=<?php echo $jogo['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>Ver Detalhes
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php
$html = ob_get_clean();
echo json_encode(['success'=>true, 'html'=>$html]);
exit();
?>


