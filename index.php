<?php
require_once 'config.php';
checkAuth();

$user_level = getUserLevel();
$is_admin = isAdmin();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Migração de Dados</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Sistema de Migração de Dados</h1>
            <div class="user-info">
                <div class="user-badge <?php echo $user_level; ?>">
                    👤 <?php echo $_SESSION['username']; ?> 
                    <span class="user-level">(<?php echo $user_level === 'admin' ? 'Administrador' : 'Operador'; ?>)</span>
                </div>
                <div class="header-links">
                    <?php if ($is_admin): ?>
                        <a href="configuracoes.php" class="btn-header">⚙️ Configurações</a>
                    <?php endif; ?>
                    <a href="logout.php" class="logout">Sair</a>
                </div>
            </div>
        </header>

        <?php if (!$is_admin): ?>
            <div class="info-message">
                <strong>⚠️ Modo Operador</strong>
                <p>Você está logado como <strong>Operador</strong>. Acesso limitado à execução de migrações.</p>
            </div>
        <?php endif; ?>

        <div class="info-panel">
            <h2>📊 Informações dos Bancos</h2>
            <div class="db-info">
                <div class="db-source">
                    <h3>🔄 Banco de Origem</h3>
                    <p><strong>Servidor:</strong> <?php echo DB_SOURCE_HOST; ?></p>
                    <p><strong>Database:</strong> <?php echo DB_SOURCE_NAME; ?></p>
                    <p><strong>Usuário:</strong> <?php echo DB_SOURCE_USER; ?></p>
                    <?php if ($is_admin): ?>
                        <button type="button" class="btn-test" onclick="testConnection('source')">
                            🔍 Testar Conexão
                        </button>
                    <?php endif; ?>
                    <div id="source-result" class="test-result"></div>
                </div>
                <div class="db-target">
                    <h3>🎯 Banco de Destino</h3>
                    <p><strong>Servidor:</strong> <?php echo DB_TARGET_HOST; ?></p>
                    <p><strong>Database:</strong> <?php echo DB_TARGET_NAME; ?></p>
                    <p><strong>Usuário:</strong> <?php echo DB_TARGET_USER; ?></p>
                    <div class="migration-info">
                        <small>✨ As tabelas serão criadas automaticamente se não existirem no destino</small>
                    </div>
                    <?php if ($is_admin): ?>
                        <button type="button" class="btn-test" onclick="testConnection('target')">
                            🔍 Testar Conexão
                        </button>
                    <?php endif; ?>
                    <div id="target-result" class="test-result"></div>
                </div>
            </div>
        </div>

        <div class="migration-panel">
            <h2>🚀 Migração de Tabelas</h2>
           <div class="tables-list">
    <strong>Tabelas selecionadas para migração:</strong>
    <div class="table-badges" id="tablesBadges">
        <?php
        $tables = getConfiguredTables();
        if (empty($tables)) {
            echo '<span style="color: #e53e3e; font-style: italic;">Nenhuma tabela configurada</span>';
        } else {
            foreach ($tables as $table): 
        ?>
            <span class="table-badge"><?php echo $table; ?></span>
        <?php 
            endforeach;
        }
        ?>
    </div>
    <?php if (empty($tables)): ?>
        <div style="margin-top: 10px; color: #e53e3e; font-size: 12px;">
            ⚠️ Configure as tabelas na página de configurações
        </div>
    <?php endif; ?>
