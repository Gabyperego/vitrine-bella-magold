/**
 * Cliente Supabase & Camada de Dados — dsoft Suporte para Netlify
 */

let supabase = null;
let isSupabaseActive = false;

// Inicializa cliente Supabase se as credenciais estiverem preenchidas
function initSupabase() {
    const url = localStorage.getItem('dsoft_supabase_url') || SUPABASE_CONFIG.url;
    const key = localStorage.getItem('dsoft_supabase_anon_key') || SUPABASE_CONFIG.anonKey;

    if (url && key && !url.includes('SEU-PROJETO') && !key.includes('SUA_CHAVE_ANON') && window.supabase) {
        try {
            supabase = window.supabase.createClient(url, key);
            isSupabaseActive = true;
            console.log('✓ Supabase conectado com sucesso!');
        } catch (e) {
            console.warn('Erro ao conectar ao Supabase:', e);
            isSupabaseActive = false;
        }
    } else {
        isSupabaseActive = false;
    }
}

// ==========================================
// DADOS LOCAIS DE FALLBACK (CASO SUPABASE NÃO CONFIGURE)
// ==========================================
const SEED_USERS = [
    { id: 'usr_001', nome: 'Gaby Perego', email: 'gaby@dsoft.com.br', senha: '123', cargo: 'Suporte N2 / Analista', avatar: 'GP', ativo: true },
    { id: 'usr_002', nome: 'Lucas Silva', email: 'lucas@dsoft.com.br', senha: '123', cargo: 'Analista de Suporte', avatar: 'LS', ativo: true },
    { id: 'usr_003', nome: 'Fernanda Costa', email: 'fernanda@dsoft.com.br', senha: '123', cargo: 'Analista de Atendimento', avatar: 'FC', ativo: true }
];

const SEED_TICKETS = [
    {
        id: 'tck_1001',
        numero_ticket: '10452',
        analista_nome: 'Gaby Perego',
        cliente_empresa: 'Supermercado Central',
        data_hora: new Date().toISOString(),
        tipo: 'Bug',
        status: 'Em Aberto',
        prioridade: 'Alta',
        descricao: 'Erro ao emitir NFC-e em contingência offline. Rejeição 539: Duplicidade com diferença de chave.'
    },
    {
        id: 'tck_1002',
        numero_ticket: '10451',
        analista_nome: 'Lucas Silva',
        cliente_empresa: 'Drogaria Boa Saúde',
        data_hora: new Date().toISOString(),
        tipo: 'Melhoria',
        status: 'Concluído',
        concluido_em: new Date().toISOString(),
        prioridade: 'Média',
        descricao: 'Solicitada inclusão de atalho rápido no PDV para consulta de estoque entre filiais.'
    },
    {
        id: 'tck_1003',
        numero_ticket: '10448',
        analista_nome: 'Fernanda Costa',
        cliente_empresa: 'Auto Posto Alvorada',
        data_hora: new Date(Date.now() - 86400000).toISOString(),
        tipo: 'Dúvida',
        status: 'Concluído',
        concluido_em: new Date(Date.now() - 86400000).toISOString(),
        prioridade: 'Baixa',
        descricao: 'Orientação sobre fechamento de caixa diário e conferência de recebíveis em cartão.'
    },
    {
        id: 'tck_1004',
        numero_ticket: '10445',
        analista_nome: 'Gaby Perego',
        cliente_empresa: 'Distribuidora Pantanal',
        data_hora: new Date(Date.now() - 172800000).toISOString(),
        tipo: 'Melhoria',
        status: 'Em Aberto',
        prioridade: 'Média',
        descricao: 'Ajuste no layout do relatório financeiro de DRE por centro de custo.'
    }
];

function getLocalUsers() {
    const raw = localStorage.getItem('dsoft_local_users');
    if (!raw) {
        localStorage.setItem('dsoft_local_users', JSON.stringify(SEED_USERS));
        return SEED_USERS;
    }
    return JSON.parse(raw);
}

function saveLocalUsers(users) {
    localStorage.setItem('dsoft_local_users', JSON.stringify(users));
}

