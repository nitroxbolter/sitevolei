<?php
// Conexão com o banco de dados
$configPath = __DIR__ . '/db_config.php';
if (is_file($configPath)) {
    $dbConfig = require $configPath;
    $host = $dbConfig['host'] ?? 'localhost';
    $dbname = $dbConfig['dbname'] ?? '';
    $username = $dbConfig['username'] ?? '';
    $password = $dbConfig['password'] ?? '';
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: '';
    $username = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
}

if ($dbname === '' || $username === '' || $password === '') {
    error_log('Configuração do banco incompleta.');
    http_response_code(500);
    die('Erro interno ao conectar ao banco de dados.');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); // Habilitar buffered queries para evitar erro de queries não finalizadas
} catch(PDOException $e) {
    error_log("Erro na conexão com o banco: " . $e->getMessage());
    http_response_code(500);
    die("Erro interno ao conectar ao banco de dados.");
}

// Função para executar queries de forma segura
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("Erro ao preparar query: " . $sql);
            return false;
        }
        $result = $stmt->execute($params);
        if (!$result) {
            $error = $stmt->errorInfo();
            error_log("Erro ao executar query: " . ($error[2] ?? 'Erro desconhecido') . " | SQL: " . $sql);
            return false;
        }
        return $stmt;
    } catch(PDOException $e) {
        error_log("Erro na query: " . $e->getMessage() . " | SQL: " . $sql);
        return false;
    }
}

// Função para obter um usuário por ID
function getUserById($pdo, $id) {
    if (empty($id) || $id <= 0) {
        return false;
    }
    $sql = "SELECT * FROM usuarios WHERE id = ? AND ativo = 1";
    $stmt = executeQuery($pdo, $sql, [$id]);
    return $stmt ? $stmt->fetch() : false;
}

// Função para obter um usuário por email
function getUserByEmail($pdo, $email) {
    $email = strtolower(trim((string)$email));
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
