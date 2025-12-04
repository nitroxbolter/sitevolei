<?php
// Conexão com o banco de dados
$host = '127.0.0.1';
$dbname = 'database_vb';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Função para executar queries de forma segura
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Erro na query: " . $e->getMessage());
        return false;
    }
}

// Função para obter um usuário por ID
function getUserById($pdo, $id) {
    $sql = "SELECT * FROM usuarios WHERE id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$id]);
    return $stmt ? $stmt->fetch() : false;
}

// Função para obter um usuário por email
function getUserByEmail($pdo, $email) {
    $sql = "SELECT * FROM usuarios WHERE email = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$email]);
    return $stmt ? $stmt->fetch() : false;
}

// Função para verificar se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Função para redirecionar se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit();
    }
}

// Função para verificar se é administrador do grupo
function isGroupAdmin($pdo, $grupo_id, $usuario_id) {
    $sql = "SELECT administrador_id FROM grupos WHERE id = ?";
    $stmt = executeQuery($pdo, $sql, [$grupo_id]);
    $grupo = $stmt ? $stmt->fetch() : false;
    return $grupo && $grupo['administrador_id'] == $usuario_id;
}
?>