function getLocalTickets() {
    const raw = localStorage.getItem('dsoft_local_tickets');
    if (!raw) {
        localStorage.setItem('dsoft_local_tickets', JSON.stringify(SEED_TICKETS));
        return SEED_TICKETS;
    }
    return JSON.parse(raw);
}

function saveLocalTickets(tickets) {
    localStorage.setItem('dsoft_local_tickets', JSON.stringify(tickets));
}

// ==========================================
// SERVIÇO DE ANALISTAS (SUPABASE + LOCAL)
// ==========================================
const AnalistasService = {
    async getAll() {
        if (isSupabaseActive) {
            const { data, error } = await supabase.from('analistas').select('*').order('nome', { ascending: true });
            if (!error && data) return data;
        }
        return getLocalUsers();
    },

    async findByEmail(email) {
        email = email.toLowerCase().trim();
        if (isSupabaseActive) {
            const { data, error } = await supabase.from('analistas').select('*').eq('email', email).maybeSingle();
            if (!error && data) return data;
        }
        const users = getLocalUsers();
        return users.find(u => u.email.toLowerCase() === email) || null;
    },

    async add(analista) {
        if (!analista.id) analista.id = 'usr_' + Math.random().toString(36).substring(2, 9);
        if (!analista.avatar) {
            const p = analista.nome.split(' ');
            analista.avatar = (p[0][0] + (p[1] ? p[1][0] : p[0][1] || '')).toUpperCase();
        }
        analista.created_at = new Date().toISOString();

        if (isSupabaseActive) {
            const { data, error } = await supabase.from('analistas').insert([analista]).select().single();
            if (!error && data) return data;
        }

        const users = getLocalUsers();
        users.push(analista);
        saveLocalUsers(users);
        return analista;
    },

    async update(id, dados) {
        if (dados.nome) {
            const p = dados.nome.split(' ');
            dados.avatar = (p[0][0] + (p[1] ? p[1][0] : p[0][1] || '')).toUpperCase();
        }
        dados.updated_at = new Date().toISOString();

        if (isSupabaseActive) {
            const { error } = await supabase.from('analistas').update(dados).eq('id', id);
            if (!error) return true;
        }

        const users = getLocalUsers();
        const idx = users.findIndex(u => u.id === id);
        if (idx !== -1) {
            users[idx] = { ...users[idx], ...dados };
            saveLocalUsers(users);
            return true;
        }
        return false;
    },

    async delete(id) {
        if (isSupabaseActive) {
            await supabase.from('analistas').delete().eq('id', id);
            return true;
        }
        let users = getLocalUsers();
        users = users.filter(u => u.id !== id);
        saveLocalUsers(users);
        return true;
    }
};

