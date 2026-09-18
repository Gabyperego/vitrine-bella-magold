<?php
// Identifica página atual para marcar link ativo
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <a href="index.php" class="sidebar-brand">
        <div class="sidebar-brand-icon" style="background: linear-gradient(135deg, #b8862d 0%, #ecd078 100%);">
            <i class="bi bi-gem"></i>
        </div>
        <div class="sidebar-brand-title">Bella Magold</div>
    </a>

    <!-- Menu: Dashboard | Produtos | Vendas | Vendedoras | Estojos | Categorias | Relatórios | Sair -->
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" title="Dashboard">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="produtos.php" class="nav-link <?= in_array($currentPage, ['produtos.php', 'produto-novo.php', 'produto-editar.php']) ? 'active' : '' ?>" title="Produtos">
            <i class="bi bi-box-seam"></i>
            <span>Produtos</span>
        </a>
        <a href="vendas.php" class="nav-link <?= $currentPage === 'vendas.php' ? 'active' : '' ?>" title="Vendas">
            <i class="bi bi-cart-check"></i>
            <span>Vendas</span>
        </a>
        <a href="vendedoras.php" class="nav-link <?= $currentPage === 'vendedoras.php' ? 'active' : '' ?>" title="Vendedoras">
            <i class="bi bi-people"></i>
            <span>Vendedoras</span>
        </a>
        <a href="estojos.php" class="nav-link <?= $currentPage === 'estojos.php' ? 'active' : '' ?>" title="Estojos">
            <i class="bi bi-briefcase"></i>
            <span>Estojos</span>
        </a>
        <a href="categorias.php" class="nav-link <?= $currentPage === 'categorias.php' ? 'active' : '' ?>" title="Categorias">
            <i class="bi bi-tags"></i>
            <span>Categorias</span>
        </a>
        <a href="relatorios.php" class="nav-link <?= $currentPage === 'relatorios.php' ? 'active' : '' ?>" title="Relatórios e Fechamento">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Relatórios</span>
        </a>
        <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: auto;" title="Sair do Sistema">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sair</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" class="btn-sidebar-store" title="Visualizar Loja Pública">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>Ver Vitrine Loja</span>
        </a>
    </div>
</aside>
