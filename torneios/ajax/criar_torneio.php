<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exigirCsrfToken();
}

function responderCriacaoTorneio($success, $message, $extra = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit();
}

if (!isLoggedIn()) {
    responderCriacaoTorneio(false, 'Você precisa estar logado.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCriacaoTorneio(false, 'Método não permitido.', [], 405);
}

$nome = trim((string)($_POST['nome'] ?? ''));
$data_torneio = trim((string)($_POST['data_torneio'] ?? ''));
$tipo = trim((string)($_POST['tipo'] ?? 'grupo'));
$grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;

if ($nome === '' || $data_torneio === '') {
    responderCriacaoTorneio(false, 'Preencha todos os campos obrigatórios.', [], 422);
}

if (strlen($nome) > 120) {
    responderCriacaoTorneio(false, 'O nome do torneio deve ter no máximo 120 caracteres.', [], 422);
}

if (!in_array($tipo, ['grupo', 'avulso'], true)) {
    responderCriacaoTorneio(false, 'Tipo de torneio inválido.', [], 422);
}

$data = DateTime::createFromFormat('Y-m-d', $data_torneio);
$errosData = DateTime::getLastErrors();
$dataValida = $data instanceof DateTime && ($errosData === false || (($errosData['warning_count'] ?? 0) === 0 && ($errosData['error_count'] ?? 0) === 0));
if (!$dataValida) {
    responderCriacaoTorneio(false, 'Data do torneio inválida.', [], 422);
}

if ($tipo === 'grupo' && !$grupo_id) {
    responderCriacaoTorneio(false, 'Selecione um grupo para torneio do grupo.', [], 422);
}

try {
    $tables = $pdo->query("SHOW TABLES LIKE 'torneios'");
    if (!$tables || $tables->rowCount() === 0) {
        responderCriacaoTorneio(false, 'Estrutura de torneios não encontrada. Verifique a instalação do banco.', [], 500);
    }

    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios");
    $columns = $columnsQuery ? $columnsQuery->fetchAll(PDO::FETCH_COLUMN) : [];
    $temDataInicio = in_array('data_inicio', $columns, true);

    if (!$temDataInicio) {
        responderCriacaoTorneio(false, 'Estrutura de torneios incompleta. Verifique a instalação do banco.', [], 500);
    }

    if ($tipo === 'grupo') {
        $stmtGrupo = executeQuery($pdo, "SELECT id, administrador_id FROM grupos WHERE id = ? AND ativo = 1", [$grupo_id]);
        $grupo = $stmtGrupo ? $stmtGrupo->fetch(PDO::FETCH_ASSOC) : false;

        if (!$grupo) {
            responderCriacaoTorneio(false, 'Grupo não encontrado ou inativo.', [], 404);
        }

        if ((int)$grupo['administrador_id'] !== (int)$_SESSION['user_id'] && !isAdmin($pdo, $_SESSION['user_id'])) {
            responderCriacaoTorneio(false, 'Apenas o administrador do grupo pode criar torneios.', [], 403);
        }
    } else {
        $grupo_id = null;
    }

    if (in_array('grupo_id', $columns, true)) {
        $sqlCheck = "SELECT id, status FROM torneios
                     WHERE nome = ?
                       AND grupo_id <=> ?
                       AND status NOT IN ('Finalizado', 'Cancelado')
                     LIMIT 1";
        $stmtCheck = executeQuery($pdo, $sqlCheck, [$nome, $grupo_id]);
    } else {
        $stmtCheck = executeQuery(
            $pdo,
            "SELECT id, status FROM torneios WHERE nome = ? AND status NOT IN ('Finalizado', 'Cancelado') LIMIT 1",
            [$nome]
        );
    }
    $torneioExistente = $stmtCheck ? $stmtCheck->fetch(PDO::FETCH_ASSOC) : false;
    if ($torneioExistente) {
        responderCriacaoTorneio(false, 'Já existe um torneio ativo com esse nome neste contexto.', [], 409);
    }

    $campos = ['nome', 'data_inicio', 'criado_por'];
    $valores = [$nome, $data->format('Y-m-d'), (int)$_SESSION['user_id']];

    if (in_array('tipo', $columns, true)) {
        $campos[] = 'tipo';
        $valores[] = $tipo;
    }

    if (in_array('grupo_id', $columns, true)) {
        $campos[] = 'grupo_id';
        $valores[] = $grupo_id;
    }

    if (in_array('status', $columns, true)) {
        $campos[] = 'status';
        $valores[] = 'Criado';
    }

    if (in_array('inscricoes_abertas', $columns, true)) {
        $campos[] = 'inscricoes_abertas';
        $valores[] = 0;
    }

    $placeholders = implode(', ', array_fill(0, count($campos), '?'));
    $sql = "INSERT INTO torneios (" . implode(', ', $campos) . ") VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);

    if (!$stmt->execute($valores)) {
        error_log('Erro ao criar torneio: ' . json_encode($stmt->errorInfo()));
        responderCriacaoTorneio(false, 'Não foi possível criar o torneio agora.', [], 500);
    }

    responderCriacaoTorneio(true, 'Torneio criado com sucesso!', [
        'torneio_id' => (int)$pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    error_log('Erro ao criar torneio: ' . $e->getMessage());
    responderCriacaoTorneio(false, 'Não foi possível criar o torneio agora.', [], 500);
}
?>
