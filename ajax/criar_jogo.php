<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['mensagem'] = 'Usuário não logado';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../jogos.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método não permitido';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../jogos.php');
    exit();
}

$titulo = sanitizar($_POST['titulo']);
$grupo_id = (int)($_POST['grupo_id'] ?? 0);
$data_jogo = $_POST['data_jogo'];
$data_fim_jogo = $_POST['data_fim_jogo'] ?? null;
$local = sanitizar($_POST['local']);
$max_jogadores = (int)$_POST['max_jogadores'];
$nivel_sugerido = sanitizar($_POST['nivel_sugerido']);
$descricao = sanitizar($_POST['descricao']);
$modalidade = sanitizar($_POST['modalidade'] ?? '');
$contato = sanitizar($_POST['contato'] ?? '');
$usuario_id = $_SESSION['user_id'];

// Validações
if (empty($titulo) || empty($data_jogo) || empty($local)) {
    $_SESSION['mensagem'] = 'Todos os campos obrigatórios devem ser preenchidos';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../jogos.php');
    exit();
}

// Removido limite mínimo de 6 e máximo de 20 jogadores

// Permitir criação com a data do dia (sem bloquear datas iguais/anteriores)

// Avulso: se vier 0, vamos gravar NULL para compatibilizar com FK (ON DELETE SET NULL)
if ($grupo_id === 0) {
    $grupo_id = null;
}

// Se houver grupo selecionado (não nulo), validar vínculo
if (!is_null($grupo_id)) {
    // Verificar se o usuário é membro do grupo selecionado
    $sql = "SELECT gm.id FROM grupo_membros gm 
            WHERE gm.usuario_id = ? AND gm.grupo_id = ? AND gm.ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$usuario_id, $grupo_id]);
    $membro = $stmt ? $stmt->fetch() : false;
    if (!$membro) {
        $_SESSION['mensagem'] = 'Você não é membro deste grupo';
        $_SESSION['tipo_mensagem'] = 'danger';
        header('Location: ../jogos.php');
        exit();
    }
}

// Criar jogo (detectar coluna modalidade se existir)
$temModalidadeJogo = false;
$stmtColsJ = executeQuery($pdo, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jogos' AND COLUMN_NAME IN ('modalidade','contato','data_fim')");
$colsJ = $stmtColsJ ? $stmtColsJ->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$temModalidadeJogo = in_array('modalidade', $colsJ, true);
$temContatoJogo = in_array('contato', $colsJ, true);
 $temDataFimJogo = in_array('data_fim', $colsJ, true);

$cols = ['grupo_id','titulo','descricao','data_jogo','local','max_jogadores','vagas_disponiveis','criado_por'];
$place = ['?','?','?','?','?','?','?','?'];
$params = [$grupo_id, $titulo, $descricao, $data_jogo, $local, $max_jogadores, $max_jogadores, $usuario_id];
if ($temModalidadeJogo) { $cols[] = 'modalidade'; $place[] = '?'; $params[] = ($modalidade ?: null); }
if ($temContatoJogo) { $cols[] = 'contato'; $place[] = '?'; $params[] = ($contato ?: null); }
if ($temDataFimJogo) { $cols[] = 'data_fim'; $place[] = '?'; $params[] = ($data_fim_jogo ?: null); }
$sql = "INSERT INTO jogos (".implode(',', $cols).") VALUES (".implode(',', $place).")";
$result = executeQuery($pdo, $sql, $params);

if ($result) {
    $jogo_id = $pdo->lastInsertId();
    
    // Auto-confirmar presença do criador
    $sql = "INSERT INTO confirmacoes_presenca (jogo_id, usuario_id, status) VALUES (?, ?, 'Confirmado')";
    executeQuery($pdo, $sql, [$jogo_id, $usuario_id]);
    
    // Não reduzir vagas por conta do criador: manter vagas_disponiveis igual ao valor informado
    
    $_SESSION['mensagem'] = 'Jogo criado com sucesso!';
    $_SESSION['tipo_mensagem'] = 'success';
    header('Location: ../jogos.php');
    exit();
} else {
    $_SESSION['mensagem'] = 'Erro ao criar jogo';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../jogos.php');
    exit();
}
?>
