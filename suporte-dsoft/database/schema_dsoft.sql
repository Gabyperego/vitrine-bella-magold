-- ============================================================
-- SCHEMA SUPABASE (PostgreSQL) — DSOFT SUPORTE
-- Central de Chamados & Agenda da Equipe de Suporte
-- ============================================================
-- Instruções de execução:
-- 1. Acesse https://supabase.com/dashboard
-- 2. Selecione seu projeto
-- 3. Clique em "SQL Editor" no menu lateral esquerdo
-- 4. Clique em "New Query", cole este script completo e clique no botão verde "Run"
-- ============================================================

-- Habilita extensão para geração de UUIDs
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. TABELA DE ANALISTAS / USUÁRIOS DO SUPORTE
CREATE TABLE IF NOT EXISTS public.analistas (
    id TEXT PRIMARY KEY DEFAULT ('usr_' || substr(md5(random()::text), 1, 10)),
    nome TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    senha_hash TEXT NOT NULL,
    cargo TEXT NOT NULL DEFAULT 'Analista de Suporte',
    avatar TEXT DEFAULT 'DS',
    ativo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 2. TABELA DE TICKETS / ATENDIMENTOS DA EQUIPE
CREATE TABLE IF NOT EXISTS public.tickets (
    id TEXT PRIMARY KEY DEFAULT ('tck_' || substr(md5(random()::text), 1, 10)),
    numero_ticket TEXT NOT NULL,
    analista_nome TEXT NOT NULL,
    cliente_empresa TEXT NOT NULL,
    data_hora TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    tipo TEXT NOT NULL DEFAULT 'Bug' CHECK (tipo IN ('Bug', 'Melhoria', 'Dúvida', 'Configuração', 'Treinamento', 'Outro')),
    status TEXT NOT NULL DEFAULT 'Em Aberto' CHECK (status IN ('Em Aberto', 'Concluído')),
    prioridade TEXT NOT NULL DEFAULT 'Média' CHECK (prioridade IN ('Baixa', 'Média', 'Alta', 'Crítica')),
    descricao TEXT NOT NULL,
    concluido_em TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- HABILITAR ROW LEVEL SECURITY (RLS)
ALTER TABLE public.analistas ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tickets ENABLE ROW LEVEL SECURITY;

-- POLÍTICAS DE ACESSO TOTAL PARA ANON E AUTHENTICATED (COMPARTILHADO PELA EQUIPE)
CREATE POLICY "Acesso total analistas" ON public.analistas FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total tickets" ON public.tickets FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);

-- ============================================================
-- DADOS INICIAIS (SEED DATA)
-- ============================================================

-- Inserir Analistas Padrão (Senha padrão: 123456)
INSERT INTO public.analistas (id, nome, email, senha_hash, cargo, avatar, ativo) VALUES
('usr_001', 'Gaby Perego', 'gaby@dsoft.com.br', '$2y$10$wT3Wf6g6fK2y6aC6bE6dNeN6bZ6c7e8f9a0b1c2d3e4f5g6h7i8j', 'Suporte N2 / Analista', 'GP', true),
('usr_002', 'Lucas Silva', 'lucas@dsoft.com.br', '$2y$10$wT3Wf6g6fK2y6aC6bE6dNeN6bZ6c7e8f9a0b1c2d3e4f5g6h7i8j', 'Analista de Suporte', 'LS', true),
('usr_003', 'Fernanda Costa', 'fernanda@dsoft.com.br', '$2y$10$wT3Wf6g6fK2y6aC6bE6dNeN6bZ6c7e8f9a0b1c2d3e4f5g6h7i8j', 'Analista de Atendimento', 'FC', true)
ON CONFLICT (email) DO NOTHING;

-- Inserir Chamados de Exemplo
INSERT INTO public.tickets (id, numero_ticket, analista_nome, cliente_empresa, data_hora, tipo, status, prioridade, descricao, concluido_em) VALUES
('tck_1001', '10452', 'Gaby Perego', 'Supermercado Central', timezone('utc'::text, now()), 'Bug', 'Em Aberto', 'Alta', 'Erro ao emitir NFC-e em contingência offline. Rejeição 539: Duplicidade com diferença de chave.', NULL),
('tck_1002', '10451', 'Lucas Silva', 'Drogaria Boa Saúde', timezone('utc'::text, now()), 'Melhoria', 'Concluído', 'Média', 'Solicitada inclusão de atalho rápido no PDV para consulta de estoque entre filiais.', timezone('utc'::text, now())),
('tck_1003', '10448', 'Fernanda Costa', 'Auto Posto Alvorada', timezone('utc'::text, now() - interval '1 day'), 'Dúvida', 'Concluído', 'Baixa', 'Orientação sobre fechamento de caixa diário e conferência de recebíveis em cartão.', timezone('utc'::text, now() - interval '1 day')),
('tck_1004', '10445', 'Gaby Perego', 'Distribuidora Pantanal', timezone('utc'::text, now() - interval '2 days'), 'Melhoria', 'Em Aberto', 'Média', 'Ajuste no layout do relatório financeiro de DRE por centro de custo.', NULL)
ON CONFLICT (id) DO NOTHING;
