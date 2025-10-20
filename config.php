<?php
// Prevenir acesso direto a este arquivo
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    header('HTTP/1.0 403 Forbidden');
    die('Acesso direto não permitido.');
}

session_start();

// Carregar configurações do arquivo
$config_file = 'app_config.json';
$default_config = [
    'source' => [
        'host' => '192.168.1.253',
        'database' => 'unico',
        'username' => 'postgres',
        'password' => 'passpostgres',
        'port' => '5432'
    ],
    'target' => [
        'host' => '10.10.1.251',
        'database' => 'unico2',
        'username' => 'postgres',
        'password' => 'passpost2',
        'port' => '5432'
    ],
    'tables' => ['produto', 'pecoproduto', 'ean', 'promocao'],
    'app' => [
        'users' => [
            'admin' => [
                'password' => 'admin123',
                'level' => 'admin'
            ],
            'operador' => [
                'password' => 'operador123',
                'level' => 'operador'
            ]
        ]
    ]
];

// Carregar configurações do arquivo se existir
if (file_exists($config_file)) {
    $loaded_config = json_decode(file_get_contents($config_file), true);
    if ($loaded_config !== null) {
        // Mesclar com padrão para garantir que todas as chaves existam
        $config = array_replace_recursive($default_config, $loaded_config);
    } else {
        // Se o JSON estiver corrompido, usar padrão e recriar arquivo
        $config = $default_config;
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    }
} else {
    $config = $default_config;
    // Salvar configuração padrão
    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
}

// Definir constantes a partir da configuração
define('DB_SOURCE_HOST', $config['source']['host']);
define('DB_SOURCE_NAME', $config['source']['database']);
define('DB_SOURCE_USER', $config['source']['username']);
define('DB_SOURCE_PASS', $config['source']['password']);
define('DB_SOURCE_PORT', $config['source']['port']);

define('DB_TARGET_HOST', $config['target']['host']);
define('DB_TARGET_NAME', $config['target']['database']);
define('DB_TARGET_USER', $config['target']['username']);
define('DB_TARGET_PASS', $config['target']['password']);
define('DB_TARGET_PORT', $config['target']['port']);

// Níveis de usuário
define('USER_LEVEL_OPERADOR', 'operador');
define('USER_LEVEL_ADMIN', 'admin');

// Função para validar login
function validateLogin($username, $password) {
    global $config;
    
    if (isset($config['app']['users'][$username])) {
        $user = $config['app']['users'][$username];
        if ($user['password'] === $password) {
            return [
                'success' => true,
                'username' => $username,
                'level' => $user['level']
            ];
        }
    }
    
    return ['success' => false];
}

// Função para verificar se usuário está logado
function checkAuth() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }
}

// Função para verificar permissão de administrador
function checkAdminAuth() {
    checkAuth();
    if ($_SESSION['user_level'] !== USER_LEVEL_ADMIN) {
        header('HTTP/1.0 403 Forbidden');
        die('Acesso negado. Permissão de administrador necessária.');
    }
}

// Função para obter nível do usuário atual
function getUserLevel() {
    return $_SESSION['user_level'] ?? USER_LEVEL_OPERADOR;
}

// Função para verificar se é administrador
function isAdmin() {
    return getUserLevel() === USER_LEVEL_ADMIN;
}

// Função para conectar ao banco
function connectDB($host, $dbname, $user, $pass, $port = 5432) {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro de conexão PostgreSQL: " . $e->getMessage());
    }
}

// Função para testar conexão
function testConnection($host, $dbname, $user, $pass, $port = 5432) {
    try {
        $pdo = connectDB($host, $dbname, $user, $pass, $port);
        
        // Testar consulta simples
        $stmt = $pdo->query("SELECT version() as pg_version");
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obter lista de todas as tabelas
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'success' => true,
            'version' => $version['pg_version'],
            'all_tables' => $all_tables,
            'tables_count' => count($all_tables)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Função para obter todas as tabelas do banco de origem
function getSourceTables() {
    try {
        $pdo = connectDB(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erro ao obter tabelas da origem: " . $e->getMessage());
        return [];
    }
}

// Função para obter tabelas atualmente configuradas
function getConfiguredTables() {
    global $config;
    return $config['tables'] ?? [];
}

/**
 * Função para inicializar o sistema de log
 */
function initLogSystem() {
    $log_file = 'migration_log.txt';
    
    // Tentar criar o arquivo se não existir
    if (!file_exists($log_file)) {
        $result = @file_put_contents($log_file, "=== SISTEMA DE LOG INICIADO ===\n");
        if ($result === false) {
            // Tentar criar em diretório temporário
            $log_file = sys_get_temp_dir() . '/migration_log.txt';
            @file_put_contents($log_file, "=== SISTEMA DE LOG INICIADO ===\n");
        }
    }
    
    // Tentar dar permissão de escrita
    @chmod($log_file, 0666);
    
    return $log_file;
}

/**
 * Função para escrever no log de forma segura
 */
function safeLogWrite($message, $log_file = 'migration_log.txt') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    // Tentar escrever no arquivo principal
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        // Fallback: tentar no diretório temporário
        $fallback_log = sys_get_temp_dir() . '/migration_log.txt';
        @file_put_contents($fallback_log, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Se ainda falhar, tentar criar um arquivo com timestamp
        $timestamp_log = 'migration_log_' . date('Y-m-d_His') . '.txt';
        @file_put_contents($timestamp_log, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Função para salvar configurações
function saveConfig($new_config) {
    $config_file = 'app_config.json';
    try {
        // Validar estrutura básica
        if (!isset($new_config['source']) || !isset($new_config['target']) || !isset($new_config['tables']) || !isset($new_config['app'])) {
            throw new Exception("Estrutura de configuração inválida");
        }
        
        // Garantir que todos os campos necessários existam
        $required_source = ['host', 'database', 'username', 'password', 'port'];
        $required_target = ['host', 'database', 'username', 'password', 'port'];
        
        foreach ($required_source as $field) {
            if (!isset($new_config['source'][$field]) || empty($new_config['source'][$field])) {
                throw new Exception("Campo obrigatório faltando: source.$field");
            }
        }
        
        foreach ($required_target as $field) {
            if (!isset($new_config['target'][$field]) || empty($new_config['target'][$field])) {
                throw new Exception("Campo obrigatório faltando: target.$field");
            }
        }
        
        // Validar se tables é um array
        if (!is_array($new_config['tables'])) {
            throw new Exception("Tabelas devem ser um array");
        }
        
        // Validar portas
        if (!is_numeric($new_config['source']['port']) || $new_config['source']['port'] <= 0) {
            throw new Exception("Porta de origem inválida");
        }
        
        if (!is_numeric($new_config['target']['port']) || $new_config['target']['port'] <= 0) {
            throw new Exception("Porta de destino inválida");
        }
        
        $result = file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            throw new Exception("Não foi possível salvar o arquivo de configuração. Verifique as permissões.");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao salvar configuração: " . $e->getMessage());
        return false;
    }
}

// Função para fazer logout
function doLogout() {
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Tratamento de erros personalizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
});

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Prevenir caching sensível
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>