</div>
            
            <form id="migrationForm">
                <div class="option-group">
                    <div class="checkbox-container">
                        <input type="checkbox" id="truncate" name="truncate" value="1">
                        <label for="truncate" class="checkbox-label">
                            <span class="checkmark"></span>
                            <span class="label-text">
                                <strong>🗑️ Esvaziar tabelas antes da migração</strong>
                                <small>Remove todos os dados existentes nas tabelas de destino antes de inserir os novos dados. <span style="color: #e53e3e; font-weight: bold;">ATENÇÃO: Esta operação não pode ser desfeita!</span></small>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-migrate" id="migrateButton" <?php echo empty($tables) ? 'disabled' : ''; ?> onclick="startMigration()">
                        <span class="btn-icon">🚀</span>
                        <?php echo empty($tables) ? 'Configure as Tabelas Primeiro' : 'Iniciar Migração'; ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['log'])): ?>
            <div class="log-panel">
                <h3>📝 Log da Última Migração</h3>
                <div class="log-content">
                    <pre><?php 
                    if (file_exists('migration_log.txt')) {
                        $log_content = file_get_contents('migration_log.txt');
                        echo htmlspecialchars($log_content);
                    } else {
                        echo "Nenhum log disponível.";
                    }
                    ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <!-- Modal Terminal -->
        <div id="terminalModal" class="terminal-modal">
            <div class="terminal-content">
                <div class="terminal-header">
                    <h3>🖥️ Terminal de Migração</h3>
                    <button class="terminal-close" onclick="closeTerminal()">✕</button>
                </div>
                <div class="terminal-body">
                    <div class="terminal-output" id="terminalOutput">
                        <div class="terminal-line">🔧 Inicializando terminal de migração...</div>
                        <div class="terminal-line">💾 Pronto para iniciar o processo</div>
                    </div>
                </div>
                <div class="terminal-footer">
                    <div class="terminal-status" id="terminalStatus">Status: Aguardando início...</div>
                    <button class="btn-terminal-close" onclick="closeTerminal()">Fechar Terminal</button>
                </div>
            </div>
        </div>

        <footer>
            <div class="footer-content">
                <div class="deepseek-logo">
                    <strong>Sistema produzido por DeepSeek</strong>
                    <div class="logo">🦉</div>
                </div>
                <div class="footer-info">
                    <p>Migração de Dados PostgreSQL - Versão 2.0</p>
                    <p>Controle de Acesso Multi-nível</p>
                </div>
            </div>
        </footer>
    </div>

    <?php if ($is_admin): ?>
    <script>
    function testConnection(type) {
        const button = event.target;
        const resultDiv = document.getElementById(type + '-result');
        const originalText = button.textContent;
        
        button.textContent = '⏳ Testando...';
        button.disabled = true;
        resultDiv.innerHTML = '<div class="testing">🔍 Testando conexão, aguarde...</div>';
        
        const formData = new FormData();
        formData.append('action', 'test_' + type);
        
        fetch('testar_conexao.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let tablesInfo = '';
                if (data.all_tables && data.all_tables.length > 0) {
                    tablesInfo = `<br><strong>📊 Total de tabelas:</strong> ${data.tables_count}`;
                }
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <strong>✅ Conexão bem-sucedida!</strong><br>
                        <strong>🔧 Versão PostgreSQL:</strong> ${data.version}${tablesInfo}
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="error">
                        <strong>❌ Falha na conexão:</strong><br>
                        ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="error">
                    <strong>❌ Erro no teste:</strong><br>
                    ${error}
                </div>
            `;
        })
        .finally(() => {
            button.textContent = originalText;
            button.disabled = false;
        });
    }
    </script>
    <?php endif; ?>

    <script>
    // Função para iniciar a migração
    function startMigration() {
        const truncate = document.getElementById('truncate').checked;
        
        let message = '📊 INICIAR MIGRAÇÃO DE DADOS\n\n' +
                     'Os dados serão migrados mantendo os dados existentes nas tabelas de destino.\n\n' +
                     '📋 Tabelas que serão migradas:\n' +
                     '<?php echo implode(", ", getConfiguredTables()); ?>\n\n' +
                     '✨ As tabelas serão criadas automaticamente se não existirem\n\n' +
                     'Deseja continuar?';
        
        if (truncate) {
            message = '⚠️ ATENÇÃO: ESVAZIAMENTO DE TABELAS SELECIONADO!\n\n' +
                     '🚨 TODOS os dados existentes nas tabelas de destino serão PERMANENTEMENTE REMOVIDOS!\n\n' +
                     '📋 Tabelas que serão esvaziadas:\n' +
                     '<?php echo implode(", ", getConfiguredTables()); ?>\n\n' +
                     '✅ Os novos dados serão migrados após o esvaziamento.\n\n' +
                     '🔒 Esta operação NÃO PODE ser desfeita!\n\n' +
                     'Deseja realmente continuar?';
        }
        
        if (confirm(message)) {
            openTerminal();
            executeMigration(truncate);
        }
    }

    // Função para abrir o terminal
    function openTerminal() {
        document.getElementById('terminalModal').style.display = 'flex';
        document.getElementById('terminalOutput').innerHTML = 
            '<div class="terminal-line">🚀 Iniciando processo de migração...</div>' +
            '<div class="terminal-line">⏳ Por favor, aguarde...</div>';
        document.getElementById('terminalStatus').textContent = 'Status: Executando migração...';
        
        // Focar no modal
        document.getElementById('terminalModal').focus();
    }

    // Função para fechar o terminal
    function closeTerminal() {
        document.getElementById('terminalModal').style.display = 'none';
        // Recarregar a página para atualizar o estado
        setTimeout(() => {
            window.location.href = 'index.php?log=1';
        }, 500);
    }

// Função para executar a migração
function executeMigration(truncate) {
    const formData = new FormData();
    if (truncate) {
        formData.append('truncate', '1');
    }
    
    const output = document.getElementById('terminalOutput');
    const status = document.getElementById('terminalStatus');
    
    // Limpar output anterior
    output.innerHTML = '<div class="terminal-line">🚀 Iniciando migração no Windows...</div>';
    output.innerHTML += '<div class="terminal-line">🔧 Verificando configurações...</div>';
    
    // Adicionar cursor
    const cursor = document.createElement('div');
    cursor.className = 'terminal-line terminal-cursor';
    cursor.textContent = '▊';
    output.appendChild(cursor);
    
    fetch('migrar_modal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        return response.text();
    })
    .then(data => {
        // Remover cursor
        cursor.remove();
        
        if (data.startsWith('MIGRATION_COMPLETED')) {
            const parts = data.split(':');
            const successTables = parts[1] || '0';
            const totalTables = parts[2] || '0';
            const totalRecords = parts[3] || '0';
            
            output.innerHTML += `<div class="terminal-line terminal-success">✅ Migração concluída com sucesso!</div>`;
            output.innerHTML += `<div class="terminal-line">📊 Tabelas processadas: ${successTables}/${totalTables}</div>`;
            output.innerHTML += `<div class="terminal-line">📦 Registros migrados: ${totalRecords}</div>`;
            
            status.textContent = 'Status: Concluído com sucesso';
            status.className = 'terminal-status status-success';
            
        } else if (data.startsWith('MIGRATION_FAILED')) {
            const errorMsg = data.split(':')[1] || 'Erro desconhecido';
            output.innerHTML += `<div class="terminal-line terminal-error">❌ Falha na migração: ${errorMsg}</div>`;
            status.textContent = 'Status: Falha na migração';
            status.className = 'terminal-status status-error';
        } else {
            output.innerHTML += `<div class="terminal-line terminal-warning">⚠️ Resposta inesperada do servidor</div>`;
            output.innerHTML += `<div class="terminal-line">${data}</div>`;
            status.textContent = 'Status: Resposta inesperada';
            status.className = 'terminal-status status-error';
        }
        
        // Adicionar botão para ver log
        const logButton = document.createElement('button');
        logButton.className = 'btn-log-view';
        logButton.textContent = '📋 Ver Log de Execução';
        logButton.onclick = () => {
            // Forçar recarregamento
            const timestamp = new Date().getTime();
            window.open(`migration_log.txt?t=${timestamp}`, '_blank');
        };
        output.appendChild(logButton);
        
        // Scroll para o final
        output.scrollTop = output.scrollHeight;
    })
    .catch(error => {
        // Remover cursor
        cursor.remove();
        
        output.innerHTML += `<div class="terminal-line terminal-error">❌ Erro de comunicação: ${error.message}</div>`;
        output.innerHTML += `<div class="terminal-line terminal-warning">🔧 Verifique: 
            - Servidor web está rodando
            - Arquivos PHP estão acessíveis
            - Permissões de escrita</div>`;
        
        status.textContent = 'Status: Erro de comunicação';
        status.className = 'terminal-status status-error';
        
        // Scroll para o final
        output.scrollTop = output.scrollHeight;
    });
    
    // Simular mensagens de progresso
    setTimeout(() => {
        if (output.querySelector('.terminal-cursor')) {
            output.innerHTML += '<div class="terminal-line">🔌 Conectando aos bancos de dados...</div>';
            output.scrollTop = output.scrollHeight;
        }
    }, 1000);
    
    setTimeout(() => {
        if (output.querySelector('.terminal-cursor')) {
            output.innerHTML += '<div class="terminal-line">📋 Obtendo lista de tabelas...</div>';
            output.scrollTop = output.scrollHeight;
        }
    }, 2000);
}

    // Simular output de carregamento
    function simulateLoadingOutput() {
        const output = document.getElementById('terminalOutput');
        const loadingMessages = [
            '🔧 Verificando conexões com os bancos...',
            '📋 Obtendo lista de tabelas...',
            '🔄 Iniciando processo de migração...',
            '⏳ Processando tabelas...'
        ];
        
        let index = 0;
        const interval = setInterval(() => {
            if (index < loadingMessages.length) {
                const line = document.createElement('div');
                line.className = 'terminal-line';
                line.textContent = loadingMessages[index];
                output.appendChild(line);
                output.scrollTop = output.scrollHeight;
                index++;
            } else {
                clearInterval(interval);
            }
        }, 800);
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTerminal();
        }
    });

    // Fechar modal clicando fora
    document.getElementById('terminalModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTerminal();
        }
    });
    </script>
</body>
</html>