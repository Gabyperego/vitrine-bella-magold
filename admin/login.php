<?php
/**
 * Login Administrativo com Supabase Auth
 */

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if (isset($_SESSION['admin_user'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$supabase = Supabase::getInstance();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $erro = 'Por favor, preencha o e-mail e a senha.';
    } else {
        $authResult = $supabase->signInWithPassword($email, $password);

        if ($authResult['success']) {
            $_SESSION['admin_user'] = [
                'id' => $authResult['user']['id'] ?? 'user_1',
                'email' => $authResult['user']['email'] ?? $email,
                'role' => $authResult['user']['role'] ?? 'Administrador',
                'user_metadata' => $authResult['user']['user_metadata'] ?? ['nome' => 'Administrador'],
                'access_token' => $authResult['access_token'] ?? ''
            ];

            setFlashMessage('success', 'Bem-vindo(a) ao Painel de Gestão!');
            header('Location: index.php');
            exit;
        } else {
            $erro = $authResult['error'] ?? 'Credenciais incorretas ou usuário não autorizado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo — Bella Magold Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-body">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2 class="login-title">Acesso Restrito</h2>
            <p class="login-subtitle">Entre com suas credenciais do Supabase</p>
        </div>

        <?php if ($supabase->isDemo()): ?>
            <div class="alert alert-info" style="font-size: 0.82rem; padding: 10px 14px;">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <strong>Modo Demonstração:</strong><br>
                    E-mail: <code>admin@admin.com</code><br>
                    Senha: <code>admin123</code>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger" style="font-size: 0.85rem; padding: 10px 14px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= sanitize($erro) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@seudominio.com" required value="<?= sanitize($_POST['email'] ?? ($supabase->isDemo() ? 'admin@admin.com' : '')) ?>" autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="password" class="form-label">Senha</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required value="<?= $supabase->isDemo() ? 'admin123' : '' ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Entrar no Painel</span>
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px;">
            <a href="../index.php" style="color: var(--admin-muted); font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Voltar para a vitrine da loja
            </a>
        </div>
    </div>

</body>
</html>