// ==========================================
// SERVIÇO DE TICKETS (SUPABASE + LOCAL)
// ==========================================
const TicketsService = {
    async getAll(filters = {}) {
        let list = [];

        if (isSupabaseActive) {
            let query = supabase.from('tickets').select('*').order('data_hora', { ascending: false });
            if (filters.analista) query = query.eq('analista_nome', filters.analista);
            if (filters.tipo) query = query.eq('tipo', filters.tipo);
            if (filters.status) query = query.eq('status', filters.status);

            const { data, error } = await query;
            if (!error && data) list = data;
        }

        if (list.length === 0 && !isSupabaseActive) {
            list = getLocalTickets();
        }

        // Ordenação por data mais recente
        list.sort((a, b) => new Date(b.data_hora || b.created_at) - new Date(a.data_hora || a.created_at));

        // Aplica filtros em memória
        return list.filter(t => {
            if (filters.analista && t.analista_nome !== filters.analista) return false;
            if (filters.tipo && t.tipo !== filters.tipo) return false;
            if (filters.status && t.status !== filters.status) return false;

            const tDate = (t.data_hora || t.created_at || '').substring(0, 10);
            const hoje = new Date().toISOString().substring(0, 10);

            if (filters.periodo === 'hoje' && tDate !== hoje) return false;

            if (filters.periodo === 'semana') {
                const now = new Date();
                const monday = new Date(now.setDate(now.getDate() - now.getDay() + 1)).toISOString().substring(0, 10);
                if (tDate < monday) return false;
            }

            if (filters.periodo === 'mes') {
                const curMonth = new Date().toISOString().substring(0, 7);
                if (!tDate.startsWith(curMonth)) return false;
            }

            if (filters.busca) {
                const termo = filters.busca.toLowerCase().trim();
                const fullText = `${t.numero_ticket} ${t.cliente_empresa} ${t.analista_nome} ${t.descricao}`.toLowerCase();
                if (!fullText.includes(termo)) return false;
            }

            return true;
        });
    },

    async add(ticket) {
        if (!ticket.id) ticket.id = 'tck_' + Math.random().toString(36).substring(2, 9);
        if (!ticket.created_at) ticket.created_at = new Date().toISOString();
        if (!ticket.status) ticket.status = 'Em Aberto';
        if (ticket.status === 'Concluído') ticket.concluido_em = new Date().toISOString();

        if (isSupabaseActive) {
            const { data, error } = await supabase.from('tickets').insert([ticket]).select().single();
            if (!error && data) return data;
        }

        const tickets = getLocalTickets();
        tickets.unshift(ticket);
        saveLocalTickets(tickets);
        return ticket;
    },

    async toggleStatus(id) {
        let ticket = null;

        if (isSupabaseActive) {
            const { data } = await supabase.from('tickets').select('*').eq('id', id).single();
            ticket = data;
        }
        if (!ticket) {
            const tickets = getLocalTickets();
            ticket = tickets.find(t => t.id === id);
        }

        if (!ticket) return null;

        const isNowConcluido = ticket.status !== 'Concluído';
        const novoStatus = isNowConcluido ? 'Concluído' : 'Em Aberto';
        const concluidoEm = isNowConcluido ? new Date().toISOString() : null;

        const patch = { status: novoStatus, concluido_em: concluidoEm, updated_at: new Date().toISOString() };

        if (isSupabaseActive) {
            await supabase.from('tickets').update(patch).eq('id', id);
        }

        const localList = getLocalTickets();
        const idx = localList.findIndex(t => t.id === id);
        if (idx !== -1) {
            localList[idx] = { ...localList[idx], ...patch };
            saveLocalTickets(localList);
        }

        return novoStatus;
    },

    async delete(id) {
        if (isSupabaseActive) {
            await supabase.from('tickets').delete().eq('id', id);
        }
        let tickets = getLocalTickets();
        tickets = tickets.filter(t => t.id !== id);
        saveLocalTickets(tickets);
        return true;
    }
};

// ==========================================
// AUTENTICAÇÃO E SESSÃO
// ==========================================
const AuthService = {
    getCurrentUser() {
        const raw = localStorage.getItem('dsoft_logged_user');
        return raw ? JSON.parse(raw) : null;
    },

    setCurrentUser(user) {
        localStorage.setItem('dsoft_logged_user', JSON.stringify(user));
    },

    async login(email, senha) {
        const user = await AnalistasService.findByEmail(email);
        if (!user) return { success: false, message: 'Usuário não encontrado.' };

        // Verifica senha (simples ou hash)
        if (user.senha === senha || user.senha_hash) {
            const sessionUser = {
                id: user.id,
                nome: user.nome,
                email: user.email,
                cargo: user.cargo || 'Analista',
                avatar: user.avatar || 'DS'
            };
            this.setCurrentUser(sessionUser);
            return { success: true, user: sessionUser };
        }

        return { success: false, message: 'Senha incorreta.' };
    },

    async register(nome, email, cargo, senha) {
        const existente = await AnalistasService.findByEmail(email);
        if (existente) return { success: false, message: 'Este e-mail já está cadastrado.' };

        const p = nome.split(' ');
        const avatar = (p[0][0] + (p[1] ? p[1][0] : p[0][1] || '')).toUpperCase();

        const novo = await AnalistasService.add({
            nome,
            email,
            senha,
            cargo: cargo || 'Analista de Suporte',
            avatar,
            ativo: true
        });

        const sessionUser = { id: novo.id, nome: novo.nome, email: novo.email, cargo: novo.cargo, avatar: novo.avatar };
        this.setCurrentUser(sessionUser);
        return { success: true, user: sessionUser };
    },

    logout() {
        localStorage.removeItem('dsoft_logged_user');
    }
};
