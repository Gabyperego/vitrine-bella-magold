<?php
/**
 * BELLA MAGOLD SEMIJOIAS — Vitrine Oficial & Catálogo Online
 * Validações: Apenas produtos ativos, disponíveis, estoque > 0 e de estojos ativos
 */

require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// 1. Carrega Estojos Ativos para validação da Vitrine
$estojosAtivos = $supabase->select('cases', '*', ['ativo' => 'eq.true']);
$estojosAtivosIds = array_column($estojosAtivos, 'id');

// 2. Carrega Produtos do Supabase (Apenas Ativos e Disponíveis)
$todosProdutos = $supabase->select('products', '*', [
    'status' => 'eq.Disponível',
    'ativo' => 'eq.true'
], 'created_at.desc');

// 3. Validação Rigorosa: Estoque > 0 e pertencentes a um Estojo Ativo
$produtos = array_values(array_filter($todosProdutos, function($p) use ($estojosAtivosIds) {
    $estoque = (int)($p['estoque'] ?? 0);
    $caseId = $p['case_id'] ?? null;
    return $estoque > 0 && $caseId && in_array($caseId, $estojosAtivosIds);
}));

// 4. Carrega Categorias Ativas do Banco
$categoriasDb = $supabase->select('categories', '*', ['ativo' => 'eq.true'], 'nome.asc');
$categorias = ['Todos'];
if (!empty($categoriasDb)) {
    foreach ($categoriasDb as $c) $categorias[] = $c['nome'];
} else {
    $categorias = ['Todos', 'Brincos', 'Colares', 'Pulseiras', 'Anéis', 'Pingentes', 'Outros'];
}

// 5. Vendedoras ativas para link direto do WhatsApp no Hero
$vendedorasAtivas = $supabase->select('sellers', '*', ['ativo' => 'eq.true'], 'nome.asc');
$primeiraVendedora = $vendedorasAtivas[0] ?? null;
$whatsappHero = !empty($primeiraVendedora['whatsapp']) ? sanitizeWhatsApp($primeiraVendedora['whatsapp']) : '5567999887766';

// 6. Estado de login administrativo
$isAdminLoggedIn = isset($_SESSION['admin_user']);

