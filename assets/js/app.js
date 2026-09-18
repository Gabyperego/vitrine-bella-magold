/**
 * Vitrine Online: Gerenciamento do Carrinho, Filtros, Busca e Checkout WhatsApp
 */

document.addEventListener('DOMContentLoaded', () => {
    // Estado da Aplicação
    const state = {
        cart: JSON.parse(localStorage.getItem('vitrine_carrinho') || '[]'),
        vendedoras: [],
        categoriaAtiva: 'Todos',
        termoBusca: ''
    };

    // Elementos DOM
    const cartBackdrop = document.getElementById('cartBackdrop');
    const btnOpenCart = document.getElementById('btnOpenCart');
    const btnCloseCart = document.getElementById('btnCloseCart');
    const cartBadge = document.getElementById('cartBadge');
    const cartItemsList = document.getElementById('cartItemsList');
    const cartTotalValue = document.getElementById('cartTotalValue');
    const btnCheckoutWhatsApp = document.getElementById('btnCheckoutWhatsApp');
    const vendedoraSelect = document.getElementById('vendedoraSelect');
    const inputClienteNome = document.getElementById('clienteNome');
    const inputClienteTelefone = document.getElementById('clienteTelefone');
    const inputObservacoes = document.getElementById('observacoes');
    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchClear');
    const categoryPills = document.querySelectorAll('.category-pill');
    const productsGrid = document.getElementById('productsGrid');
    const productCards = document.querySelectorAll('.product-card');

    // Inicialização
    init();

    function init() {
        carregarVendedoras();
        atualizarCarrinhoUI();
        vincularEventos();
    }

    // ============================================================
    // VINCULAÇÃO DE EVENTOS
    // ============================================================
    function vincularEventos() {
        // Abertura e fechamento do carrinho
        btnOpenCart?.addEventListener('click', abrirCarrinho);
        btnCloseCart?.addEventListener('click', fecharCarrinho);
        cartBackdrop?.addEventListener('click', (e) => {
            if (e.target === cartBackdrop) fecharCarrinho();
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && cartBackdrop?.classList.contains('open')) {
                fecharCarrinho();
            }
        });

        // Botões "Adicionar ao Carrinho" nos cards
        productsGrid?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-add-cart');
            if (!btn || btn.disabled) return;

            const card = btn.closest('.product-card');
            if (!card) return;

            const produto = {
                id: card.dataset.id,
                codigo: card.dataset.codigo,
                nome: card.dataset.nome,
                preco: parseFloat(card.dataset.preco),
                imagem: card.dataset.imagem,
                categoria: card.dataset.categoria
            };

            adicionarAoCarrinho(produto);
        });

        // Filtro por Categorias (Pills)
        categoryPills.forEach(pill => {
            pill.addEventListener('click', () => {
                categoryPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                state.categoriaAtiva = pill.dataset.categoria || 'Todos';
                filtrarProdutos();
            });
        });

        // Busca por Nome / Descrição
        searchInput?.addEventListener('input', (e) => {
            state.termoBusca = e.target.value.trim().toLowerCase();
            if (searchClear) {
                searchClear.style.display = state.termoBusca ? 'block' : 'none';
            }
            filtrarProdutos();
        });

        searchClear?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            state.termoBusca = '';
            searchClear.style.display = 'none';
            filtrarProdutos();
            searchInput.focus();
        });

        // Máscara para Telefone do Cliente
        inputClienteTelefone?.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) {
                e.target.value = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 5) {
                e.target.value = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                e.target.value = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            } else {
                e.target.value = v;
            }
        });

        // Finalizar Pedido pelo WhatsApp
        btnCheckoutWhatsApp?.addEventListener('click', finalizarPedidoWhatsApp);
    }

    // ============================================================
    // GERENCIAMENTO DO CARRINHO
    // ============================================================
    function adicionarAoCarrinho(produto) {
        const itemExistente = state.cart.find(item => item.id == produto.id);

        if (itemExistente) {
            itemExistente.quantidade += 1;
        } else {
            state.cart.push({
                ...produto,
                quantidade: 1
            });
        }

        salvarCarrinho();
        atualizarCarrinhoUI();
        mostrarToast(`"${produto.nome}" adicionado ao carrinho!`);
    }

    function alterarQuantidade(produtoId, delta) {
        const item = state.cart.find(i => i.id == produtoId);
        if (!item) return;

        item.quantidade += delta;
        if (item.quantidade <= 0) {
            removerDoCarrinho(produtoId);
            return;
        }

        salvarCarrinho();
        atualizarCarrinhoUI();
    }

    function removerDoCarrinho(produtoId) {
        state.cart = state.cart.filter(i => i.id != produtoId);
        salvarCarrinho();
        atualizarCarrinhoUI();
        mostrarToast('Item removido do carrinho.', 'info');
    }

    function salvarCarrinho() {
        localStorage.setItem('vitrine_carrinho', JSON.stringify(state.cart));
    }

    function atualizarCarrinhoUI() {
        const totalItens = state.cart.reduce((acc, item) => acc + item.quantidade, 0);
        const totalValor = state.cart.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);

        // Atualiza Badge
        if (cartBadge) {
            cartBadge.textContent = totalItens;
            cartBadge.style.display = totalItens > 0 ? 'flex' : 'none';
        }

        // Atualiza Totalizador
        if (cartTotalValue) {
            cartTotalValue.textContent = formatarMoeda(totalValor);
        }

        // Habilita / Desabilita botão de checkout
        if (btnCheckoutWhatsApp) {
            btnCheckoutWhatsApp.disabled = state.cart.length === 0;
        }

        // Renderiza itens
        if (!cartItemsList) return;

        if (state.cart.length === 0) {
            cartItemsList.innerHTML = `
                <div class="cart-empty-message">
                    <i class="bi bi-bag-x"></i>
                    <p style="font-weight: 600; color: #475569; margin-bottom: 4px;">Seu carrinho está vazio</p>
                    <p style="font-size: 0.85rem;">Escolha peças incríveis na vitrine para montar seu pedido!</p>
                </div>
            `;
            return;
        }

        cartItemsList.innerHTML = state.cart.map(item => {
            const subtotal = item.preco * item.quantidade;
            return `
                <div class="cart-item-row" data-id="${item.id}">
                    <img src="${item.imagem}" alt="${escapeHtml(item.nome)}" class="cart-item-thumb">
                    <div class="cart-item-details">
                        <div class="cart-item-name" title="${escapeHtml(item.nome)}">${escapeHtml(item.nome)}</div>
                        <div class="cart-item-price-unit">${formatarMoeda(item.preco)} cada</div>
                        <div class="cart-item-actions">
                            <div class="qty-control-group">
                                <button type="button" class="btn-qty btn-minus" onclick="window.vitrineApp.alterarQtd(${item.id}, -1)">-</button>
                                <span class="qty-number">${item.quantidade}</span>
                                <button type="button" class="btn-qty btn-plus" onclick="window.vitrineApp.alterarQtd(${item.id}, 1)">+</button>
                            </div>
                            <span class="cart-item-subtotal">${formatarMoeda(subtotal)}</span>
                            <button type="button" class="btn-remove-item" onclick="window.vitrineApp.remover(${item.id})" title="Remover item">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function abrirCarrinho() {
        cartBackdrop?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function fecharCarrinho() {
        cartBackdrop?.classList.remove('open');
        document.body.style.overflow = '';
    }

    // ============================================================
    // FILTROS E BUSCA
    // ============================================================
    function filtrarProdutos() {
        let visiveis = 0;

        productCards.forEach(card => {
            const nome = (card.dataset.nome || '').toLowerCase();
            const desc = (card.dataset.descricao || '').toLowerCase();
            const cod = (card.dataset.codigo || '').toLowerCase();
            const cat = card.dataset.categoria || '';

            const coincideCategoria = (state.categoriaAtiva === 'Todos' || cat === state.categoriaAtiva);
            const coincideBusca = !state.termoBusca || (nome.includes(state.termoBusca) || desc.includes(state.termoBusca) || cod.includes(state.termoBusca));

            if (coincideCategoria && coincideBusca) {
                card.style.display = 'flex';
                visiveis++;
            } else {
                card.style.display = 'none';
            }
        });

        // Trata aviso de nenhum resultado
        let emptyState = document.getElementById('searchEmptyState');
        if (visiveis === 0) {
            if (!emptyState && productsGrid) {
                emptyState = document.createElement('div');
                emptyState.id = 'searchEmptyState';
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <i class="bi bi-search"></i>
                    <h3>Nenhuma peça encontrada</h3>
                    <p>Tente buscar por outro termo ou selecione outra categoria.</p>
                `;
                productsGrid.appendChild(emptyState);
            }
        } else if (emptyState) {
            emptyState.remove();
        }

        // Atualiza contador no topo
        const counterEl = document.getElementById('productsCountDisplay');
        if (counterEl) {
            counterEl.textContent = `${visiveis} peça${visiveis !== 1 ? 's' : ''} disponível${visiveis !== 1 ? 'eis' : ''}`;
        }
    }

    // ============================================================
    // CARREGAMENTO DE VENDEDORAS DINÂMICO
    // ============================================================
    async function carregarVendedoras() {
        if (!vendedoraSelect) return;

        try {
            const res = await fetch('api/vendedoras.php');
            const data = await res.json();

            if (data.success && Array.isArray(data.data)) {
                state.vendedoras = data.data;

                if (state.vendedoras.length === 0) {
                    vendedoraSelect.innerHTML = '<option value="">Nenhuma vendedora ativa no momento</option>';
                    return;
                }

                vendedoraSelect.innerHTML = `
                    <option value="">-- Escolha quem vai te atender --</option>
                    ${state.vendedoras.map(v => `
                        <option value="${v.id}">${escapeHtml(v.nome)} (${v.whatsapp_formatado || v.whatsapp})</option>
                    `).join('')}
                `;

                // Seleciona a primeira por padrão para comodidade
                if (state.vendedoras.length === 1) {
                    vendedoraSelect.selectedIndex = 1;
                }
            }
        } catch (err) {
            console.error('Erro ao carregar vendedoras:', err);
            vendedoraSelect.innerHTML = '<option value="">Erro ao carregar lista de atendimento</option>';
        }
    }

    // ============================================================
    // FINALIZAR PEDIDO PELO WHATSAPP
    // ============================================================
    async function finalizarPedidoWhatsApp() {
        if (state.cart.length === 0) {
            mostrarToast('Seu carrinho está vazio.', 'error');
            return;
        }

        const nome = inputClienteNome?.value.trim();
        const telefone = inputClienteTelefone?.value.trim();
        const observacoes = inputObservacoes?.value.trim();
        const vendedoraId = vendedoraSelect?.value;

        // Validações
        if (!nome) {
            mostrarToast('Por favor, informe seu nome.', 'error');
            inputClienteNome?.focus();
            return;
        }

        if (!vendedoraId) {
            mostrarToast('Por favor, selecione uma vendedora para ser atendido.', 'error');
            vendedoraSelect?.focus();
            return;
        }

        // Bloqueia botão durante envio
        btnCheckoutWhatsApp.disabled = true;
        const textoOriginal = btnCheckoutWhatsApp.innerHTML;
        btnCheckoutWhatsApp.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Preparando seu pedido...';

        try {
            const payload = {
                cliente_nome: nome,
                cliente_telefone: telefone,
                observacoes: observacoes,
                vendedora_id: vendedoraId,
                itens: state.cart.map(i => ({
                    id: i.id,
                    nome: i.nome,
                    preco: i.preco,
                    quantidade: i.quantidade
                }))
            };

            const response = await fetch('api/checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success && result.whatsapp_url) {
                mostrarToast('Pedido registrado! Redirecionando para o WhatsApp...', 'success');

                // Limpa carrinho
                state.cart = [];
                salvarCarrinho();
                atualizarCarrinhoUI();
                fecharCarrinho();

                // Limpa campos opcionais
                if (inputObservacoes) inputObservacoes.value = '';

                // Redireciona para o WhatsApp
                setTimeout(() => {
                    window.location.href = result.whatsapp_url;
                }, 700);

            } else {
                throw new Error(result.error || 'Não foi possível finalizar seu pedido.');
            }
        } catch (error) {
            console.error('Erro no checkout:', error);
            mostrarToast(error.message || 'Erro de comunicação ao enviar pedido.', 'error');
        } finally {
            btnCheckoutWhatsApp.disabled = false;
            btnCheckoutWhatsApp.innerHTML = textoOriginal;
        }
    }

    // ============================================================
    // UTILITÁRIOS
    // ============================================================
    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
    }

    function escapeHtml(string) {
        return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function mostrarToast(mensagem, tipo = 'success') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast-item ${tipo}`;
        const icone = tipo === 'success' ? 'bi-check-circle-fill' : (tipo === 'info' ? 'bi-info-circle-fill' : 'bi-exclamation-triangle-fill');
        toast.innerHTML = `<i class="bi ${icone}"></i> <span>${escapeHtml(mensagem)}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    }

    // Expõe funções para chamadas inline (botões do carrinho)
    window.vitrineApp = {
        alterarQtd: alterarQuantidade,
        remover: removerDoCarrinho,
        adicionar: adicionarAoCarrinho
    };
});

