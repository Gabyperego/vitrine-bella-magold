/**
 * Lógica da Interface SPA — dsoft Suporte para Netlify
 */

let currentView = 'dashboard';
let dashPeriodo = 'mes';
let lancamentosStatusFiltro = '';

document.addEventListener('DOMContentLoaded', async () => {
    initSupabase();
    atualizarStatusConexaoSupabase();
    await checkAuth();

    // Data padrão no modal de novo ticket
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const dataInput = document.getElementById('tckData');
    if (dataInput) dataInput.value = now.toISOString().slice(0, 16);
});

function atualizarStatusConexaoSupabase() {
    const box = document.getElementById('supabaseStatusText');
    if (!box) return;
    if (isSupabaseActive) {
        box.innerHTML = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span> <span>Supabase: <strong>Conectado na Nuvem</strong></span>';
    } else {
        box.innerHTML = '<span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span> <span>Supabase: <strong>Modo Local / Demo</strong></span>';
    }
}

function showToast(mensagem, tipo = 'success') {
    const t = document.getElementById('toastNotification');
    if (!t) return;
    t.className = `alert-flash alert-${tipo}`;
    t.innerHTML = `<i class="bi bi-check-circle-fill"></i> <span>${mensagem}</span>`;
    t.style.display = 'flex';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// ==========================================
// AUTENTICAÇÃO E TELAS
// ==========================================
async function checkAuth() {
    const user = AuthService.getCurrentUser();
    const authSec = document.getElementById('authSection');
    const mainApp = document.getElementById('mainApp');

    if (!user) {
        authSec.style.display = 'flex';
        mainApp.style.display = 'none';
    } else {
        authSec.style.display = 'none';
        mainApp.style.display = 'flex';

        // Atualiza cabeçalho
        document.getElementById('headerUserName').innerText = user.nome;
        document.getElementById('headerUserRole').innerText = user.cargo || 'Analista';
        document.getElementById('headerUserAvatar').innerText = user.avatar || 'DS';

        await popularSelectsAnalistas();
        await navegarPara(currentView);
    }
}

function toggleAuthMode(mode) {
    const fLogin = document.getElementById('formLogin');
    const fReg = document.getElementById('formRegister');
    const aTitle = document.getElementById('authTitle');
    const aSub = document.getElementById('authSubtitle');
    const alert = document.getElementById('authAlert');
    alert.style.display = 'none';

    if (mode === 'register') {
        fLogin.style.display = 'none';
        fReg.style.display = 'block';
        aTitle.innerText = 'Cadastro de Novo Analista';
        aSub.innerText = 'Crie seu acesso para a equipe de suporte';
    } else {
        fLogin.style.display = 'block';
        fReg.style.display = 'none';
        aTitle.innerText = 'Agenda da Equipe de Suporte';
        aSub.innerText = 'Acesso restrito para analistas dsoft';
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const senha = document.getElementById('loginSenha').value;
    const alert = document.getElementById('authAlert');

    const res = await AuthService.login(email, senha);
    if (res.success) {
        showToast(`Bem-vindo(a), ${res.user.nome}!`);
        await checkAuth();
    } else {
        alert.innerText = res.message;
        alert.style.display = 'flex';
    }
}

async function handleRegister(e) {
    e.preventDefault();
    const nome = document.getElementById('regNome').value;
    const email = document.getElementById('regEmail').value;
    const cargo = document.getElementById('regCargo').value;
    const senha = document.getElementById('regSenha').value;
    const confirma = document.getElementById('regConfirma').value;
    const alert = document.getElementById('authAlert');

    if (senha !== confirma) {
        alert.innerText = 'As senhas informadas não coincidem.';
        alert.style.display = 'flex';
        return;
    }

    const res = await AuthService.register(nome, email, cargo, senha);
    if (res.success) {
        showToast(`Conta criada com sucesso! Olá, ${res.user.nome}`);
        await checkAuth();
    } else {
        alert.innerText = res.message;
        alert.style.display = 'flex';
    }
}

function handleLogout() {
    AuthService.logout();
    checkAuth();
}

// ==========================================
// NAVEGAÇÃO ENTRE ABAS
// ==========================================
async function navegarPara(view) {
    currentView = view;

    document.querySelectorAll('.view-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.nav-link-btn').forEach(btn => btn.classList.remove('active'));

    if (view === 'dashboard') {
        document.getElementById('viewDashboard').style.display = 'block';
        document.getElementById('tabNavDashboard').classList.add('active');
        await recarregarDashboard();
    } else if (view === 'lancamentos') {
        document.getElementById('viewLancamentos').style.display = 'block';
        document.getElementById('tabNavLancamentos').classList.add('active');
        await recarregarLancamentos();
    } else if (view === 'equipe') {
        document.getElementById('viewEquipe').style.display = 'block';
        document.getElementById('tabNavEquipe').classList.add('active');
        await recarregarEquipe();
    }
}

async function popularSelectsAnalistas() {
    const analistas = await AnalistasService.getAll();
    const user = AuthService.getCurrentUser();

    // Select no Modal Novo Ticket
    const selTicket = document.getElementById('tckAnalista');
    if (selTicket) {
        selTicket.innerHTML = analistas.map(a => 
            `<option value="${a.nome}" ${user && user.nome === a.nome ? 'selected' : ''}>${a.nome} (${a.cargo || 'Suporte'})</option>`
        ).join('');
    }

    // Select no Filtro do Dashboard
    const selDash = document.getElementById('dashSelectAnalista');
    if (selDash) {
        const cur = selDash.value;
        selDash.innerHTML = '<option value="">👤 Todos os Analistas</option>' + 
            analistas.map(a => `<option value="${a.nome}" ${cur === a.nome ? 'selected' : ''}>${a.nome}</option>`).join('');
    }

    // Select no Filtro de Lançamentos
    const selLanc = document.getElementById('selectAnalistaLancamentos');
    if (selLanc) {
        const cur = selLanc.value;
        selLanc.innerHTML = '<option value="">👤 Todos os Analistas</option>' + 
            analistas.map(a => `<option value="${a.nome}" ${cur === a.nome ? 'selected' : ''}>${a.nome}</option>`).join('');
    }
}

// ==========================================
// LÓGICA DO DASHBOARD
// ==========================================
function filtrarDashPeriodo(periodo) {
    dashPeriodo = periodo;
    document.querySelectorAll('.filter-pills-group .filter-pill').forEach(p => p.classList.remove('active'));

    if (periodo === 'hoje') document.getElementById('dashPillHoje').classList.add('active');
    else if (periodo === 'semana') document.getElementById('dashPillSemana').classList.add('active');
    else if (periodo === 'mes') document.getElementById('dashPillMes').classList.add('active');
    else document.getElementById('dashPillTodos').classList.add('active');

    recarregarDashboard();
}

async function recarregarDashboard() {
    const analista = document.getElementById('dashSelectAnalista').value;
    const tipo = document.getElementById('dashSelectTipo').value;

    const tickets = await TicketsService.getAll({
        periodo: dashPeriodo !== 'todos' ? dashPeriodo : '',
        analista,
        tipo
    });

    const total = tickets.length;
    let abertos = 0;
    let concluidos = 0;
    let bugs = 0;
    let melhorias = 0;

    tickets.forEach(t => {
        if (t.status === 'Concluído') concluidos++;
        else abertos++;

        if (t.tipo === 'Bug') bugs++;
        else if (t.tipo === 'Melhoria') melhorias++;
    });

    const taxa = total > 0 ? Math.round((concluidos / total) * 100) : 0;

    document.getElementById('dashMetricTotal').innerText = total;
    document.getElementById('dashMetricAbertos').innerText = abertos;
    document.getElementById('dashMetricConcluidos').innerText = concluidos;
    document.getElementById('dashMetricBugs').innerText = bugs;
    document.getElementById('dashMetricMelhorias').innerText = melhorias;
    document.getElementById('dashMetricTaxa').innerText = `${taxa}% de resolução`;

    // Renderiza Tabela Recente
    const tbody = document.getElementById('dashTableBody');
    if (tickets.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 32px;">Nenhum atendimento no período selecionado.</td></tr>`;
        return;
    }

    tbody.innerHTML = tickets.slice(0, 6).map(t => renderizarLinhaTicket(t, false)).join('');
}

// ==========================================
// LÓGICA DE TODOS OS LANÇAMENTOS
// ==========================================
function filtrarStatusLancamentos(status) {
    lancamentosStatusFiltro = status;
    document.getElementById('pillStatusTodos').classList.toggle('active', status === '');
    document.getElementById('pillStatusAberto').classList.toggle('active', status === 'Em Aberto');
    document.getElementById('pillStatusConcluido').classList.toggle('active', status === 'Concluído');
    recarregarLancamentos();
}

async function recarregarLancamentos() {
    const busca = document.getElementById('inputBuscaLancamentos').value;
    const analista = document.getElementById('selectAnalistaLancamentos').value;
    const tipo = document.getElementById('selectTipoLancamentos').value;

    const tickets = await TicketsService.getAll({
        busca,
        analista,
        tipo,
        status: lancamentosStatusFiltro
    });

    const tbody = document.getElementById('lancamentosTableBody');
    if (tickets.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 48px;">
            <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
            Nenhum chamado encontrado.
        </td></tr>`;
        return;
    }

    tbody.innerHTML = tickets.map(t => renderizarLinhaTicket(t, true)).join('');
}

// RENDERIZADOR DA LINHA DO TICKET CONFORME ESPECIFICAÇÃO:
// - Em Aberto: classe row-status-aberto (escrita preta marcante)
// - Concluído: classe row-status-concluido (tonalidade verde)
function renderizarLinhaTicket(t, allowDelete = true) {
    const isConcluido = t.status === 'Concluído';
    const rowClass = isConcluido ? 'row-status-concluido' : 'row-status-aberto';

    const tipoBadge = t.tipo === 'Bug' ? 'tipo-bug' : (t.tipo === 'Melhoria' ? 'tipo-melhoria' : 'tipo-outro');
    const dataFmt = new Date(t.data_hora || t.created_at).toLocaleDateString('pt-BR');
    const horaFmt = new Date(t.data_hora || t.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    const btnAcao = isConcluido 
        ? `<button type="button" class="btn-toggle-status btn-toggle-reabrir" onclick="handleAlternarStatus('${t.id}')" title="Clique para reabrir">
             <i class="bi bi-arrow-counterclockwise"></i> Reabrir
           </button>`
        : `<button type="button" class="btn-toggle-status btn-toggle-concluir" onclick="handleAlternarStatus('${t.id}')" title="Marcar como Concluído">
             <i class="bi bi-check-lg"></i> Concluir
           </button>`;

    const btnDelete = allowDelete ? `
        <button type="button" class="btn-icon-action danger" onclick="handleExcluirTicket('${t.id}', '${t.numero_ticket}')" title="Excluir">
            <i class="bi bi-trash3"></i>
        </button>
    ` : '';

    return `
        <tr class="${rowClass}" id="row-${t.id}">
            <td><strong class="ticket-title">#${t.numero_ticket}</strong></td>
            <td style="white-space: nowrap;">
                <div>${dataFmt}</div>
                <div style="font-size: 0.78rem; opacity: 0.8;">${horaFmt}</div>
            </td>
            <td><strong>${t.analista_nome}</strong></td>
            <td><strong>${t.cliente_empresa}</strong></td>
            <td><span class="badge-tipo ${tipoBadge}">${t.tipo}</span></td>
            <td>
                <div style="line-height: 1.4; word-break: break-word;">${t.descricao}</div>
                ${isConcluido && t.concluido_em ? `<div style="font-size: 0.75rem; color: #047857; margin-top: 4px; font-weight: 700;"><i class="bi bi-check2-all"></i> Concluído em ${new Date(t.concluido_em).toLocaleDateString('pt-BR')} ${new Date(t.concluido_em).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}</div>` : ''}
            </td>
            <td>
                <span class="badge-status">
                    <i class="bi bi-${isConcluido ? 'check-circle-fill' : 'clock'}"></i>
                    <span>${isConcluido ? 'Concluído' : 'Em Aberto'}</span>
                </span>
            </td>
            <td style="text-align: right; white-space: nowrap;">
                ${btnAcao}
                ${btnDelete}
            </td>
        </tr>
    `;
}

async function handleAlternarStatus(id) {
    const novoStatus = await TicketsService.toggleStatus(id);
    showToast(`Status atualizado para "${novoStatus}"`);
    if (currentView === 'dashboard') recarregarDashboard();
    else recarregarLancamentos();
}

async function handleExcluirTicket(id, numero) {
    if (confirm(`Deseja realmente excluir o ticket #${numero}?`)) {
        await TicketsService.delete(id);
        showToast(`Ticket #${numero} excluído.`);
        recarregarLancamentos();
    }
}

// ==========================================
// LÓGICA DA ABA EQUIPE (GESTÃO & SENHAS)
// ==========================================
async function recarregarEquipe() {
    const analistas = await AnalistasService.getAll();
    const tickets = await TicketsService.getAll();
    const user = AuthService.getCurrentUser();

    const stats = {};
    analistas.forEach(a => { stats[a.nome] = { total: 0, abertos: 0, concluidos: 0 }; });

    tickets.forEach(t => {
        if (!stats[t.analista_nome]) stats[t.analista_nome] = { total: 0, abertos: 0, concluidos: 0 };
        stats[t.analista_nome].total++;
        if (t.status === 'Concluído') stats[t.analista_nome].concluidos++;
        else stats[t.analista_nome].abertos++;
    });

    const tbody = document.getElementById('equipeTableBody');
    tbody.innerHTML = analistas.map(a => {
        const s = stats[a.nome] || { total: 0, abertos: 0, concluidos: 0 };
        const isVoce = user && user.id === a.id;
        const anJson = JSON.stringify(a).replace(/"/g, '&quot;');

        return `
            <tr>
                <td><div class="user-avatar" style="width: 38px; height: 38px; font-size: 0.85rem;">${a.avatar || 'DS'}</div></td>
                <td>
                    <strong>${a.nome}</strong>
                    ${isVoce ? '<span style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 10px; font-weight: 700; margin-left: 6px;">Você</span>' : ''}
                </td>
                <td><code>${a.email}</code></td>
                <td>${a.cargo || 'Analista'}</td>
                <td style="text-align: center; font-weight: 800; color: var(--dsoft-navy);">${s.total}</td>
                <td style="text-align: center; font-weight: 800; color: #0f172a;">${s.abertos}</td>
                <td style="text-align: center; font-weight: 800; color: #16a34a;">${s.concluidos}</td>
                <td style="text-align: right; white-space: nowrap;">
                    <button type="button" class="btn-action-primary" style="padding: 6px 12px; font-size: 0.8rem; background: #fff; color: var(--dsoft-navy); border: 1px solid #cbd5e1; box-shadow: none;"
                            onclick="abrirModalEditarAnalista(${anJson})">
                        <i class="bi bi-pencil-square" style="color: var(--dsoft-cyan);"></i> Editar / Senha
                    </button>
                    ${!isVoce ? `
                        <button type="button" class="btn-icon-action danger" onclick="handleExcluirAnalista('${a.id}', '${a.nome}')" title="Excluir Analista">
                            <i class="bi bi-trash3"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

async function handleSalvarTicket(e) {
    e.preventDefault();
    const novoTicket = {
        numero_ticket: document.getElementById('tckNumero').value,
        analista_nome: document.getElementById('tckAnalista').value,
        cliente_empresa: document.getElementById('tckCliente').value,
        data_hora: new Date(document.getElementById('tckData').value).toISOString(),
        tipo: document.getElementById('tckTipo').value,
        status: document.getElementById('tckStatus').value,
        prioridade: document.getElementById('tckPrioridade').value,
        descricao: document.getElementById('tckDescricao').value
    };

    await TicketsService.add(novoTicket);
    showToast(`Ticket #${novoTicket.numero_ticket} registrado com sucesso!`);
    fecharModalNovoTicket();
    document.getElementById('formNovoTicket').reset();

    if (currentView === 'dashboard') recarregarDashboard();
    else recarregarLancamentos();
}

async function handleSalvarAnalista(e) {
    e.preventDefault();
    const an = {
        nome: document.getElementById('anNome').value,
        email: document.getElementById('anEmail').value,
        cargo: document.getElementById('anCargo').value,
        senha: document.getElementById('anSenha').value
    };

    await AnalistasService.add(an);
    showToast(`Analista "${an.nome}" cadastrado com sucesso!`);
    fecharModalNovoAnalista();
    document.getElementById('formNovoAnalista').reset();
    await popularSelectsAnalistas();
    recarregarEquipe();
}

async function handleAtualizarAnalista(e) {
    e.preventDefault();
    const id = document.getElementById('editAnId').value;
    const dados = {
        nome: document.getElementById('editAnNome').value,
        email: document.getElementById('editAnEmail').value,
        cargo: document.getElementById('editAnCargo').value
    };
    const novaSenha = document.getElementById('editAnSenha').value;
    if (novaSenha) dados.senha = novaSenha;

    await AnalistasService.update(id, dados);

    // Se editou o usuário logado
    const user = AuthService.getCurrentUser();
    if (user && user.id === id) {
        AuthService.setCurrentUser({ ...user, nome: dados.nome, email: dados.email, cargo: dados.cargo });
        document.getElementById('headerUserName').innerText = dados.nome;
        document.getElementById('headerUserRole').innerText = dados.cargo;
    }

    showToast('Dados e senha atualizados com sucesso!');
    fecharModalEditarAnalista();
    await popularSelectsAnalistas();
    recarregarEquipe();
}

async function handleExcluirAnalista(id, nome) {
    if (confirm(`Deseja realmente remover o analista "${nome}" da equipe?`)) {
        await AnalistasService.delete(id);
        showToast(`Analista "${nome}" removido.`);
        recarregarEquipe();
    }
}

// ==========================================
// CONTROLE DE MODAIS
// ==========================================
function abrirModalNovoTicket() {
    document.getElementById('modalNovoTicket').classList.add('active');
    setTimeout(() => { document.getElementById('tckNumero').focus(); }, 100);
}
function fecharModalNovoTicket() { document.getElementById('modalNovoTicket').classList.remove('active'); }

function abrirModalNovoAnalista() { document.getElementById('modalNovoAnalista').classList.add('active'); }
function fecharModalNovoAnalista() { document.getElementById('modalNovoAnalista').classList.remove('active'); }

function abrirModalEditarAnalista(an) {
    document.getElementById('editAnId').value = an.id;
    document.getElementById('editAnNome').value = an.nome;
    document.getElementById('editAnEmail').value = an.email;
    document.getElementById('editAnCargo').value = an.cargo || 'Analista de Suporte';
    document.getElementById('editAnSenha').value = '';
    document.getElementById('modalEditarAnalista').classList.add('active');
}
function fecharModalEditarAnalista() { document.getElementById('modalEditarAnalista').classList.remove('active'); }

function abrirModalConfigSupabase() {
    document.getElementById('cfgSupabaseUrl').value = localStorage.getItem('dsoft_supabase_url') || '';
    document.getElementById('cfgSupabaseKey').value = localStorage.getItem('dsoft_supabase_anon_key') || '';
    document.getElementById('modalConfigSupabase').classList.add('active');
}
function fecharModalConfigSupabase() { document.getElementById('modalConfigSupabase').classList.remove('active'); }

function salvarConfigSupabase(e) {
    e.preventDefault();
    const url = document.getElementById('cfgSupabaseUrl').value.trim();
    const key = document.getElementById('cfgSupabaseKey').value.trim();

    localStorage.setItem('dsoft_supabase_url', url);
    localStorage.setItem('dsoft_supabase_anon_key', key);

    initSupabase();
    atualizarStatusConexaoSupabase();
    fecharModalConfigSupabase();
    showToast('Configurações do Supabase salvas!');
    recarregarDashboard();
}
