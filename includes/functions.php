<?php
// Funções globais para o sistema

// Função para calcular reputação do usuário
function calcularReputacao($pdo, $usuario_id) {
    $sql = "SELECT SUM(pontos) as total FROM avaliacoes_reputacao WHERE avaliado_id = ?";
    $stmt = executeQuery($pdo, $sql, [$usuario_id]);
    $result = $stmt ? $stmt->fetch() : false;
    return $result ? $result['total'] : 0;
}

// Função para atualizar reputação do usuário
function atualizarReputacao($pdo, $usuario_id) {
    $nova_reputacao = (int)calcularReputacao($pdo, $usuario_id);
    if ($nova_reputacao < 0) { $nova_reputacao = 0; }
    $sql = "UPDATE usuarios SET reputacao = ? WHERE id = ?";
    return executeQuery($pdo, $sql, [$nova_reputacao, $usuario_id]);
}

// Função para obter próximos jogos
function getProximosJogos($pdo, $limite = 5) {
    $limite = max(1, (int)$limite);
    $sql = "SELECT j.*, g.nome as grupo_nome, g.local_principal,
                   COUNT(DISTINCT cp.id) as jogadores_confirmados
            FROM jogos j 
            JOIN grupos g ON j.grupo_id = g.id 
            LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id AND cp.status = 'Confirmado'
            WHERE j.status = 'Aberto' AND DATE(j.data_jogo) >= CURDATE()
            GROUP BY j.id, j.titulo, j.data_jogo, j.data_fim, j.local, j.max_jogadores, j.status, g.nome, g.local_principal
            ORDER BY j.data_jogo ASC 
            LIMIT $limite";
    $stmt = executeQuery($pdo, $sql);
    return $stmt ? $stmt->fetchAll() : [];
}

// Função para obter torneios ativos
function getTorneiosAtivos($pdo, $limite = 3) {
    $limite = max(1, (int)$limite);
    $sql = "SELECT t.*, g.nome as grupo_nome 
            FROM torneios t 
            LEFT JOIN grupos g ON t.grupo_id = g.id
            WHERE (t.inscricoes_abertas = 1 OR t.status IN ('Inscrições Abertas', 'Em Andamento'))
              AND t.status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY t.data_inicio ASC 
            LIMIT $limite";
    $stmt = executeQuery($pdo, $sql);
    return $stmt ? $stmt->fetchAll() : [];
}

// Função para obter ranking de jogadores
function getRankingJogadores($pdo, $limite = 10) {
    $limite = max(1, (int)$limite);
    $sql = "SELECT u.id, u.nome, u.reputacao, u.posicao_preferida,
                   COUNT(cp.id) as total_jogos,
                   SUM(CASE WHEN cp.status = 'Confirmado' THEN 1 ELSE 0 END) as jogos_confirmados
            FROM usuarios u
            LEFT JOIN confirmacoes_presenca cp ON u.id = cp.usuario_id
            LEFT JOIN jogos j ON cp.jogo_id = j.id
            WHERE u.ativo = 1 AND j.status = 'Finalizado'
            GROUP BY u.id
            ORDER BY u.reputacao DESC, jogos_confirmados DESC
            LIMIT $limite";
    $stmt = executeQuery($pdo, $sql);
    return $stmt ? $stmt->fetchAll() : [];
}

// Função para verificar se usuário está confirmado no jogo
function isJogoConfirmado($pdo, $jogo_id, $usuario_id) {
    $sql = "SELECT status FROM confirmacoes_presenca WHERE jogo_id = ? AND usuario_id = ?";
    $stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
    $result = $stmt ? $stmt->fetch() : false;
    return $result && $result['status'] === 'Confirmado';
}

// Verificar se participação está pendente
function isJogoPendente($pdo, $jogo_id, $usuario_id) {
    $sql = "SELECT status FROM confirmacoes_presenca WHERE jogo_id = ? AND usuario_id = ?";
    $stmt = executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
    $result = $stmt ? $stmt->fetch() : false;
    return $result && $result['status'] === 'Pendente';
}

// Função para confirmar presença no jogo
function confirmarPresenca($pdo, $jogo_id, $usuario_id, $status = 'Confirmado') {
    $sql = "INSERT INTO confirmacoes_presenca (jogo_id, usuario_id, status) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = ?";
    return executeQuery($pdo, $sql, [$jogo_id, $usuario_id, $status, $status]);
}

