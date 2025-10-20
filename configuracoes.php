<?php
require_once 'config.php';
checkAdminAuth();

$message = '';
$message_type = '';

// Processar formulário de configuração
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'save_config') {
        // Processar usuários
        $users = [];
        
        // Usuário admin
        if (!empty($_POST['admin_username']) && !empty($_POST['admin_password'])) {
            $users[$_POST['admin_username']] = [
                'password' => $_POST['admin_password'],
                'level' => 'admin'
            ];
        }
        
        // Usuário operador
        if (!empty($_POST['operador_username']) && !empty($_POST['operador_password'])) {
            $users[$_POST['operador_username']] = [
                'password' => $_POST['operador_password'],
                'level' => 'operador'
            ];
        }
        
        $new_config = [
            'source' => [
                'host' => $_POST['source_host'],
                'database' => $_POST['source_database'],
                'username' => $_POST['source_username'],
                'password' => $_POST['source_password'],
                'port' => $_POST['source_port']
            ],
            'target' => [
                'host' => $_POST['target_host'],
                'database' => $_POST['target_database'],
                'username' => $_POST['target_username'],
                'password' => $_POST['target_password'],
                'port' => $_POST['target_port']
            ],
            'tables' => isset($_POST['tables']) ? array_filter(array_map('trim', explode(',', $_POST['tables']))) : [],
            'app' => [
                'users' => $users
            ]
        ];
        
        if (saveConfig($new_config)) {
            $message = 'Configurações salvas com sucesso!';
            $message_type = 'success';
            
            // Recarregar a configuração
            $config = json_decode(file_get_contents('app_config.json'), true);
        } else {
            $message = 'Erro ao salvar configurações!';
            $message_type = 'error';
        }
    }
    
    if ($action == 'test_connection') {
        $type = $_POST['connection_type'];
        $host = $_POST[$type . '_host'];
        $database = $_POST[$type . '_database'];
        $username = $_POST[$type . '_username'];
        $password = $_POST[$type . '_password'];
        $port = $_POST[$type . '_port'];
        
        $result = testConnection($host, $database, $username, $password, $port);
        
        if ($result['success']) {
            $message = "✅ Conexão $type bem-sucedida! PostgreSQL " . $result['version'];
            $message_type = 'success';
        } else {
            $message = "❌ Falha na conexão $type: " . $result['error'];
            $message_type = 'error';
        }
    }
}

// Carregar configuração atual
$config_file = 'app_config.json';
$config = json_decode(file_get_contents($config_file), true);

// Encontrar usuários admin e operador
$admin_user = null;
$operador_user = null;

