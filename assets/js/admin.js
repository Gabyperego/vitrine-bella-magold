/**
 * Scripts do Painel Administrativo
 */

document.addEventListener('DOMContentLoaded', () => {
    // Fechar modais ao clicar no backdrop ou botão X
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                fecharModal(backdrop.id);
            }
        });
    });

    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const backdrop = btn.closest('.modal-backdrop');
            if (backdrop) fecharModal(backdrop.id);
        });
    });

    // Máscara para inputs de telefone de vendedoras
    document.querySelectorAll('input[type="tel"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 13) v = v.substring(0, 13);
            
            // Suporta formato com 55 ou sem 55
            if (v.startsWith('55') && v.length > 11) {
                e.target.value = '+' + v.replace(/^(\d{2})(\d{2})(\d{5})(\d{4})$/, '$1 ($2) $3-$4');
            } else if (v.length > 10) {
                e.target.value = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 5) {
                e.target.value = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                e.target.value = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            } else {
                e.target.value = v;
            }
        });
    });

    // Abre modal automaticamente se vier ?nova=1 na URL
    if (new URLSearchParams(window.location.search).get('nova') === '1') {
        abrirModal('modalNovaVenda');
    }
});

function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function confirmarExclusao(formElement, mensagem) {
    if (confirm(mensagem || 'Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.')) {
        formElement.submit();
    }
}

