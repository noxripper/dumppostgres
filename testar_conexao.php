<?php
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $response = [];
    
    try {
        if ($action == 'test_source') {
            // Testar conexão com banco de origem
            $result = testConnection(
                DB_SOURCE_HOST, 
                DB_SOURCE_NAME, 
                DB_SOURCE_USER, 
                DB_SOURCE_PASS, 
                DB_SOURCE_PORT
            );
            
            $response = $result;
            
        } elseif ($action == 'test_target') {
            // Testar conexão com banco de destino
            $result = testConnection(
                DB_TARGET_HOST, 
                DB_TARGET_NAME, 
                DB_TARGET_USER, 
                DB_TARGET_PASS, 
                DB_TARGET_PORT
            );
            
            $response = $result;
            
        } else {
            $response = [
                'success' => false,
                'error' => 'Ação inválida'
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>