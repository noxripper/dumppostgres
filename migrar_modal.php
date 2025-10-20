<?php
require_once 'config.php';
checkAuth();

// Inicializar sistema de log
$log_file = initLogSystem();

// Função de log melhorada
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // Também salvar no arquivo
    safeLogWrite($message, $log_file);
    
    flush();
    ob_flush();
}

// Limpar log anterior
safeLogWrite("=== NOVA MIGRAÇÃO INICIADA ===", $log_file);
safeLogWrite("Usuário: " . $_SESSION['username'], $log_file);
safeLogWrite("Data/Hora: " . date('d/m/Y H:i:s'), $log_file);

log_message("=== INICIANDO PROCESSO DE MIGRAÇÃO ===");
log_message("Usuário: " . $_SESSION['username'] . " (" . $_SESSION['user_level'] . ")");
log_message("Sistema: " . PHP_OS);
log_message("Diretório: " . __DIR__);

// Verificar se temos POST data
if ($_POST) {
    log_message("📝 Dados recebidos: " . print_r($_POST, true));
} else {
    log_message("⚠️ Nenhum dado POST recebido");
}

try {
    // Verificação rápida das conexões antes de iniciar
    log_message("🔍 Verificando conexões...");
    
    $source_test = testConnection(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
    log_message("✅ Conexão com origem: OK - " . $source_test['version']);
    
    $target_test = testConnection(DB_TARGET_HOST, DB_TARGET_NAME, DB_TARGET_USER, DB_TARGET_PASS, DB_TARGET_PORT);
    log_message("✅ Conexão com destino: OK - " . $target_test['version']);
    
} catch (Exception $e) {
    log_message("❌ Falha na verificação de conexões: " . $e->getMessage());
    log_message("=== MIGRAÇÃO CANCELADA ===");
    
    echo "MIGRATION_FAILED: " . $e->getMessage();
    exit;
}

try {
    // Conectar aos bancos
    log_message("🔌 Conectando ao banco de origem...");
    $source_pdo = connectDB(DB_SOURCE_HOST, DB_SOURCE_NAME, DB_SOURCE_USER, DB_SOURCE_PASS, DB_SOURCE_PORT);
    log_message("✅ Conexão com origem estabelecida");

    log_message("🔌 Conectando ao banco de destino...");
    $target_pdo = connectDB(DB_TARGET_HOST, DB_TARGET_NAME, DB_TARGET_USER, DB_TARGET_PASS, DB_TARGET_PORT);
    log_message("✅ Conexão com destino estabelecida");

    // Lista de tabelas para migrar
    $tables = getConfiguredTables();
    
    if (empty($tables)) {
        log_message("❌ Nenhuma tabela configurada para migração");
        log_message("=== MIGRAÇÃO CANCELADA ===");
        echo "MIGRATION_FAILED: Nenhuma tabela configurada";
        exit;
    }
    
    log_message("📋 Tabelas selecionadas: " . implode(', ', $tables));
    
    // Verificar modo de operação
    $truncate_mode = isset($_POST['truncate']) && $_POST['truncate'] == '1';
    if ($truncate_mode) {
        log_message("🗑️ MODO: ESVAZIAMENTO DE TABELAS ATIVADO");
    } else {
        log_message("💾 MODO: MIGRAÇÃO COM DADOS EXISTENTES");
    }
    
    // Iniciar transação no destino
    $target_pdo->beginTransaction();
    log_message("🔄 Transação iniciada no banco de destino");

    $total_tables = count($tables);
    $processed_tables = 0;
    $success_tables = 0;
    $total_records = 0;

    foreach ($tables as $table) {
        $processed_tables++;
        log_message("\n--- Processando tabela {$processed_tables}/{$total_tables}: $table ---");
        
        // Verificar se tabela existe na origem
        try {
            $check_table = $source_pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
            $table_exists_origin = $check_table->fetchColumn();
        } catch (Exception $e) {
            log_message("❌ Erro ao verificar tabela na origem: " . $e->getMessage());
            continue;
        }
        
        if (!$table_exists_origin) {
            log_message("⚠️ Tabela $table não existe na origem");
            continue;
        }
        
        log_message("✅ Tabela encontrada na origem");

        // Verificar se tabela existe no destino
        try {
            $check_table_dest = $target_pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
            $table_exists_dest = $check_table_dest->fetchColumn();
        } catch (Exception $e) {
            log_message("❌ Erro ao verificar tabela no destino: " . $e->getMessage());
            continue;
        }

        if (!$table_exists_dest) {
            log_message("🏗️ Criando tabela $table no destino...");
            if (!createTableStructure($source_pdo, $target_pdo, $table)) {
                log_message("❌ Falha ao criar tabela $table");
                continue;
            }
        } else {
            log_message("✅ Tabela $table já existe no destino");
        }

        // Esvaziar tabela de destino se solicitado
        if ($truncate_mode) {
            log_message("🗑️ Esvaziando tabela...");
            try {
                $target_pdo->exec("TRUNCATE TABLE \"$table\"");
                log_message("✅ Tabela $table esvaziada");
            } catch (Exception $e) {
                log_message("⚠️ Não foi possível esvaziar tabela: " . $e->getMessage());
            }
        }

        // Obter dados da tabela de origem
        log_message("📥 Extraindo dados...");
        try {
            $stmt = $source_pdo->query("SELECT * FROM \"$table\"");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $row_count = count($rows);
        } catch (Exception $e) {
            log_message("❌ Erro ao extrair dados: " . $e->getMessage());
            continue;
        }
        
        log_message("📊 $row_count registros encontrados");

        if ($row_count > 0) {
            // Obter colunas
            $columns = array_keys($rows[0]);
            $columns_str = '"' . implode('", "', $columns) . '"';
            $placeholders = ':' . implode(', :', $columns);
            
            // Preparar INSERT para destino
            $insert_sql = "INSERT INTO \"$table\" ($columns_str) VALUES ($placeholders)";
            
            try {
                $insert_stmt = $target_pdo->prepare($insert_sql);
            } catch (Exception $e) {
                log_message("❌ Erro ao preparar INSERT: " . $e->getMessage());
                continue;
            }
            
            $inserted = 0;
            $errors = 0;
            
            foreach ($rows as $index => $row) {
                try {
                    $insert_stmt->execute($row);
                    $inserted++;
                    
                    // Log a cada 100 registros
                    if ($inserted % 100 === 0) {
                        log_message("   📦 Progresso: $inserted/$row_count");
                    }
                } catch (Exception $e) {
                    $errors++;
                    if ($errors <= 2) {
                        log_message("   ⚠️ Erro no registro $index: " . $e->getMessage());
                    }
                }
            }
            
            $total_records += $inserted;
            log_message("✅ $inserted registros inseridos em $table");
            
            if ($errors > 0) {
                log_message("⚠️  $errors erros em $table");
            }
            
            $success_tables++;
        } else {
            log_message("ℹ Nenhum dado para migrar em $table");
            $success_tables++;
        }
    }

    // Commit da transação
    $target_pdo->commit();
    log_message("\n🎉 MIGRAÇÃO CONCLUÍDA!");
    log_message("📊 RESUMO FINAL:");
    log_message("   • Tabelas processadas: $success_tables/$total_tables");
    log_message("   • Registros migrados: $total_records");
    log_message("   • Modo: " . ($truncate_mode ? "Esvaziar e migrar" : "Migrar com dados existentes"));
    log_message("=== MIGRAÇÃO FINALIZADA ===");
    
    echo "MIGRATION_COMPLETED:$success_tables:$total_tables:$total_records";

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($target_pdo)) {
        try {
            $target_pdo->rollBack();
            log_message("🔄 Transação revertida");
        } catch (Exception $rollback_e) {
            log_message("⚠️ Erro ao reverter transação: " . $rollback_e->getMessage());
        }
    }
    log_message("\n❌ ERRO CRÍTICO: " . $e->getMessage());
    log_message("=== MIGRAÇÃO INTERROMPIDA ===");
    
    echo "MIGRATION_FAILED:" . $e->getMessage();
}