// Função para obter grupos do usuário
function getGruposUsuario($pdo, $usuario_id) {
    $sql = "SELECT g.*, gm.data_entrada, 
                   CASE WHEN g.administrador_id = ? THEN 1 ELSE 0 END as is_admin
            FROM grupos g
            JOIN grupo_membros gm ON g.id = gm.grupo_id
            WHERE gm.usuario_id = ? AND gm.ativo = 1 AND g.ativo = 1
            ORDER BY g.nome";
    $stmt = executeQuery($pdo, $sql, [$usuario_id, $usuario_id]);
    return $stmt ? $stmt->fetchAll() : [];
}

// Função para formatar data
function formatarData($data, $formato = 'd/m/Y H:i') {
    return date($formato, strtotime($data));
}

// Função para calcular tempo restante
function tempoRestante($data) {
    $agora = new DateTime();
    $data_jogo = new DateTime($data);
    $diferenca = $agora->diff($data_jogo);
    
    if ($agora > $data_jogo) {
        return 'Jogo já aconteceu';
    }
    
    if ($diferenca->days > 0) {
        return $diferenca->days . ' dia(s) restante(s)';
    } elseif ($diferenca->h > 0) {
        return $diferenca->h . ' hora(s) restante(s)';
    } else {
        return $diferenca->i . ' minuto(s) restante(s)';
    }
}

// Tempo restante até o fim do jogo; se já acabou, informa que aconteceu
function tempoRestanteAteFim($data_inicio, $data_fim = null) {
    $agora = new DateTime();
    $inicio = new DateTime($data_inicio);
    $fim = $data_fim ? new DateTime($data_fim) : null;
    if ($fim) {
        if ($agora >= $fim) { return 'Jogo já aconteceu'; }
        $alvo = $agora < $inicio ? $inicio : $fim; // antes do jogo: até começar; durante: até terminar
    } else {
        if ($agora >= $inicio) { return 'Jogo já aconteceu'; }
        $alvo = $inicio;
    }
    $dif = $agora->diff($alvo);
    if ($dif->days > 0) return $dif->days.' dia(s)';
    if ($dif->h > 0) return $dif->h.' hora(s)';
    return $dif->i.' minuto(s)';
}

function tempoStatusJogo($data_inicio, $data_fim = null) {
    $agora = new DateTime();
    $inicio = new DateTime($data_inicio);
    $fim = $data_fim ? new DateTime($data_fim) : null;
    if ($fim && $agora >= $fim) return 'Jogo já aconteceu';
    if (!$fim && $agora >= $inicio) return 'Jogo já aconteceu';
    if ($agora < $inicio) {
        return 'Começa em ' . tempoRestanteAteFim($data_inicio, $data_fim);
    }
    // Entre início e fim
    if ($fim && $agora >= $inicio && $agora < $fim) {
        return 'Termina em ' . tempoRestanteAteFim($data_inicio, $data_fim);
    }
    return tempoRestanteAteFim($data_inicio, $data_fim);
}

// Formata período: exibe início completo e apenas hora do fim
function formatarPeriodoJogo($inicio, $fim = null) {
    if (!$fim) return formatarData($inicio);
    $inicioFmt = formatarData($inicio);
    $horaFim = date('H:i', strtotime($fim));
    return $inicioFmt.' — '.$horaFim;
}

// Atualiza status dos jogos conforme data/hora atual e datas de início/fim
function atualizarStatusJogos($pdo) {
    // Se a coluna data_fim não existir, evita erro
    try {
        // 1) Finalizado: já passou do fim
        executeQuery($pdo, "UPDATE jogos SET status = 'Finalizado' WHERE data_fim IS NOT NULL AND data_fim <= NOW() AND status <> 'Finalizado'");
        // 2) Fechado: sem vagas (independente do horário), não marca finalizado automaticamente
        executeQuery($pdo, "UPDATE jogos SET status = 'Fechado' WHERE vagas_disponiveis <= 0 AND status NOT IN ('Finalizado','Fechado')");
        // 3) Em Andamento: entre início e fim (se existir fim) ou já passou do início (sem data_fim)
        executeQuery($pdo, "UPDATE jogos SET status = 'Em Andamento' WHERE status NOT IN ('Finalizado','Fechado') AND ((data_fim IS NOT NULL AND NOW() BETWEEN data_jogo AND data_fim) OR (data_fim IS NULL AND NOW() >= data_jogo))");
        // 4) Aberto: ainda não começou (com data_fim ou sem) e possui vagas
        executeQuery($pdo, "UPDATE jogos SET status = 'Aberto' WHERE data_jogo > NOW() AND vagas_disponiveis > 0 AND status <> 'Aberto'");
    } catch (Exception $e) {
        // Silencia caso estrutura não tenha as colunas esperadas
    }
}

