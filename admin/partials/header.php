<?php
require_once __DIR__ . '/auth_check.php';
$flash = getFlashMessage();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?>Bella Magold Admin</title>
    
    <!-- Google Fonts: Playfair Display, Dancing Script, Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<!-- Marca d'água Bella Magold de Fundo -->
<div class="admin-watermark-bg" aria-hidden="true">
    <div class="watermark-logo-circle">
        <div class="watermark-text-script">bella ♡</div>
        <div class="watermark-text-serif">MAGOLD</div>
        <div class="watermark-text-sub">SEMIJOIAS</div>
    </div>
</div>

<div class="admin-wrapper">
    <!-- Barra de Navegação Superior (Header Horizontal Oficial) -->
    <header class="admin-navbar">
        <div class="admin-navbar-container">
            <!-- Logo & Usuário (Esquerda) -->
            <a href="index.php" class="admin-brand-box">
                <div class="admin-brand-icon">
                    <i class="bi bi-gem"></i>
                </div>
                <div class="admin-brand-texts">
                    <div class="admin-brand-name">Bella Magold</div>
                    <div class="admin-brand-user">Olá, <?= sanitize($userName ?? 'Gaby Pérego') ?></div>
                </div>
            </a>

            <!-- Links em Pílula (Direita) -->
            <nav class="admin-nav-tabs">
                <a href="index.php" class="nav-tab-pill <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    Dashboard
                </a>
                <a href="vendas.php?nova=1" class="nav-tab-pill" onclick="if(document.getElementById('modalNovaVenda')) { abrirModal('modalNovaVenda'); return false; }">
                    Nova venda
                </a>
                <a href="vendas.php" class="nav-tab-pill <?= $currentPage === 'vendas.php' ? 'active' : '' ?>">
                    Vendas
                </a>
                <a href="estojos.php" class="nav-tab-pill <?= $currentPage === 'estojos.php' ? 'active' : '' ?>">
                    Controle de estojos
                </a>
                <a href="produtos.php" class="nav-tab-pill <?= in_array($currentPage, ['produtos.php', 'produto-novo.php', 'produto-editar.php']) ? 'active' : '' ?>">
                    Produtos
                </a>
                <a href="vendedoras.php" class="nav-tab-pill <?= $currentPage === 'vendedoras.php' ? 'active' : '' ?>">
                    Vendedoras
                </a>
                <a href="relatorios.php" class="nav-tab-pill <?= $currentPage === 'relatorios.php' ? 'active' : '' ?>">
                    Relatórios
                </a>
                <a href="logout.php" class="nav-tab-pill btn-pill-outline" title="Sair do sistema">
                    Sair
                </a>
            </nav>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="admin-main-container">
        <?php if ($flash): ?>
            <div class="admin-alert alert-<?= $flash['type'] === 'error' ? 'danger' : sanitize($flash['type']) ?>">
                <i class="bi bi-info-circle-fill"></i>
                <span><?= sanitize($flash['text']) ?></span>
            </div>
        <?php endif; ?>