/**
 * Função para criar a estrutura da tabela no destino
 */
function createTableStructure($source_pdo, $target_pdo, $table) {
    try {
        // Obter informações das colunas
        $columns_info = $source_pdo->query("
            SELECT 
                column_name,
                data_type,
                is_nullable,
                column_default,
                character_maximum_length
            FROM information_schema.columns 
            WHERE table_name = '$table' 
            ORDER BY ordinal_position
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($columns_info)) {
            log_message("   ❌ Não foi possível obter colunas da tabela $table");
            return false;
        }
        
        $column_definitions = [];
        foreach ($columns_info as $col) {
            $definition = "\"{$col['column_name']}\" {$col['data_type']}";
            
            // Adicionar tamanho para tipos character
            if ($col['character_maximum_length']) {
                $definition .= "({$col['character_maximum_length']})";
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
        
        $create_sql = "CREATE TABLE \"$table\" (\n    " . implode(",\n    ", $column_definitions) . "\n)";
        
        // Executar CREATE TABLE
        $target_pdo->exec($create_sql);
        log_message("   ✅ Tabela $table criada com sucesso");
        return true;
        
    } catch (Exception $e) {
        log_message("   ❌ Erro ao criar tabela $table: " . $e->getMessage());
        return false;
    }
}
?>