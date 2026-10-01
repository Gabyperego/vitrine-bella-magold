/**
 * Scripts Interativos — dsoft Suporte
 */

// Abrir e Fechar Modal de Novo Lançamento
function abrirModalNovo() {
    const modal = document.getElementById('modalNovoTicket');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const input = document.getElementById('numero_ticket');
            if (input) input.focus();
        }, 150);
    }
}

function fecharModalNovo() {
    const modal = document.getElementById('modalNovoTicket');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Fechar modal ao clicar fora ou na tecla ESC
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modalNovoTicket');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                fecharModalNovo();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalNovo();
        }
    });

    // Busca Instantânea em Tempo Real na Tabela
    const inputBusca = document.getElementById('inputBuscaInstantanea');
    const tabela = document.getElementById('tabelaLancamentos');
    if (inputBusca && tabela) {
        inputBusca.addEventListener('input', (e) => {
            const termo = e.target.value.toLowerCase().trim();
            const linhas = tabela.querySelectorAll('tbody tr');
            linhas.forEach(linha => {
                const textoLinha = linha.innerText.toLowerCase();
                if (textoLinha.includes(termo)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        });
    }
});

/**
 * Alternar Status Rápido (1 Clique):
 * Em Aberto (escrita preta) <---> Concluído (tonalidade verde)
 */
async function alternarStatus(ticketId) {
    const row = document.getElementById(`ticket-row-${ticketId}`);
    if (!row) {
        window.location.href = `api/alternar_status.php?id=${ticketId}`;
        return;
    }

    // Feedback visual imediato
    row.style.opacity = '0.6';

    try {
        const formData = new FormData();
        formData.append('id', ticketId);

        const response = await fetch('api/alternar_status.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();
        row.style.opacity = '1';

        if (data.success) {
            const isConcluido = data.status === 'Concluído';

            // Atualiza classe da linha:
            // Aberto = row-status-aberto (escrita preta)
            // Concluído = row-status-concluido (tonalidade verde)
            if (isConcluido) {
                row.className = 'row-status-concluido';
            } else {
                row.className = 'row-status-aberto';
            }

            // Atualiza o Badge de Status
            const badge = row.querySelector('.badge-status');
            if (badge) {
                badge.innerHTML = isConcluido 
                    ? '<i class="bi bi-check-circle-fill"></i> <span>Concluído</span>'
                    : '<i class="bi bi-clock"></i> <span>Em Aberto</span>';
            }

            // Atualiza o Botão de Ação
            const actionCell = row.querySelector('td:last-child');
            if (actionCell) {
                const deleteBtnHtml = `
                    <button type="button" class="btn-icon-action danger" onclick="excluirTicket('${ticketId}', '${row.dataset.ticket || ''}')" title="Excluir Lançamento">
                        <i class="bi bi-trash3"></i>
                    </button>
                `;

                if (isConcluido) {
                    actionCell.innerHTML = `
                        <button type="button" class="btn-toggle-status btn-toggle-reabrir" onclick="alternarStatus('${ticketId}')" title="Clique para reabrir este chamado">
                            <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                        </button>
                        ${deleteBtnHtml}
                    `;
                } else {
                    actionCell.innerHTML = `
                        <button type="button" class="btn-toggle-status btn-toggle-concluir" onclick="alternarStatus('${ticketId}')" title="Marcar como Concluído na hora">
                            <i class="bi bi-check-lg"></i> Concluir
                        </button>
                        ${deleteBtnHtml}
                    `;
                }
            }

            // Efeito visual sutil de sucesso
            row.style.transition = 'background-color 0.4s ease';
        } else {
            alert('Não foi possível alterar o status. Atualizando página...');
            window.location.reload();
        }
    } catch (err) {
        // Fallback para redirect padrão
        window.location.href = `api/alternar_status.php?id=${ticketId}`;
    }
}

// Confirmação para Excluir Chamado
function excluirTicket(id, numero) {
    const numTexto = numero ? ` #${numero}` : '';
    if (confirm(`Tem certeza que deseja excluir o lançamento do ticket${numTexto}? Esta ação não pode ser desfeita.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/excluir_ticket.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
