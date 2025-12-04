<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$busca = trim($_POST['busca'] ?? '');
$grupo_id = !empty($_POST['grupo']) ? (int)$_POST['grupo'] : null;
$status = trim($_POST['status'] ?? '');

// Construir query base
$sql = "SELECT t.*, g.nome as grupo_nome, g.logo_id as grupo_logo_id, u.nome as criado_por_nome,
               COUNT(tp.id) as total_inscritos
        FROM torneios t
        LEFT JOIN grupos g ON t.grupo_id = g.id
        LEFT JOIN usuarios u ON t.criado_por = u.id
        LEFT JOIN torneio_participantes tp ON t.id = tp.torneio_id
        WHERE 1=1";

$params = [];

// Filtro por busca (nome)
if ($busca !== '') {
    $sql .= " AND t.nome LIKE ?";
    $params[] = '%' . $busca . '%';
}

// Filtro por grupo
if ($grupo_id) {
    $sql .= " AND t.grupo_id = ?";
    $params[] = $grupo_id;
}

// Filtro por status
if ($status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
} else {
    // Se não especificar status, mostrar todos (incluindo finalizados)
    $sql .= " AND t.status IN ('Criado', 'Inscrições Abertas', 'Em Andamento', 'Finalizado')";
}

$sql .= " GROUP BY t.id ORDER BY CASE 
            WHEN t.status = 'Finalizado' THEN 1 
            ELSE 0 
        END, t.data_inicio ASC";

$stmt = executeQuery($pdo, $sql, $params);
$torneios = $stmt ? $stmt->fetchAll() : [];