foreach ($config['app']['users'] as $username => $user_data) {
    if ($user_data['level'] === 'admin') {
        $admin_user = ['username' => $username, 'password' => $user_data['password']];
    } elseif ($user_data['level'] === 'operador') {
        $operador_user = ['username' => $username, 'password' => $user_data['password']];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Configurações - Migração de Dados</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ Configurações do Sistema</h1>
            <div class="user-info">
                <div class="user-badge admin">
                    👤 <?php echo $_SESSION['username']; ?> 
                    <span class="user-level">(Administrador)</span>
                </div>
                <div class="header-links">
                    <a href="index.php" class="btn-header">📊 Dashboard</a>
                    <a href="logout.php" class="logout">Sair</a>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="config-panel">
            <form method="POST" id="configForm">
                <input type="hidden" name="action" value="save_config">
                
                <div class="config-sections">
                    <!-- Configurações do Banco de Origem -->
                    <div class="config-section">
                        <h3>🔄 Banco de Origem</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Servidor (IP/Host):</label>
                                <input type="text" name="source_host" value="<?php echo htmlspecialchars($config['source']['host']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Database:</label>
                                <input type="text" name="source_database" value="<?php echo htmlspecialchars($config['source']['database']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Usuário:</label>
                                <input type="text" name="source_username" value="<?php echo htmlspecialchars($config['source']['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Senha:</label>
                                <input type="password" name="source_password" value="<?php echo htmlspecialchars($config['source']['password']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Porta:</label>
                                <input type="number" name="source_port" value="<?php echo htmlspecialchars($config['source']['port']); ?>" required>
                            </div>
                        </div>
                        <button type="button" class="btn-test-connection" onclick="testConnection('source')">
                            🔍 Testar Conexão Origem
                        </button>
                    </div>

                    <!-- Configurações do Banco de Destino -->
                    <div class="config-section">
                        <h3>🎯 Banco de Destino</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Servidor (IP/Host):</label>
                                <input type="text" name="target_host" value="<?php echo htmlspecialchars($config['target']['host']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Database:</label>
                                <input type="text" name="target_database" value="<?php echo htmlspecialchars($config['target']['database']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Usuário:</label>
                                <input type="text" name="target_username" value="<?php echo htmlspecialchars($config['target']['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Senha:</label>
                                <input type="password" name="target_password" value="<?php echo htmlspecialchars($config['target']['password']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Porta:</label>
                                <input type="number" name="target_port" value="<?php echo htmlspecialchars($config['target']['port']); ?>" required>
                            </div>
                        </div>
                        <button type="button" class="btn-test-connection" onclick="testConnection('target')">
                            🔍 Testar Conexão Destino
                        </button>
                    </div>

                    <!-- Configurações das Tabelas -->
                    <div class="config-section">
                        <h3>📊 Tabelas para Migração</h3>
                        
                        <div class="tables-controls">
                            <div class="search-box">
                                <input type="text" id="tableSearch" placeholder="🔍 Digite para filtrar tabelas..." 
                                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                            </div>
                            
                            <div class="selection-actions">
                                <button type="button" class="btn-selection" onclick="selectAllTables()">
                                    ✅ Selecionar Todas (Filtradas)
                                </button>
                                <button type="button" class="btn-selection" onclick="deselectAllTables()">
                                    ❌ Limpar Seleção (Filtradas)
                                </button>
                                <button type="button" class="btn-selection" onclick="clearFilter()">
                                    🔄 Limpar Filtro
                                </button>
                            </div>
                        </div>

                        <div class="tables-selection">
                            <div class="tables-stats">
                                <span id="selectedCount">0</span> de <span id="totalCount">0</span> tabelas selecionadas
                            </div>
                            
                            <div class="tables-grid" id="tablesGrid">
                                <div class="loading-tables">⏳ Carregando tabelas do servidor...</div>
                            </div>
                        </div>

                        <!-- Campo hidden para armazenar as tabelas selecionadas -->
                        <input type="hidden" name="tables" id="selectedTables" value="<?php echo htmlspecialchars(implode(',', $config['tables'])); ?>">
                        
                        <!-- Preview das tabelas selecionadas -->
                        <div class="selected-tables-preview">
                            <h4>📋 Tabelas Selecionadas:</h4>
                            <div class="selected-tables-list" id="selectedTablesPreview">
                                <?php
                                if (!empty($config['tables'])) {
                                    foreach ($config['tables'] as $table) {
                                        echo '<span class="selected-table-badge">' . htmlspecialchars($table) . '</span>';
                                    }
                                } else {
                                    echo '<span style="color: #718096; font-style: italic;">Nenhuma tabela selecionada</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações de Usuários -->
                    <div class="config-section">
                        <h3>👥 Usuários do Sistema</h3>
                        <div class="user-config-grid">
                            <div class="user-config admin-config">
                                <h4>👑 Administrador</h4>
                                <div class="form-group">
                                    <label>Usuário:</label>
                                    <input type="text" name="admin_username" value="<?php echo htmlspecialchars($admin_user['username'] ?? 'admin'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Senha:</label>
                                    <input type="password" name="admin_password" value="<?php echo htmlspecialchars($admin_user['password'] ?? 'admin123'); ?>" required>
                                </div>
                                <small>Acesso completo ao sistema</small>
                            </div>
                            
                            <div class="user-config operador-config">
                                <h4>👨‍💻 Operador</h4>
                                <div class="form-group">
                                    <label>Usuário:</label>
                                    <input type="text" name="operador_username" value="<?php echo htmlspecialchars($operador_user['username'] ?? 'operador'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Senha:</label>
                                    <input type="password" name="operador_password" value="<?php echo htmlspecialchars($operador_user['password'] ?? 'operador123'); ?>" required>
                                </div>
                                <small>Acesso somente à migração</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="window.location.href='index.php'">
                        ↩️ Voltar para Dashboard
                    </button>
                    <button type="submit" class="btn-primary">
                        💾 Salvar Configurações
                    </button>
                </div>
            </form>
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

  <script>
let allTables = [];
let selectedTables = <?php echo json_encode($config['tables']); ?>;

// Carregar tabelas do servidor
document.addEventListener('DOMContentLoaded', function() {
    loadTablesFromServer();
    updateSelectedTablesPreview();
    
    // Event listener para o campo de busca - FILTRO DINÂMICO
    document.getElementById('tableSearch').addEventListener('input', function(e) {
        filterTablesDynamic(e.target.value);
    });
    
    // Focar no campo de busca quando a página carregar
    document.getElementById('tableSearch').focus();
});

function loadTablesFromServer() {
    const grid = document.getElementById('tablesGrid');
    grid.innerHTML = '<div class="loading-tables">⏳ Carregando tabelas do servidor...</div>';
    
    fetch('get_tables.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allTables = data.tables;
                updateTablesDisplay();
                updateSelectionCount();
                updateSelectedTablesPreview();
            } else {
                grid.innerHTML = 
                    '<div class="error">❌ Erro ao carregar tabelas: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            grid.innerHTML = 
                '<div class="error">❌ Erro de conexão: ' + error + '</div>';
        });
}

function updateTablesDisplay(filter = '') {
    const grid = document.getElementById('tablesGrid');
    const filteredTables = allTables.filter(table => 
        table.toLowerCase().includes(filter.toLowerCase())
    );

    if (filteredTables.length === 0) {
        if (filter === '') {
            grid.innerHTML = '<div class="no-tables">Nenhuma tabela encontrada no banco de dados</div>';
        } else {
            grid.innerHTML = '<div class="no-tables">Nenhuma tabela encontrada com "' + filter + '"</div>';
        }
        return;
    }

    grid.innerHTML = filteredTables.map(table => {
        const isChecked = selectedTables.includes(table);
        return `
        <div class="table-checkbox-item">
            <input type="checkbox" id="table_${table}" value="${table}" 
                   ${isChecked ? 'checked' : ''} 
                   onchange="toggleTable('${table}')">
            <label for="table_${table}" class="table-checkbox-label">
                <span class="table-checkmark"></span>
                <span class="table-name">${table}</span>
                ${isChecked ? '<span class="table-selected-badge">✓</span>' : ''}
            </label>
        </div>
        `;
    }).join('');
}

// FILTRO DINÂMICO - Atualiza em tempo real
function filterTablesDynamic(searchTerm) {
    updateTablesDisplay(searchTerm);
}

// Função para o botão "Limpar Filtro"
function clearFilter() {
    document.getElementById('tableSearch').value = '';
    updateTablesDisplay('');
}

function toggleTable(tableName) {
    const checkbox = document.getElementById(`table_${tableName}`);
    
    if (checkbox.checked) {
        if (!selectedTables.includes(tableName)) {
            selectedTables.push(tableName);
        }
    } else {
        selectedTables = selectedTables.filter(t => t !== tableName);
    }
    
    updateSelectedTablesField();
    updateSelectionCount();
    updateSelectedTablesPreview();
    
    // Atualizar visualmente o item se estiver visível no filtro atual
    const currentFilter = document.getElementById('tableSearch').value;
    updateTablesDisplay(currentFilter);
}

function updateSelectedTablesField() {
    document.getElementById('selectedTables').value = selectedTables.join(',');
}

function updateSelectionCount() {
    document.getElementById('selectedCount').textContent = selectedTables.length;
    document.getElementById('totalCount').textContent = allTables.length;
}

function updateSelectedTablesPreview() {
    const preview = document.getElementById('selectedTablesPreview');
    
    if (selectedTables.length === 0) {
        preview.innerHTML = '<span style="color: #718096; font-style: italic;">Nenhuma tabela selecionada</span>';
        return;
    }
    
    preview.innerHTML = selectedTables.map(table => 
        `<span class="selected-table-badge">${table}</span>`
    ).join('');
}

function selectAllTables() {
    const currentFilter = document.getElementById('tableSearch').value;
    const filteredTables = currentFilter ? 
        allTables.filter(table => table.toLowerCase().includes(currentFilter.toLowerCase())) : 
        allTables;
    
    // Adicionar apenas as tabelas filtradas que ainda não estão selecionadas
    filteredTables.forEach(table => {
        if (!selectedTables.includes(table)) {
            selectedTables.push(table);
        }
    });
    
    updateSelectedTablesField();
    updateSelectionCount();
    updateSelectedTablesPreview();
    updateTablesDisplay(currentFilter);
}

function deselectAllTables() {
    const currentFilter = document.getElementById('tableSearch').value;
    const filteredTables = currentFilter ? 
        allTables.filter(table => table.toLowerCase().includes(currentFilter.toLowerCase())) : 
        allTables;
    
    // Remover apenas as tabelas filtradas
    selectedTables = selectedTables.filter(table => !filteredTables.includes(table));
    
    updateSelectedTablesField();
    updateSelectionCount();
    updateSelectedTablesPreview();
    updateTablesDisplay(currentFilter);
}

// Permitir Enter para filtrar
document.getElementById('tableSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        filterTablesDynamic(e.target.value);
    }
});

function testConnection(type) {
    const form = document.getElementById('configForm');
    const formData = new FormData(form);
    
    // Adicionar dados específicos do teste
    formData.set('action', 'test_connection');
    formData.set('connection_type', type);
    
    // Mostrar loading
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Testando...';
    button.disabled = true;
    
    fetch('configuracoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Recarregar a página para mostrar a mensagem
        window.location.reload();
    })
    .catch(error => {
        alert('Erro ao testar conexão: ' + error);
        button.textContent = originalText;
        button.disabled = false;
    });
}

// Confirmar antes de sair sem salvar
let formChanged = false;
document.getElementById('configForm').addEventListener('change', () => {
    formChanged = true;
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
</body>
</html>