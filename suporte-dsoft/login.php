<?php
/**
 * Tela de Login — dsoft Suporte
 */
require_once __DIR__ . '/config/auth.php';

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        $erro = 'Informe seu e-mail e senha para acessar.';
    } else {
        if (login($email, $senha)) {
            header('Location: index.php');
            exit;
        } else {
            $erro = 'E-mail ou senha incorretos. Verifique suas credenciais.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — dsoft Suporte</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page-body">

<!-- MARCA D'ÁGUA SUAVE NO FUNDO DA TELA DE LOGIN -->
<img src="assets/images/logo-dsoft.png" alt="dsoft watermark" class="dsoft-watermark-bg" style="opacity: 0.05;" aria-hidden="true">

<div class="login-card">
    <div class="login-logo-box">
        <img src="assets/images/logo-dsoft.png" alt="dsoft" class="login-logo-img">
        <h2 class="login-title">Agenda da Equipe de Suporte</h2>
        <p class="login-subtitle">Acesso restrito para analistas e suporte técnico</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert-flash alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group" style="margin-bottom: 18px;">
            <label for="email">E-mail Corporativo</label>
            <div style="position: relative;">
                <i class="bi bi-envelope" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="email" id="email" name="email" class="form-control" style="padding-left: 40px;" placeholder="analista@dsoft.com.br" required autofocus>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label for="senha">Senha de Acesso</label>
            <div style="position: relative;">
                <i class="bi bi-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="password" id="senha" name="senha" class="form-control" style="padding-left: 40px;" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn-action-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem;">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Entrar no Sistema</span>
        </button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 0.9rem; color: #64748b;">
        Não tem uma conta de analista?  
        <a href="cadastro.php" style="color: var(--dsoft-cyan); font-weight: 700; text-decoration: none;">Cadastre-se aqui</a>
    </div>

    <!-- Credenciais de Acesso da Equipe para Facilidade -->
    <div style="margin-top: 28px; padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.82rem; color: #475569;">
        <strong style="color: var(--dsoft-navy); display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
            <i class="bi bi-info-circle-fill" style="color: var(--dsoft-cyan);"></i> Contas de Acesso Disponíveis:
        </strong>
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <span>• <strong>Gaby Perego:</strong> <code>gaby@dsoft.com.br</code> (Senha: <code>123456</code>)</span>
            <span>• <strong>Lucas Silva:</strong> <code>lucas@dsoft.com.br</code> (Senha: <code>123456</code>)</span>
            <span>• <strong>Fernanda Costa:</strong> <code>fernanda@dsoft.com.br</code> (Senha: <code>123456</code>)</span>
        </div>
    </div>
</div>

</body>
</html>