// Gerar HTML dos torneios
$html = '';
if (empty($torneios)) {
    $html = '<div class="col-12"><div class="text-center py-5">';
    $html .= '<i class="fas fa-trophy fa-4x text-muted mb-3"></i>';
    $html .= '<h5 class="text-muted">Nenhum torneio encontrado</h5>';
    $html .= '<p class="text-muted">Tente ajustar os filtros.</p>';
    $html .= '</div></div>';
} else {
    foreach ($torneios as $torneio) {
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
        
        $html .= '<div class="col-md-6 col-lg-4 mb-4">';
        $html .= '<div class="card h-100 torneio-card ' . $cardClass . '" style="' . $cardStyle . '">';
        $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
        $html .= '<h6 class="mb-0">';
        $html .= '<i class="fas fa-trophy me-2"></i>';
        $html .= htmlspecialchars($torneio['nome']);
        $html .= '</h6>';
        
        $statusClass = $torneio['status'] === 'Inscrições Abertas' ? 'success' : 
                      ($torneio['status'] === 'Em Andamento' ? 'warning' : 
                      ($torneio['status'] === 'Finalizado' ? 'dark' :
                      ($torneio['status'] === 'Criado' ? 'info' : 'secondary')));
        $html .= '<span class="badge bg-' . $statusClass . '">';
        $html .= htmlspecialchars($torneio['status']);
        $html .= '</span>';
        $html .= '</div>';
        
        $html .= '<div class="card-body">';
        $html .= '<p class="card-text">';
        $html .= '<i class="fas fa-users me-2"></i>';
        $html .= '<strong>Grupo:</strong> ';
        if (!empty($torneio['grupo_logo_id']) && $torneio['grupo_nome']) {
            $html .= '<img src="../../assets/arquivos/logosgrupos/' . (int)$torneio['grupo_logo_id'] . '.png" ';
            $html .= 'alt="' . htmlspecialchars($torneio['grupo_nome']) . '" ';
            $html .= 'class="rounded-circle me-2" width="20" height="20" style="object-fit:cover;" ';
            $html .= 'onerror="this.style.display=\'none\';">';
        }
        $html .= htmlspecialchars($torneio['grupo_nome'] ?: 'Avulso');
        $html .= '</p>';
        
        $dataInicio = $torneio['data_inicio'] ?? '';
        if ($dataInicio) {
            $html .= '<p class="card-text">';
            $html .= '<i class="fas fa-calendar me-2"></i>';
            $html .= '<strong>Início:</strong> ' . date('d/m/Y', strtotime($dataInicio));
            $html .= '</p>';
        }
        
        $maxParticipantes = $torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0;
        $html .= '<p class="card-text">';
        $html .= '<i class="fas fa-user-friends me-2"></i>';
        $html .= '<strong>Inscritos:</strong> ' . (int)$torneio['total_inscritos'];
        if ($maxParticipantes > 0) {
            $html .= '/' . (int)$maxParticipantes;
        }
        $html .= '</p>';
        
        $html .= '<p class="card-text">';
        $html .= '<i class="fas fa-user me-2"></i>';
        $html .= '<strong>Criado por:</strong> ' . htmlspecialchars($torneio['criado_por_nome']);
        $html .= '</p>';
        
        if (!empty($torneio['descricao'])) {
            $html .= '<p class="card-text">';
            $html .= '<small class="text-muted">' . htmlspecialchars(substr($torneio['descricao'], 0, 100)) . '...</small>';
            $html .= '</p>';
        }
        
        $html .= '<div class="d-flex justify-content-between align-items-center">';
        if ($dataInicio) {
            $agora = new DateTime();
            $dataTorneio = new DateTime($dataInicio);
            $diferenca = $agora->diff($dataTorneio);
            $tempoRestante = '';
            if ($agora > $dataTorneio) {
                $tempoRestante = 'Já aconteceu';
            } elseif ($diferenca->days > 0) {
                $tempoRestante = $diferenca->days . ' dia(s)';
            } elseif ($diferenca->h > 0) {
                $tempoRestante = $diferenca->h . ' hora(s)';
            } else {
                $tempoRestante = $diferenca->i . ' minuto(s)';
            }
            
            $html .= '<small class="text-muted">';
            $html .= '<i class="fas fa-clock me-1"></i>' . $tempoRestante;
            $html .= '</small>';
        }
        
        if ($maxParticipantes > 0 && $dataInicio) {
            $percentual = min(100, ($torneio['total_inscritos'] / $maxParticipantes) * 100);
            $html .= '<div class="progress" style="width: 100px; height: 8px;">';
            $html .= '<div class="progress-bar" role="progressbar" style="width: ' . $percentual . '%"></div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="card-footer">';
        $html .= '<div class="d-flex justify-content-between">';
        $html .= '<a href="torneio.php?id=' . (int)$torneio['id'] . '" class="btn btn-primary btn-sm">';
        $html .= '<i class="fas fa-eye me-1"></i>Ver Detalhes';
        $html .= '</a>';
        
        if ($torneio['status'] === 'Inscrições Abertas') {
            $html .= '<button class="btn btn-success btn-sm" onclick="inscreverTorneio(' . (int)$torneio['id'] . ')">';
            $html .= '<i class="fas fa-user-plus me-1"></i>Inscrever-se';
            $html .= '</button>';
        } elseif ($torneio['status'] === 'Criado') {
            $html .= '<a href="admin/gerenciar_torneio.php?id=' . (int)$torneio['id'] . '" class="btn btn-info btn-sm">';
            $html .= '<i class="fas fa-cog me-1"></i>Gerenciar';
            $html .= '</a>';
        } elseif ($torneio['status'] === 'Finalizado') {
            // Verificar se é criador/admin para mostrar botão de visualizar
            $sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
            $sou_admin_grupo = false;
            if ($torneio['grupo_id']) {
                $sql_check = "SELECT administrador_id FROM grupos WHERE id = ?";
                $stmt_check = executeQuery($pdo, $sql_check, [$torneio['grupo_id']]);
                $grupo_check = $stmt_check ? $stmt_check->fetch() : false;
                $sou_admin_grupo = $grupo_check && ((int)$grupo_check['administrador_id'] === (int)$_SESSION['user_id']);
            }
            if ($sou_criador || $sou_admin_grupo || isAdmin($pdo, $_SESSION['user_id'])) {
                $html .= '<a href="admin/gerenciar_torneio.php?id=' . (int)$torneio['id'] . '" class="btn btn-secondary btn-sm">';
                $html .= '<i class="fas fa-eye me-1"></i>Visualizar';
                $html .= '</a>';
            } else {
                $html .= '<span class="badge bg-dark">' . htmlspecialchars($torneio['status']) . '</span>';
            }
        } else {
            $html .= '<span class="badge bg-warning">' . htmlspecialchars($torneio['status']) . '</span>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
}

echo json_encode([
    'success' => true,
    'html' => $html
]);
?>