// Função para gerar cores aleatórias para times
function gerarCorTime() {
    $cores = ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'];
    return $cores[array_rand($cores)];
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para gerar hash da senha
function hashSenha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

// Função para verificar senha
function verificarSenha($senha, $hash) {
    return password_verify($senha, $hash);
}

// Função para sanitizar entrada
function sanitizar($dados) {
    return htmlspecialchars(strip_tags(trim($dados)));
}

function normalizarEmail($email) {
    return strtolower(trim((string)$email));
}

function normalizarUsuario($usuario) {
    return strtolower(trim(strip_tags((string)$usuario)));
}

function normalizarCpf($cpf) {
    return preg_replace('/\D+/', '', (string)$cpf);
}

function normalizarTelefone($telefone) {
    return preg_replace('/\D+/', '', (string)$telefone);
}

function validarCpf($cpf) {
    $cpf = normalizarCpf($cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int)$cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

function validarUsuarioLogin($usuario) {
    return (bool)preg_match('/^[a-z0-9_]{3,50}$/', $usuario);
}

function senhaAtendePolitica($senha) {
    return is_string($senha) && strlen($senha) >= 8;
}

function usuarioEmailCpfEmUso($pdo, $usuario, $email, $cpf, $ignorar_id = 0) {
    $condicoes = ["usuario = ?", "email = ?"];
    $params = [$usuario, $email];

    if ($cpf !== '') {
        $condicoes[] = "cpf = ?";
        $params[] = $cpf;
    }

    $sql = "SELECT usuario, email, cpf FROM usuarios WHERE (" . implode(' OR ', $condicoes) . ")";

    if ((int)$ignorar_id > 0) {
        $sql .= " AND id != ?";
        $params[] = (int)$ignorar_id;
    }

    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

// Proteção CSRF compartilhada pelas ações autenticadas do site.
function csrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfTokenValido() {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['csrf_token'])) {
        return false;
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function exigirCsrfToken() {
    if (csrfTokenValido()) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'
    ]);
    exit();
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function podeGerenciarTorneio($pdo, $torneio_id, $usuario_id) {
    $sql = "SELECT t.criado_por, g.administrador_id
            FROM torneios t
            LEFT JOIN grupos g ON g.id = t.grupo_id
            WHERE t.id = ?";
    $stmt = executeQuery($pdo, $sql, [(int)$torneio_id]);
    $torneio = $stmt ? $stmt->fetch() : false;

    if (!$torneio) {
        return false;
    }

    return (int)$torneio['criado_por'] === (int)$usuario_id
        || (!empty($torneio['administrador_id']) && (int)$torneio['administrador_id'] === (int)$usuario_id)
        || isAdmin($pdo, $usuario_id);
}

function podeGerenciarJogo($pdo, $jogo_id, $usuario_id) {
    $sql = "SELECT j.criado_por, g.administrador_id
            FROM jogos j
            LEFT JOIN grupos g ON g.id = j.grupo_id
            WHERE j.id = ?";
    $stmt = executeQuery($pdo, $sql, [(int)$jogo_id]);
    $jogo = $stmt ? $stmt->fetch() : false;

    if (!$jogo) {
        return false;
    }

    return (int)$jogo['criado_por'] === (int)$usuario_id
        || (!empty($jogo['administrador_id']) && (int)$jogo['administrador_id'] === (int)$usuario_id)
        || isAdmin($pdo, $usuario_id);
}

// Função para exibir mensagens de erro/sucesso
function exibirMensagem($tipo, $mensagem) {
    $classe = $tipo === 'sucesso' ? 'alert-success' : 'alert-danger';
    return "<div class='alert $classe alert-dismissible fade show' role='alert'>
                $mensagem
                <button type='button' class='close' data-dismiss='alert'>
                    <span>&times;</span>
                </button>
            </div>";
}

// Função para verificar se usuário é premium
function isPremium($pdo, $usuario_id) {
    $sql = "SELECT is_premium, premium_expira_em FROM usuarios WHERE id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$usuario_id]);
    $usuario = $stmt ? $stmt->fetch() : false;
    
    if (!$usuario || !$usuario['is_premium']) {
        return false;
    }
    
    // Verificar se o premium não expirou
    if ($usuario['premium_expira_em'] && strtotime($usuario['premium_expira_em']) < time()) {
        return false;
    }
    
    return true;
}

// Função para verificar se usuário é administrador
function isAdmin($pdo, $usuario_id) {
    // Administrador do site: is_admin >= 3 (1 = admin de grupo, 3 = admin do site)
    $sql = "SELECT COALESCE(is_admin, 0) AS is_admin FROM usuarios WHERE id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$usuario_id]);
    $usuario = $stmt ? $stmt->fetch() : false;
    if (!$usuario) {
        return false;
    }
    return ((int)($usuario['is_admin'] ?? 0) >= 3);
}

// Função para verificar se usuário tem acesso premium
function requirePremium($pdo) {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
        exit();
    }
    
    if (!isPremium($pdo, $_SESSION['user_id'])) {
        $_SESSION['mensagem'] = 'Acesso restrito a usuários premium.';
        $_SESSION['tipo_mensagem'] = 'warning';
        header('Location: dashboard.php');
        exit();
    }
}

// Função para verificar se usuário é administrador
function requireAdmin($pdo) {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
        exit();
    }
    
    if (!isAdmin($pdo, $_SESSION['user_id'])) {
        $_SESSION['mensagem'] = 'Acesso restrito a administradores.';
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: dashboard.php');
        exit();
    }
}

