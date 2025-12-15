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

// Capturar torneio_id de múltiplas fontes para garantir que seja encontrado
$torneio_id = 0;
if (isset($_POST['torneio_id']) && $_POST['torneio_id'] > 0) {
    $torneio_id = (int)$_POST['torneio_id'];
} elseif (isset($_GET['torneio_id']) && $_GET['torneio_id'] > 0) {
    $torneio_id = (int)$_GET['torneio_id'];
} elseif (isset($_REQUEST['torneio_id']) && $_REQUEST['torneio_id'] > 0) {
    $torneio_id = (int)$_REQUEST['torneio_id'];
}

$nome_avulso = trim($_POST['nome_avulso'] ?? '');
$participantes = $_POST['participantes'] ?? [];

// Debug
error_log("=== DEBUG ADICIONAR PARTICIPANTE TORNEIO ===");
error_log("POST completo: " . print_r($_POST, true));
error_log("torneio_id capturado: " . $torneio_id);

if ($torneio_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Torneio inválido. ID não foi encontrado ou é inválido.',
        'error_code' => 'TORNEIO_ID_INVALIDO',
        'debug' => [
            'post_completo' => $_POST,
            'torneio_id_recebido' => $torneio_id
        ]
    ]);
    exit();
}

// Verificar permissão
$sql = "SELECT t.*, g.administrador_id 
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;
if (!$torneio) {
    echo json_encode(['success' => false, 'message' => 'Torneio não encontrado.']);
    exit();
}

$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin = $torneio['administrador_id'] && ((int)$torneio['administrador_id'] === (int)$_SESSION['user_id']);
if (!$sou_criador && !$sou_admin && !isAdmin($pdo, $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit();
}

// Verificar limite de participantes
$sql = "SELECT COUNT(*) AS total FROM torneio_participantes WHERE torneio_id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$totalAtual = $stmt ? (int)$stmt->fetch()['total'] : 0;

// Obter limite máximo do torneio
$maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;

// Determinar tipo do torneio
$tipoTorneio = $torneio['tipo'] ?? ($torneio['grupo_id'] ? 'grupo' : 'avulso');

// Verificar se a coluna 'ordem' existe
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
$columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
$tem_ordem = in_array('ordem', $columns);

$pdo->beginTransaction();
$mensagem_sucesso = 'Participante(s) adicionado(s) com sucesso!';
try {
    if ($tipoTorneio === 'grupo' && !empty($participantes)) {
        // Adicionar membros do grupo
        if ($tem_ordem) {
            $sql = "INSERT INTO torneio_participantes (torneio_id, usuario_id, ordem) VALUES (?, ?, ?)";
        } else {
            $sql = "INSERT INTO torneio_participantes (torneio_id, usuario_id) VALUES (?, ?)";
        }
        $stmt = $pdo->prepare($sql);
        
        foreach ($participantes as $usuario_id) {
            $usuario_id = (int)$usuario_id;
            if ($usuario_id > 0) {
                // Verificar se já está inscrito
                $check = executeQuery($pdo, "SELECT id FROM torneio_participantes WHERE torneio_id = ? AND usuario_id = ?", [$torneio_id, $usuario_id]);
                if (!$check || !$check->fetch()) {
                    if ($maxParticipantes > 0 && $totalAtual >= (int)$maxParticipantes) {
                        throw new Exception('Limite de participantes atingido.');
                    }
                    if ($tem_ordem) {
                        $stmt->execute([$torneio_id, $usuario_id, $totalAtual + 1]);
                    } else {
                        $stmt->execute([$torneio_id, $usuario_id]);
                    }
                    $totalAtual++;
                }
            }
        }
    } elseif ($tipoTorneio === 'avulso' && $nome_avulso !== '') {
        // Verificar se há vírgulas no nome (múltiplos nomes)
        $nomes = [];
        if (strpos($nome_avulso, ',') !== false) {
            // Separar nomes por vírgula
            $nomes_array = explode(',', $nome_avulso);
            foreach ($nomes_array as $nome) {
                $nome_trim = trim($nome);
                if (!empty($nome_trim)) {
                    $nomes[] = $nome_trim;
                }
            }
        } else {
            // Apenas um nome
            $nomes[] = trim($nome_avulso);
        }
        
        if (empty($nomes)) {
            throw new Exception('Nome inválido.');
        }
        
        // Verificar limite de participantes antes de adicionar
        $totalNomes = count($nomes);
        if ($maxParticipantes > 0 && ($totalAtual + $totalNomes) > (int)$maxParticipantes) {
            throw new Exception('Limite de participantes atingido. Você está tentando adicionar ' . $totalNomes . ' participante(s), mas só há ' . max(0, (int)$maxParticipantes - $totalAtual) . ' vaga(s) disponível(is).');
        }
        
        // Preparar SQL
        if ($tem_ordem) {
            $sql = "INSERT INTO torneio_participantes (torneio_id, nome_avulso, ordem) VALUES (?, ?, ?)";
        } else {
            $sql = "INSERT INTO torneio_participantes (torneio_id, nome_avulso) VALUES (?, ?)";
        }
        $stmt = $pdo->prepare($sql);
        
        $participantes_adicionados = 0;
        $participantes_duplicados = 0;
        
        foreach ($nomes as $nome) {
            // Verificar se já existe um participante com o mesmo nome neste torneio
            $check = executeQuery($pdo, "SELECT id FROM torneio_participantes WHERE torneio_id = ? AND nome_avulso = ?", [$torneio_id, $nome]);
            if ($check && $check->fetch()) {
                $participantes_duplicados++;
                continue; // Pular nomes duplicados
            }
            
            if ($tem_ordem) {
                $stmt->execute([$torneio_id, $nome, $totalAtual + $participantes_adicionados + 1]);
            } else {
                $stmt->execute([$torneio_id, $nome]);
            }
            $participantes_adicionados++;
        }
        
        if ($participantes_adicionados === 0) {
            if ($participantes_duplicados > 0) {
                throw new Exception('Todos os nomes informados já estão cadastrados neste torneio.');
            } else {
                throw new Exception('Nenhum participante foi adicionado.');
            }
        }
        
        $mensagem_sucesso = $participantes_adicionados . ' participante(s) adicionado(s) com sucesso!';
        if ($participantes_duplicados > 0) {
            $mensagem_sucesso .= ' (' . $participantes_duplicados . ' nome(s) já estavam cadastrados e foram ignorados)';
        }
    } else {
        throw new Exception('Dados inválidos.');
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $mensagem_sucesso]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

