<?php
require_once 'config.php';

// Permitir acesso apenas para usuários logados
checkAuth();

header('Content-Type: application/json');

try {
    $tables = getConfiguredTables();
    
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'count' => count($tables)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'tables' => []
    ]);
}
?>
