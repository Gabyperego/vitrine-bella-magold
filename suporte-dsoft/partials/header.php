<?php
/**
 * Header Oficial — dsoft Suporte
 */
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>dsoft Suporte</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- LOGOTIPO DSOFT EM MARCA D'ÁGUA NO FUNDO -->
<img src="assets/images/logo-dsoft.png" alt="dsoft watermark" class="dsoft-watermark-bg" aria-hidden="true">

<div class="app-wrapper">
    <!-- BARRA SUPERIOR (HEADER) -->
    <header class="navbar-dsoft">
        <div class="navbar-container">
            <!-- LOGO OFICIAL -->
            <a href="index.php" class="brand-wrapper">
                <img src="assets/images/logo-dsoft.png" alt="dsoft Logo" class="brand-logo-img">
                <div>
                    <span class="brand-subtag">Equipe de Suporte</span>
                </div>
            </a>

            <!-- NAVEGAÇÃO ENTRE ABAS -->
            <nav class="nav-links">
                <a href="index.php" class="nav-link-btn <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-pie-chart-fill"></i>
                    <span>Dashboard</span>
                </a>
                <a href="lancamentos.php" class="nav-link-btn <?= $currentPage === 'lancamentos.php' ? 'active' : '' ?>">
                    <i class="bi bi-card-checklist"></i>
                    <span>Todos os Lançamentos</span>
                </a>
                <a href="equipe.php" class="nav-link-btn <?= $currentPage === 'equipe.php' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Equipe</span>
                </a>
            </nav>

            <!-- AÇÕES DO TOPO E PERFIL -->
            <div style="display: flex; align-items: center; gap: 16px;">
                <button type="button" class="btn-action-primary" onclick="abrirModalNovo()">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Novo Lançamento</span>
                </button>

                <div class="user-profile-box">
                    <div class="user-avatar" title="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        <?= htmlspecialchars($user['avatar'] ?? 'DS') ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($user['nome'] ?? 'Analista') ?></span>
                        <span class="user-role"><?= htmlspecialchars($user['cargo'] ?? 'Suporte') ?></span>
                    </div>
                    <a href="logout.php" class="btn-logout" title="Sair do Sistema">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="main-container">
        <?php if ($flash): ?>
            <div class="alert-flash alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
                <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-octagon-fill' : 'check-circle-fill' ?>"></i>
                <span><?= htmlspecialchars($flash['text']) ?></span>
            </div>
        <?php endif; ?>