$totalDisponiveis = count($produtos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bella Magold Semijoias — Semijoias que contam sua história</title>
    
    <!-- Google Fonts: Dancing Script / Great Vibes (script), Playfair Display (serif), Plus Jakarta Sans (sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,400;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Estilo Oficial da Vitrine -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- ============================================================
         1. BARRA SUPERIOR (ANNOUNCEMENT BAR)
         ============================================================ -->
    <div class="top-announcement">
        <div class="top-announcement-container">
            <div class="top-announcement-item">
                <span>✦</span>
                <span>ATENDIMENTO SOMENTE EM IVINHEMA-MS</span>
            </div>
            <div class="top-announcement-item active-mobile">
                <span>♡</span>
                <span>BELLA MAGOLD SEMIJOIAS</span>
            </div>
            <div class="top-announcement-item">
                <i class="bi bi-chat-dots"></i>
                <span>ATENDIMENTO PERSONALIZADO</span>
            </div>
        </div>
    </div>

    <!-- ============================================================
         2. CABEÇALHO PRINCIPAL
         ============================================================ -->
    <header class="site-header">
        <div class="header-container">
            <!-- Botão Redondo Hamburger (Esquerda) -->
            <button type="button" class="header-menu-toggle" id="btnOpenMobileMenu" aria-label="Abrir Menu">
                <i class="bi bi-list"></i>
            </button>

            <!-- Logotipo Centralizado: bella ♡ / MAGOLD / SEMIJOIAS -->
            <a href="index.php" class="header-brand-logo">
                <span class="logo-cursive">bella ♡</span>
                <span class="logo-serif">MAGOLD</span>
                <span class="logo-tag">SEMIJOIAS</span>
            </a>

            <!-- Navegação e Ações (Direita) -->
            <div class="header-right-nav">
                <a href="index.php" class="header-nav-link">Início</a>
                <a href="#catalogo" class="header-nav-link">Catálogo</a>

                <!-- Botão Carrinho de Compras com Badge -->
                <button type="button" id="btnOpenCart" class="btn-cart-nav" aria-label="Abrir Carrinho">
                    <i class="bi bi-bag"></i>
                    <span id="cartBadge" class="cart-badge-count" style="display: none;">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ============================================================
         3. MENU LATERAL OFFCANVAS (MOBILE DRAWER)
         ============================================================ -->
    <div class="mobile-nav-backdrop" id="mobileNavBackdrop">
        <aside class="mobile-nav-drawer">
            <div class="mobile-nav-header">
                <div class="header-brand-logo" style="position: static; transform: none;">
                    <span class="logo-cursive">bella ♡</span>
                    <span class="logo-serif">MAGOLD</span>
                </div>
                <button type="button" class="btn-close-drawer" id="btnCloseMobileMenu" aria-label="Fechar Menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="mobile-nav-links">
                <a href="index.php" class="mobile-nav-item">
                    <i class="bi bi-house text-primary"></i> Início
                </a>
                <a href="#catalogo" class="mobile-nav-item" onclick="document.getElementById('mobileNavBackdrop').classList.remove('open')">
                    <i class="bi bi-gem text-primary"></i> Catálogo de Peças
                </a>
                <a href="https://wa.me/<?= $whatsappHero ?>?text=<?= rawurlencode('Olá! Vim pelo site da Bella Magold Semijoias e gostaria de atendimento.') ?>" target="_blank" class="mobile-nav-item">
                    <i class="bi bi-whatsapp" style="color: #25d366;"></i> Falar no WhatsApp
                </a>
            </nav>
        </aside>
    </div>

    <!-- ============================================================
         4. HERO SECTION COM MARCA D'ÁGUA E VANTAGENS
         ============================================================ -->
    <section class="hero-banner">
        <!-- Marca d'água de anel circular sutil -->
        <div class="hero-watermark-circle">
            <div class="hero-watermark-inner">
                <div class="hero-watermark-text">BELLA MAGOLD</div>
            </div>
        </div>

        <div class="hero-content">
            <span class="hero-subtitle-over">SEMIJOIAS QUE CONTAM SUA HISTÓRIA</span>
            
            <h1 class="hero-title">
                Elegância delicada<br>
                para todos os momentos.
            </h1>

            <p class="hero-desc">
                Peças selecionadas para realçar sua beleza e acompanhar você em cada detalhe do seu dia.
            </p>

            <div class="hero-buttons">
                <a href="#catalogo" class="btn-hero-primary">
                    <span>Ver catálogo</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
                <a href="https://wa.me/<?= $whatsappHero ?>?text=<?= rawurlencode('Olá! Vim pelo site da Bella Magold Semijoias e gostaria de atendimento.') ?>" target="_blank" class="btn-hero-secondary">
                    <i class="bi bi-chat-dots"></i>
                    <span>Falar no WhatsApp</span>
                </a>
            </div>

            <!-- Barra de Vantagens (Rodapé do Hero) -->
            <div class="hero-features-bar">
                <div class="hero-feature-item">
                    <div class="feature-icon-box">✦</div>
                    <div class="feature-info">
                        <strong>Atendimento local</strong>
                        <span>Ivinhema - MS</span>
                    </div>
                </div>

                <div class="hero-feature-item">
                    <div class="feature-icon-box">◇</div>
                    <div class="feature-info">
                        <strong>Semijoias selecionadas</strong>
                        <span>Qualidade e delicadeza</span>
                    </div>
                </div>

                <div class="hero-feature-item">
                    <div class="feature-icon-box"><i class="bi bi-credit-card-2-front"></i></div>
                    <div class="feature-info">
                        <strong>Pagamento facilitado</strong>
                        <span>PIX, cartão e mais</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         5. SEÇÃO DE CATÁLOGO & FILTROS
         ============================================================ -->
    <main class="catalog-section" id="catalogo">
        <div class="catalog-controls">
            <!-- Barra de Pesquisa em Tempo Real -->
            <div class="search-wrapper">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Pesquisar por nome ou código..." autocomplete="off">
                <button type="button" id="searchClear" class="search-clear" title="Limpar busca">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <!-- Pílulas de Categoria -->
            <div class="categories-pills-bar">
                <?php foreach ($categorias as $index => $cat): ?>
                    <button type="button" class="category-pill <?= $index === 0 ? 'active' : '' ?>" data-categoria="<?= sanitize($cat) ?>">
                        <?= sanitize($cat) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="catalog-header-info">
            <h2 class="catalog-title">Coleção Disponível</h2>
            <div id="productsCountDisplay" class="catalog-counter">
                <?= $totalDisponiveis ?> peças disponíveis
            </div>
        </div>

        <!-- Grid de Cards de Produtos -->
        <div class="products-grid" id="productsGrid">
            <?php if (empty($produtos)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <i class="bi bi-box2" style="font-size: 2.5rem; color: #d6c6b2; margin-bottom: 12px; display: block;"></i>
                    <h3 style="font-family: 'Playfair Display', serif; color: var(--text-heading); margin-bottom: 8px;">Nenhuma peça disponível no momento</h3>
                    <p>Novas coleções estão sendo preparadas nos estojos das vendedoras.</p>
                </div>
            <?php else: ?>
                <?php foreach ($produtos as $p): 
                    $status = $p['status'] ?? 'Disponível';
                    $imagem = !empty($p['imagem_url']) ? $p['imagem_url'] : 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80';
                ?>
                    <div class="product-card" 
                         data-id="<?= sanitize($p['id']) ?>"
                         data-codigo="<?= sanitize($p['codigo'] ?? '') ?>"
                         data-nome="<?= sanitize($p['nome']) ?>"
                         data-descricao="<?= sanitize($p['descricao'] ?? '') ?>"
                         data-categoria="<?= sanitize($p['categoria'] ?? 'Outros') ?>"
                         data-preco="<?= (float)($p['preco'] ?? 0) ?>"
                         data-imagem="<?= sanitize($imagem) ?>"
                         data-status="Disponível">
                        
                        <!-- Mídia da Peça -->
                        <div class="card-media">
                            <img src="<?= sanitize($imagem) ?>" alt="<?= sanitize($p['nome']) ?>" loading="lazy">
                            <span class="badge-category"><?= sanitize($p['categoria'] ?? 'Geral') ?></span>
                            <span class="badge-status status-disponivel">Disponível</span>
                        </div>

                        <!-- Detalhes da Peça -->
                        <div class="card-body">
                            <?php if (!empty($p['codigo'])): ?>
                                <span class="card-code">Cód: <?= sanitize($p['codigo']) ?></span>
                            <?php endif; ?>
                            <h3 class="card-title"><?= sanitize($p['nome']) ?></h3>
                            <p class="card-description"><?= sanitize($p['descricao'] ?? 'Peça refinada banhada a ouro 18k.') ?></p>
                            
                            <div class="card-footer">
                                <div class="card-price-wrapper">
                                    <span class="card-price-label">Valor</span>
                                    <span class="card-price"><?= formatMoney($p['preco']) ?></span>
                                </div>
                                <button type="button" class="btn-add-cart" title="Adicionar ao carrinho" aria-label="Adicionar <?= sanitize($p['nome']) ?> ao carrinho">
                                    <i class="bi bi-bag-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- ============================================================
         6. DRAWER LATERAL DO CARRINHO (OFFCANVAS)
         ============================================================ -->
    <div class="cart-backdrop" id="cartBackdrop">
        <aside class="cart-drawer" aria-label="Carrinho de Compras">
            <div class="cart-drawer-header">
                <div class="cart-drawer-title">
                    <i class="bi bi-bag-check" style="color: var(--primary);"></i>
                    <span>Seu Carrinho</span>
                </div>
                <button type="button" class="btn-close-drawer" id="btnCloseCart" aria-label="Fechar Carrinho">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Lista de Itens Adicionados -->
            <div class="cart-items-list" id="cartItemsList">
                <!-- Preenchido dinamicamente via app.js -->
            </div>

            <!-- Totalizador e Checkout -->
            <div class="cart-drawer-footer">
                <div class="cart-total-row">
                    <span class="cart-total-label">Total do Pedido:</span>
                    <span class="cart-total-value" id="cartTotalValue">R$ 0,00</span>
                </div>

                <!-- Formulário com Validações Rigorosas -->
                <div class="checkout-fields">
                    <div class="field-group">
                        <label for="clienteNome" class="field-label">
                            <span>Seu Nome <span class="required-star">*</span></span>
                        </label>
                        <input type="text" id="clienteNome" class="form-input" placeholder="Digite seu nome completo" required>
                    </div>

                    <div class="field-group">
                        <label for="clienteTelefone" class="field-label">
                            <span>Seu WhatsApp / Telefone</span>
                            <span class="optional-hint">(opcional)</span>
                        </label>
                        <input type="tel" id="clienteTelefone" class="form-input" placeholder="(67) 99999-9999">
                    </div>

                    <div class="field-group">
                        <label for="vendedoraSelect" class="field-label">
                            <span>Escolha quem vai te atender <span class="required-star">*</span></span>
                        </label>
                        <select id="vendedoraSelect" class="form-select" required>
                            <option value="">Carregando vendedoras...</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="observacoes" class="field-label">
                            <span>Observações ou dúvidas</span>
                            <span class="optional-hint">(opcional)</span>
                        </label>
                        <textarea id="observacoes" class="form-textarea" placeholder="Ex: Gostaria de verificar a disponibilidade para entrega."></textarea>
                    </div>
                </div>

                <!-- Botão Finalizar pelo WhatsApp -->
                <button type="button" id="btnCheckoutWhatsApp" class="btn-whatsapp-finish" disabled>
                    <i class="bi bi-whatsapp"></i>
                    <span>FINALIZAR PEDIDO PELO WHATSAPP</span>
                </button>
            </div>
        </aside>
    </div>

    <!-- ============================================================
         7. RODAPÉ DO SITE
         ============================================================ -->
    <footer class="site-footer">
        <div class="footer-container">
            <div>
                <div class="footer-brand">Bella Magold Semijoias</div>
                <div class="footer-copy">
                    &copy; <?= date('Y') ?> <strong>Bella Magold Semijoias</strong>. Atendimento exclusivo em Ivinhema - MS.
                </div>
            </div>
            <div class="footer-links">
                <a href="index.php">Início</a>
                <a href="#catalogo">Catálogo</a>
            </div>
        </div>
    </footer>

    <!-- Scripts da Loja -->
    <script src="assets/js/app.js"></script>
    <script>
    // Controle do menu mobile hamburger
    document.addEventListener('DOMContentLoaded', () => {
        const btnOpenMobile = document.getElementById('btnOpenMobileMenu');
        const btnCloseMobile = document.getElementById('btnCloseMobileMenu');
        const mobileBackdrop = document.getElementById('mobileNavBackdrop');

        btnOpenMobile?.addEventListener('click', () => {
            mobileBackdrop?.classList.add('open');
        });

        btnCloseMobile?.addEventListener('click', () => {
            mobileBackdrop?.classList.remove('open');
        });

        mobileBackdrop?.addEventListener('click', (e) => {
            if (e.target === mobileBackdrop) {
                mobileBackdrop.classList.remove('open');
            }
        });
    });
    </script>
</body>
</html>
