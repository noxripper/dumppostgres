<?php
require_once 'config.php';
checkAuth();

// Iniciar buffer para capturar a saída
ob_start();
$log_file = 'migration_log.txt';

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    flush();
    ob_flush();
}

// Limpar log anterior
if (file_exists($log_file)) {
    file_put_contents($log_file, '');
}

log_message("=== INICIANDO PROCESSO DE MIGRAÇÃO ===");
log_message("Usuário: " . $_SESSION['username'] . " (" . $_SESSION['user_level'] . ")");
log_message("Data/Hora: " . date('d/m/Y H:i:s'));

try {
    // Verificação rápida das conexões antes de iniciar
    log_message("Verificando conexões...");
    $source_test = testConnection(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
    log_message("✓ Conexão com origem: OK");
    
    $target_test = testConnection(DB_TARGET_HOST, DB_TARGET_NAME, DB_TARGET_USER, DB_TARGET_PASS, DB_TARGET_PORT);
    log_message("✓ Conexão com destino: OK");
    
} catch (Exception $e) {
    log_message("✗ Falha na verificação de conexões: " . $e->getMessage());
    log_message("=== MIGRAÇÃO CANCELADA ===");
    
    // Salvar log mesmo em caso de erro
    $log_content = ob_get_clean();
    if (file_put_contents($log_file, $log_content) === false) {
        error_log("Erro ao salvar arquivo de log");
    }
    exit;
}

try {
    // Conectar aos bancos
    log_message("Conectando ao banco de origem...");
    $source_pdo = connectDB(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
    log_message("✓ Conexão com origem estabelecida");

    log_message("Conectando ao banco de destino...");
    $target_pdo = connectDB(DB_TARGET_HOST, DB_TARGET_NAME, DB_TARGET_USER, DB_TARGET_PASS, DB_TARGET_PORT);
    log_message("✓ Conexão com destino estabelecida");

    // Lista de tabelas para migrar
    $tables = getConfiguredTables();
    
    if (empty($tables)) {
        log_message("❌ Nenhuma tabela configurada para migração");
        log_message("=== MIGRAÇÃO CANCELADA ===");
        $log_content = ob_get_clean();
        file_put_contents($log_file, $log_content);
        exit;
    }
    
    log_message("📋 Tabelas selecionadas para migração: " . implode(', ', $tables));
    
    // Verificar modo de operação
    $truncate_mode = isset($_POST['truncate']) && $_POST['truncate'] == '1';
    if ($truncate_mode) {
        log_message("🗑️ MODO: ESVAZIAMENTO DE TABELAS ATIVADO");
    } else {
        log_message("💾 MODO: MIGRAÇÃO COM DADOS EXISTENTES");
    }
    
    // Iniciar transação no destino
    $target_pdo->beginTransaction();
    log_message("Transação iniciada no banco de destino");

    $total_tables = count($tables);
    $processed_tables = 0;
    $total_records = 0;

    foreach ($tables as $table) {
        $processed_tables++;
        log_message("\n--- Processando tabela {$processed_tables}/{$total_tables}: $table ---");
        
        // Verificar se tabela existe na origem
        $check_table = $source_pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
        $table_exists_origin = $check_table->fetchColumn();
        
        if (!$table_exists_origin) {
            log_message("✗ Tabela $table não existe na origem");
            continue;
        }
        
        log_message("✓ Tabela encontrada na origem");

        // Verificar se tabela existe no destino
        $check_table_dest = $target_pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
        $table_exists_dest = $check_table_dest->fetchColumn();

        if (!$table_exists_dest) {
            log_message("📋 Tabela $table não existe no destino - criando estrutura...");
            createTableStructure($source_pdo, $target_pdo, $table);
        } else {
            log_message("✓ Tabela $table existe no destino");
        }

        // Esvaziar tabela de destino se solicitado
        if ($truncate_mode) {
            log_message("🗑️ Esvaziando tabela de destino...");
            try {
                $target_pdo->exec("TRUNCATE TABLE \"$table\" CASCADE");
                log_message("✅ Tabela $table esvaziada");
            } catch (Exception $e) {
                log_message("⚠️ Tabela $table não pôde ser esvaziada: " . $e->getMessage());
            }
        }

        // Obter dados da tabela de origem
        log_message("📥 Extraindo dados da origem...");
        $stmt = $source_pdo->query("SELECT * FROM \"$table\"");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row_count = count($rows);
        
        log_message("✅ $row_count registros encontrados");
        $total_records += $row_count;

        if ($row_count > 0) {
            // Obter colunas
            $columns = array_keys($rows[0]);
            $columns_str = '"' . implode('", "', $columns) . '"';
            $placeholders = ':' . implode(', :', $columns);
            
            // Preparar INSERT para destino
            $insert_sql = "INSERT INTO \"$table\" ($columns_str) VALUES ($placeholders)";
            $insert_stmt = $target_pdo->prepare($insert_sql);
            
            $inserted = 0;
            $errors = 0;
            
            foreach ($rows as $row) {
                try {
                    $insert_stmt->execute($row);
                    $inserted++;
                    
                    // Log a cada 100 registros para acompanhar o progresso
                    if ($inserted % 100 === 0) {
                        log_message("   📦 $inserted/$row_count registros inseridos...");
                    }
                } catch (Exception $e) {
                    $errors++;
                    if ($errors <= 3) { // Log apenas os primeiros 3 erros
                        log_message("   ⚠️ Erro ao inserir registro: " . $e->getMessage());
                    }
                }
            }
            
            log_message("✅ $inserted registros inseridos na tabela $table");
            if ($errors > 0) {
                log_message("⚠️  $errors registros com erro na tabela $table");
            }
        } else {
            log_message("ℹ Nenhum dado para migrar na tabela $table");
        }
        
        log_message("✅ Tabela $table processada com sucesso");
    }

    // Commit da transação
    $target_pdo->commit();
    log_message("\n🎉 MIGRAÇÃO CONCLUÍDA COM SUCESSO!");
    log_message("📊 RESUMO:");
    log_message("   • Tabelas processadas: $processed_tables/$total_tables");
    log_message("   • Total de registros migrados: $total_records");
    log_message("   • Modo: " . ($truncate_mode ? "Esvaziar e migrar" : "Migrar com dados existentes"));
    log_message("=== MIGRAÇÃO FINALIZADA ===");

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($target_pdo)) {
        try {
            $target_pdo->rollBack();
            log_message("🔄 Transação revertida devido a erro");
        } catch (Exception $rollback_e) {
            log_message("⚠️ Erro ao reverter transação: " . $rollback_e->getMessage());
        }
    }
    log_message("\n❌ ERRO NA MIGRAÇÃO: " . $e->getMessage());
    log_message("=== MIGRAÇÃO INTERROMPIDA ===");
}

// Salvar log
$log_content = ob_get_clean();

// Garantir que o diretório tem permissão de escrita
if (file_put_contents($log_file, $log_content) === false) {
    // Se não conseguir salvar, tentar criar o arquivo com permissões diferentes
    $log_file = 'migration_log_' . date('Y-m-d_His') . '.txt';
    file_put_contents($log_file, $log_content);
    log_message("⚠️ Log salvo em: $log_file");
}

// Retornar sucesso para o JavaScript
echo "MIGRATION_COMPLETED";

/**
 * Função para criar a estrutura da tabela no destino
 */
function createTableStructure($source_pdo, $target_pdo, $table) {
    try {
        // Obter estrutura da tabela de origem
        $create_table_sql = getTableCreateStatement($source_pdo, $table);
        
        if ($create_table_sql) {
            // Executar CREATE TABLE no destino
            $target_pdo->exec($create_table_sql);
            log_message("   ✅ Estrutura da tabela $table criada com sucesso");
            
            // Criar índices se existirem
            createTableIndexes($source_pdo, $target_pdo, $table);
            
        } else {
            log_message("   ⚠️ Não foi possível obter a estrutura da tabela $table");
            // Criar tabela básica como fallback
            createBasicTable($source_pdo, $target_pdo, $table);
        }
        
    } catch (Exception $e) {
        log_message("   ❌ Erro ao criar estrutura da tabela $table: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obter statement CREATE TABLE
 */
function getTableCreateStatement($pdo, $table) {
    try {
        // Tentar obter o CREATE TABLE do PostgreSQL
        $stmt = $pdo->query("SELECT pg_get_viewdef('$table'::regclass, true) as create_sql");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['create_sql'])) {
            return $result['create_sql'];
        }
        
        // Fallback: criar baseado na estrutura das colunas
        return generateCreateTableFromColumns($pdo, $table);
        
    } catch (Exception $e) {
        log_message("   ⚠️ Não foi possível obter CREATE TABLE: " . $e->getMessage());
        return generateCreateTableFromColumns($pdo, $table);
    }
}

/**
 * Gerar CREATE TABLE baseado nas colunas
 */
function generateCreateTableFromColumns($pdo, $table) {
    $columns_info = $pdo->query("
        SELECT 
            column_name,
            data_type,
            is_nullable,
            column_default,
            character_maximum_length,
            numeric_precision,
            numeric_scale
        FROM information_schema.columns 
        WHERE table_name = '$table' 
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns_info)) {
        return null;
    }
    
    $column_definitions = [];
    foreach ($columns_info as $col) {
        $definition = "\"{$col['column_name']}\" {$col['data_type']}";
        
        // Adicionar tamanho para tipos character
        if ($col['character_maximum_length']) {
            $definition .= "({$col['character_maximum_length']})";
        }
        
        // Adicionar precisão para tipos numéricos
        if ($col['numeric_precision']) {
            if ($col['numeric_scale']) {
                $definition .= "({$col['numeric_precision']},{$col['numeric_scale']})";
            } else {
                $definition .= "({$col['numeric_precision']})";
            }
        }
        
        // Adicionar NOT NULL
        if ($col['is_nullable'] === 'NO') {
            $definition .= " NOT NULL";
        }
        
        // Adicionar valor padrão
        if ($col['column_default']) {
            $definition .= " DEFAULT {$col['column_default']}";
        }
        
        $column_definitions[] = $definition;
    }
    
    return "CREATE TABLE \"$table\" (\n    " . implode(",\n    ", $column_definitions) . "\n)";
}

/**
 * Criar índices da tabela
 */
function createTableIndexes($source_pdo, $target_pdo, $table) {
    try {
        $indexes = $source_pdo->query("
            SELECT 
                indexname,
                indexdef
            FROM pg_indexes 
            WHERE tablename = '$table' 
            AND indexname NOT LIKE '%pkey'
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($indexes as $index) {
            try {
                $target_pdo->exec($index['indexdef']);
                log_message("   ✅ Índice {$index['indexname']} criado");
            } catch (Exception $e) {
                log_message("   ⚠️ Erro ao criar índice {$index['indexname']}: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        log_message("   ⚠️ Erro ao obter índices: " . $e->getMessage());
    }
}

/**
 * Criar tabela básica como fallback
 */
function createBasicTable($source_pdo, $target_pdo, $table) {
    try {
        // Obter apenas as colunas básicas
        $columns = $source_pdo->query("SELECT * FROM \"$table\" LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if (!$columns) {
            log_message("   ⚠️ Não foi possível obter estrutura da tabela $table");
            return;
        }
        
        $column_definitions = [];
        foreach (array_keys($columns) as $col_name) {
            $column_definitions[] = "\"$col_name\" TEXT";
        }
        
        $create_sql = "CREATE TABLE \"$table\" (\n    " . implode(",\n    ", $column_definitions) . "\n)";
        $target_pdo->exec($create_sql);
        log_message("   ✅ Tabela básica $table criada como fallback");
        
    } catch (Exception $e) {
        log_message("   ❌ Erro ao criar tabela básica: " . $e->getMessage());
        throw $e;
    }
}
?>