<?php
// check_permissions.php - Script para verificar permissões
echo "=== VERIFICAÇÃO DE PERMISSÕES ===\n\n";

$log_file = 'migration_log.txt';

// Testar escrita no arquivo de log
if (is_writable($log_file)) {
    echo "✅ migration_log.txt - PERMISSÃO DE ESCRITA OK\n";
} else {
    echo "❌ migration_log.txt - SEM PERMISSÃO DE ESCRITA\n";
    
    // Tentar criar o arquivo
    if (touch($log_file)) {
        echo "✅ migration_log.txt - ARQUIVO CRIADO COM SUCESSO\n";
        chmod($log_file, 0666);
        echo "✅ Permissões ajustadas para 0666\n";
    } else {
        echo "❌ Não foi possível criar o arquivo\n";
    }
}

// Verificar permissões do diretório
$directory = '.';
if (is_writable($directory)) {
    echo "✅ Diretório atual - PERMISSÃO DE ESCRITA OK\n";
} else {
    echo "❌ Diretório atual - SEM PERMISSÃO DE ESCRITA\n";
}

echo "\n=== INSTRUÇÕES ===\n";
echo "Se houver problemas de permissão, execute no terminal:\n";
echo "chmod 666 migration_log.txt\n";
echo "chmod 755 .\n";
?>