// Função para verificar se usuário pode criar grupos
function podeCriarGrupo($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    return true; // Todos os usuários logados podem criar grupos
}

// Função para verificar se usuário pode criar torneios
function podeCriarTorneio($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    return true; // Todos os usuários logados podem criar torneios
}

// Função para verificar se usuário pode participar de grupos
function podeParticiparGrupo($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    return true; // Todos os usuários logados podem participar
}

// Função para mostrar mensagem de login necessário
function mostrarMensagemLoginNecessario() {
    $_SESSION['mensagem'] = 'Você precisa fazer login para acessar esta funcionalidade.';
    $_SESSION['tipo_mensagem'] = 'warning';
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: auth/login.php');
    exit();
}

// Função para upload de foto
function uploadFoto($arquivo, $pasta = 'uploads/profile_pics/') {
    if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $tamanho_maximo = 2 * 1024 * 1024;
    if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name']) || ($arquivo['size'] ?? 0) > $tamanho_maximo) {
        return false;
    }

    if (!file_exists($pasta)) {
        mkdir($pasta, 0777, true);
    }
    
    $tipos_permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    
    if (!isset($tipos_permitidos[$mime]) || !@getimagesize($arquivo['tmp_name'])) {
        return false;
    }
    
    $nome_arquivo = bin2hex(random_bytes(16)) . '.' . $tipos_permitidos[$mime];
    $caminho_completo = $pasta . $nome_arquivo;
    
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return $caminho_completo;
    }
    
    return false;
}

function uploadImagemSegura($arquivo, $pasta, $prefixoRelativo, $permitirWebp = false) {
    if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $tamanho_maximo = 2 * 1024 * 1024;
    if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name']) || ($arquivo['size'] ?? 0) > $tamanho_maximo) {
        return false;
    }

    $tipos_permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];
    if ($permitirWebp) {
        $tipos_permitidos['image/webp'] = 'webp';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    if (!isset($tipos_permitidos[$mime]) || !@getimagesize($arquivo['tmp_name'])) {
        return false;
    }

    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }

    $nome_arquivo = bin2hex(random_bytes(16)) . '.' . $tipos_permitidos[$mime];
    $caminho_completo = rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . $nome_arquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return false;
    }

    return rtrim($prefixoRelativo, '/\\') . '/' . $nome_arquivo;
}

// Todos os POSTs das APIs AJAX exigem o token enviado pelo cabeçalho comum.
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/ajax/') !== false) {
        exigirCsrfToken();
    }
}
?>
