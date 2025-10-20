<?php
require_once 'config.php';
checkAdminAuth();

header('Content-Type: application/json');

try {
    $tables = getSourceTables();
    
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