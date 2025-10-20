<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $login_result = validateLogin($username, $password);
    
    if ($login_result['success']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $login_result['username'];
        $_SESSION['user_level'] = $login_result['level'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Usuário ou senha inválidos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Migração de Dados</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>🔐 Login - Sistema de Migração</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="login-info">
            <h4>Usuários de Teste:</h4>
            <div class="user-types">
                <div class="user-type admin">
                    <strong>Administrador</strong><br>
                    Usuário: <code>admin</code><br>
                    Senha: <code>admin123</code><br>
                    <small>Acesso completo</small>
                </div>
                <div class="user-type operador">
                    <strong>Operador</strong><br>
                    Usuário: <code>operador</code><br>
                    Senha: <code>operador123</code><br>
                    <small>Acesso somente à migração</small>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>👤 Usuário:</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>🔒 Senha:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">🚀 Entrar</button>
        </form>
    </div>
</body>
</html>