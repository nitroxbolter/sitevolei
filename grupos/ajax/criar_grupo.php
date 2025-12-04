<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['mensagem'] = 'Usuário não logado';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Método não permitido';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

$nome = sanitizar($_POST['nome']);
$descricao = sanitizar($_POST['descricao']);
$local_principal = sanitizar($_POST['local_principal']);
$contato = sanitizar($_POST['contato'] ?? '');
$nivel_grupo = sanitizar($_POST['nivel_grupo']);
$logoCropped = $_POST['logo_cropped'] ?? '';
$modalidade = sanitizar($_POST['modalidade'] ?? '');
$usuario_id = $_SESSION['user_id'];

// Validações
if (empty($nome) || empty($local_principal)) {
    $_SESSION['mensagem'] = 'Nome e local são obrigatórios';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}

// Removido campo de máximo de jogadores (usar default do banco)

// Criar grupo
// Detectar colunas opcionais existentes (nivel, contato)
$temNivel = false;
$temContato = false;
$sqlCol = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos' AND COLUMN_NAME IN ('nivel','contato','modalidade')";
$stmtCol = executeQuery($pdo, $sqlCol);
if ($stmtCol) {
    $cols = $stmtCol->fetchAll(PDO::FETCH_COLUMN, 0);
    $temNivel = in_array('nivel', $cols, true);
    $temContato = in_array('contato', $cols, true);
    $temModalidade = in_array('modalidade', $cols, true);
}

// Preparar logo, se enviada (Base64 PNG/JPEG), salva como 128x128
$logo_id = null;
if (!empty($logoCropped) && preg_match('/^data:image\/(png|jpeg);base64,/', $logoCropped)) {
    $dadosBase64 = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $logoCropped);
    $dadosBase64 = str_replace(' ', '+', $dadosBase64);
    $binario = base64_decode($dadosBase64);
    if ($binario !== false) {
        // Criar registro em logos_grupos para obter ID
        $stmtLogo = executeQuery($pdo, "INSERT INTO logos_grupos (caminho) VALUES ('')", []);
        if ($stmtLogo) {
            $novoId = (int)$pdo->lastInsertId();
            $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'arquivos' . DIRECTORY_SEPARATOR . 'logosgrupos';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $arquivo = $dir . DIRECTORY_SEPARATOR . $novoId . '.png';
            // Redimensionar no servidor para 128x128 (se GD disponível); caso contrário, salvar como veio
            if (function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($binario);
                if ($src) {
                    $dst = imagecreatetruecolor(128, 128);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $width = imagesx($src);
                    $height = imagesy($src);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, 128, 128, $width, $height);
                    $saved = imagepng($dst, $arquivo);
                    imagedestroy($dst);
                    imagedestroy($src);
                } else {
                    $saved = (file_put_contents($arquivo, $binario) !== false);
                }
            } else {
                $saved = (file_put_contents($arquivo, $binario) !== false);
            }
            if ($saved) {
                $relPath = 'assets/arquivos/logosgrupos/' . $novoId . '.png';
                executeQuery($pdo, "UPDATE logos_grupos SET caminho = ? WHERE id = ?", [$relPath, $novoId]);
                $logo_id = $novoId;
            } else {
                executeQuery($pdo, "DELETE FROM logos_grupos WHERE id = ?", [$novoId]);
            }
        }
    }
}

// Montar INSERT dinamicamente conforme colunas disponíveis
$campos = ['nome', 'descricao', 'administrador_id', 'local_principal'];
$placeholders = ['?', '?', '?', '?'];
$params = [$nome, $descricao, $usuario_id, $local_principal];

if ($temNivel) { $campos[] = 'nivel'; $placeholders[] = '?'; $params[] = ($nivel_grupo ?: null); }
if ($temContato) { $campos[] = 'contato'; $placeholders[] = '?'; $params[] = ($contato ?: null); }
if ($temModalidade) { $campos[] = 'modalidade'; $placeholders[] = '?'; $params[] = ($modalidade ?: null); }

// Se existir logo_id na tabela grupos, incluir
$sqlHasLogoId = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos' AND COLUMN_NAME = 'logo_id'";
$stmtHasLogoId = executeQuery($pdo, $sqlHasLogoId);
if ($stmtHasLogoId && $stmtHasLogoId->fetch()) {
    $campos[] = 'logo_id';
    $placeholders[] = '?';
    $params[] = $logo_id;
}

$sql = "INSERT INTO grupos (" . implode(',', $campos) . ") VALUES (" . implode(',', $placeholders) . ")";
$result = executeQuery($pdo, $sql, $params);

if ($result) {
    $grupo_id = $pdo->lastInsertId();
    
    // Adicionar criador como membro do grupo
    $sql = "INSERT INTO grupo_membros (grupo_id, usuario_id) VALUES (?, ?)";
    executeQuery($pdo, $sql, [$grupo_id, $usuario_id]);
    
    $_SESSION['mensagem'] = 'Grupo criado com sucesso!';
    $_SESSION['tipo_mensagem'] = 'success';
    header('Location: ../grupos.php');
    exit();
} else {
    $_SESSION['mensagem'] = 'Erro ao criar grupo';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../grupos.php');
    exit();
}
?>
