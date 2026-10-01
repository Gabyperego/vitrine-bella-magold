<?php
/**
 * Auto-cadastro de Analista — dsoft Suporte
 */
require_once __DIR__ . '/config/auth.php';

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cargo = trim($_POST['cargo'] ?? 'Analista de Suporte');
    $senha = trim($_POST['senha'] ?? '');
    $confirmarSenha = trim($_POST['confirmar_senha'] ?? '');

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas informadas não coincidem. Digite novamente.';
    } elseif (strlen($senha) < 4) {
        $erro = 'A senha deve ter no mínimo 4 caracteres.';
    } else {
        $db = Database::getInstance();
        $existente = $db->findAnalistaByEmail($email);
        if ($existente) {
            $erro = 'Este e-mail já está cadastrado no sistema. Tente fazer login ou use outro e-mail.';
        } else {
            $partes = explode(' ', $nome);
            $avatar = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : substr($partes[0], 1, 1)));

            $novo = $db->addAnalista([
                'nome' => $nome,
                'email' => $email,
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                'cargo' => $cargo,
                'avatar' => $avatar,
                'ativo' => true
            ]);

            // Faz login automático
            login($email, $senha);
            setFlash('success', "Bem-vindo(a) à equipe dsoft, {$nome}! Seu usuário foi criado com sucesso.");
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta de Analista — dsoft Suporte</title>
    
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

<img src="assets/images/logo-dsoft.png" alt="dsoft watermark" class="dsoft-watermark-bg" style="opacity: 0.05;" aria-hidden="true">

<div class="login-card" style="max-width: 480px;">
    <div class="login-logo-box">
        <img src="assets/images/logo-dsoft.png" alt="dsoft" class="login-logo-img">
        <h2 class="login-title">Cadastro de Novo Analista</h2>
        <p class="login-subtitle">Crie seu acesso para registrar e consultar chamados</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert-flash alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($erro) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="cadastro.php">
        <div class="form-group" style="margin-bottom: 14px;">
            <label for="nome">Nome Completo <span style="color: #ef4444;">*</span></label>
            <div style="position: relative;">
                <i class="bi bi-person" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="nome" name="nome" class="form-control" style="padding-left: 40px;" placeholder="Ex: Rodrigo Mendes" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" autofocus>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label for="email">E-mail de Acesso <span style="color: #ef4444;">*</span></label>
            <div style="position: relative;">
                <i class="bi bi-envelope" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="email" id="email" name="email" class="form-control" style="padding-left: 40px;" placeholder="rodrigo@dsoft.com.br" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label for="cargo">Cargo / Função no Suporte</label>
            <div style="position: relative;">
                <i class="bi bi-briefcase" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="cargo" name="cargo" class="form-control" style="padding-left: 40px;" placeholder="Ex: Analista de Suporte N1" value="<?= htmlspecialchars($_POST['cargo'] ?? 'Analista de Suporte') ?>">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label for="senha">Defina sua Senha <span style="color: #ef4444;">*</span></label>
            <div style="position: relative;">
                <i class="bi bi-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="password" id="senha" name="senha" class="form-control" style="padding-left: 40px;" placeholder="Mínimo 4 caracteres" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 22px;">
            <label for="confirmar_senha">Confirme sua Senha <span style="color: #ef4444;">*</span></label>
            <div style="position: relative;">
                <i class="bi bi-check2-circle" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" style="padding-left: 40px;" placeholder="Repita a senha" required>
            </div>
        </div>

        <button type="submit" class="btn-action-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem;">
            <i class="bi bi-person-check-fill"></i>
            <span>Criar Minha Conta & Acessar</span>
        </button>
    </form>

    <div style="margin-top: 20px; text-align: center; font-size: 0.9rem; color: #64748b;">
        Já tem uma conta cadastrada?  
        <a href="login.php" style="color: var(--dsoft-cyan); font-weight: 700; text-decoration: none;">Fazer Login</a>
    </div>
</div>

</body>
</html>
