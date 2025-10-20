<?php
// diagnostico.php
echo "<pre>";
echo "=== DIAGNÓSTICO DO SISTEMA ===\n\n";

echo "Sistema Operacional: " . PHP_OS . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Diretório: " . __DIR__ . "\n";
echo "Web Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n\n";

echo "=== PERMISSÕES DE ARQUIVO ===\n";
$files = ['migration_log.txt', 'app_config.json', 'config.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $writable = is_writable($file) ? 'SIM' : 'NÃO';
        echo "$file: Existe, Permissões: " . substr(sprintf('%o', $perms), -4) . ", Gravável: $writable\n";
    } else {
        echo "$file: NÃO EXISTE\n";
    }
}

echo "\n=== EXTENSÕES PHP ===\n";
$extensions = ['pdo', 'pdo_pgsql', 'json', 'session'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? 'CARREGADA' : 'NÃO CARREGADA') . "\n";
}

echo "\n=== CONFIGURAÇÕES DE BANCO ===\n";
try {
    require_once 'config.php';
    echo "Origem: " . DB_SOURCE_HOST . ":" . DB_SOURCE_NAME . "\n";
    echo "Destino: " . DB_TARGET_HOST . ":" . DB_TARGET_NAME . "\n";
    
    // Testar conexões
    $source_test = testConnection(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
    echo "Conexão Origem: " . ($source_test['success'] ? 'OK' : 'FALHA') . "\n";
    
    $target_test = testConnection(DB_TARGET_HOST, DB_TARGET_NAME, DB_TARGET_USER, DB_TARGET_PASS, DB_TARGET_PORT);
    echo "Conexão Destino: " . ($target_test['success'] ? 'OK' : 'FALHA') . "\n";
    
} catch (Exception $e) {
    echo "Erro ao carregar configurações: " . $e->getMessage() . "\n";
}

echo "\n=== DIRETÓRIO TEMPORÁRIO ===\n";
echo "sys_temp_dir: " . sys_get_temp_dir() . "\n";
echo " Gravável: " . (is_writable(sys_get_temp_dir()) ? 'SIM' : 'NÃO') . "\n";

echo "</pre>";
?>