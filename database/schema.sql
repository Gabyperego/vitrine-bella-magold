-- ============================================================
-- SCHEMA DO BANCO DE DADOS SUPABASE (PostgreSQL)
-- Bella Magold Semijoias — Vitrine Online & Painel Administrativo
-- Arquitetura: SELLERS -> CASES -> CATEGORIES -> PRODUCTS -> SALES -> SALE_ITEMS -> CASE_CLOSINGS
-- ============================================================
-- Para executar:
-- 1. Acesse https://supabase.com/dashboard
-- 2. Selecione seu projeto
-- 3. Clique em "SQL Editor" no menu lateral
-- 4. Clique em "New Query", cole este script inteiro e clique em "Run".
-- ============================================================

-- Habilita extensão pgcrypto para UUIDs
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. TABELA DE VENDEDORAS (sellers)
CREATE TABLE IF NOT EXISTS public.sellers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nome TEXT NOT NULL,
    whatsapp TEXT NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT true,
    foto_url TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 2. TABELA DE ESTOJOS (cases)
CREATE TABLE IF NOT EXISTS public.cases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nome TEXT NOT NULL,
    descricao TEXT,
    seller_id UUID REFERENCES public.sellers(id) ON DELETE SET NULL,
    ativo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 3. TABELA DE CATEGORIAS (categories)
CREATE TABLE IF NOT EXISTS public.categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nome TEXT NOT NULL UNIQUE,
    ativo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 4. TABELA DE PRODUTOS (products)
CREATE TABLE IF NOT EXISTS public.products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    codigo TEXT UNIQUE,
    nome TEXT NOT NULL,
    descricao TEXT,
    categoria TEXT NOT NULL DEFAULT 'Outros',
    preco NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    case_id UUID REFERENCES public.cases(id) ON DELETE SET NULL,
    imagem_url TEXT,
    estoque INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'Disponível' CHECK (status IN ('Disponível', 'Indisponível', 'Vendido')),
    ativo BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 5. TABELA DE VENDAS / PEDIDOS (sales)
CREATE TABLE IF NOT EXISTS public.sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    numero_venda TEXT UNIQUE NOT NULL,
    cliente_nome TEXT NOT NULL,
    cliente_telefone TEXT,
    observacoes TEXT,
    seller_id UUID REFERENCES public.sellers(id) ON DELETE SET NULL,
    forma_pagamento TEXT NOT NULL DEFAULT 'WhatsApp / A Combinar',
    total NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    status TEXT NOT NULL DEFAULT 'Pendente' CHECK (status IN ('Pendente', 'Concluída', 'Cancelada')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 6. ITENS DA VENDA COM ORIGEM DE ESTOJO (sale_items)
CREATE TABLE IF NOT EXISTS public.sale_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sale_id UUID REFERENCES public.sales(id) ON DELETE CASCADE NOT NULL,
    product_id UUID REFERENCES public.products(id) ON DELETE SET NULL,
    case_id UUID REFERENCES public.cases(id) ON DELETE SET NULL,
    seller_id UUID REFERENCES public.sellers(id) ON DELETE SET NULL,
    produto_nome TEXT NOT NULL,
    preco_unitario NUMERIC(10, 2) NOT NULL,
    quantidade INTEGER NOT NULL DEFAULT 1,
    subtotal NUMERIC(10, 2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- 7. HISTÓRICO DE FECHAMENTO DE ESTOJOS (case_closings)
CREATE TABLE IF NOT EXISTS public.case_closings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    case_id UUID REFERENCES public.cases(id) ON DELETE CASCADE NOT NULL,
    seller_id UUID REFERENCES public.sellers(id) ON DELETE SET NULL,
    data_inicial TIMESTAMPTZ NOT NULL,
    data_final TIMESTAMPTZ NOT NULL,
    total_pecas_iniciais INTEGER NOT NULL DEFAULT 0,
    total_pecas_vendidas INTEGER NOT NULL DEFAULT 0,
    total_pecas_restantes INTEGER NOT NULL DEFAULT 0,
    valor_total_vendido NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    comissao_vendedora_pct NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    valor_comissao_vendedora NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    comissao_proprietaria_pct NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    valor_repasse NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    observacoes TEXT,
    status TEXT NOT NULL DEFAULT 'Fechado',
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- ============================================================
-- HABILITAR ROW LEVEL SECURITY (RLS)
-- ============================================================
ALTER TABLE public.sellers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sale_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.case_closings ENABLE ROW LEVEL SECURITY;

-- Políticas de Leitura Pública
CREATE POLICY "Leitura pública de vendedoras" ON public.sellers FOR SELECT TO anon, authenticated USING (ativo = true);
CREATE POLICY "Leitura pública de estojos" ON public.cases FOR SELECT TO anon, authenticated USING (ativo = true);
CREATE POLICY "Leitura pública de categorias" ON public.categories FOR SELECT TO anon, authenticated USING (ativo = true);
CREATE POLICY "Leitura pública de produtos" ON public.products FOR SELECT TO anon, authenticated USING (ativo = true);

-- Políticas de Inserção Pública (Checkout pelo WhatsApp)
CREATE POLICY "Inserção pública de vendas" ON public.sales FOR INSERT TO anon, authenticated WITH CHECK (true);
CREATE POLICY "Inserção pública de itens" ON public.sale_items FOR INSERT TO anon, authenticated WITH CHECK (true);

-- Acesso Total para Chaves de Aplicação / Autenticadas (Painel Admin)
CREATE POLICY "Acesso total sellers" ON public.sellers FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total cases" ON public.cases FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total categories" ON public.categories FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total products" ON public.products FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total sales" ON public.sales FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total sale_items" ON public.sale_items FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);
CREATE POLICY "Acesso total case_closings" ON public.case_closings FOR ALL TO anon, authenticated USING (true) WITH CHECK (true);

-- ============================================================
-- BUCKET DE STORAGE (PRODUTOS)
-- ============================================================
INSERT INTO storage.buckets (id, name, public) 
VALUES ('produtos', 'produtos', true)
ON CONFLICT (id) DO NOTHING;

CREATE POLICY "Visualização pública de fotos" ON storage.objects FOR SELECT TO public USING (bucket_id = 'produtos');
CREATE POLICY "Upload de fotos" ON storage.objects FOR INSERT TO anon, authenticated WITH CHECK (bucket_id = 'produtos');
CREATE POLICY "Edição de fotos" ON storage.objects FOR UPDATE TO anon, authenticated USING (bucket_id = 'produtos');
CREATE POLICY "Remoção de fotos" ON storage.objects FOR DELETE TO anon, authenticated USING (bucket_id = 'produtos');

-- ============================================================
-- DADOS INICIAIS (SEED DATA)
-- ============================================================

-- Vendedoras
INSERT INTO public.sellers (id, nome, whatsapp, ativo, foto_url) VALUES
('b1000000-0000-0000-0000-000000000001', 'Gaby', '5567999887766', true, 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80'),
('b2000000-0000-0000-0000-000000000002', 'Vanessa', '5567988776655', true, 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80')
ON CONFLICT (id) DO NOTHING;

-- Estojos vinculados às vendedoras
INSERT INTO public.cases (id, nome, descricao, seller_id, ativo) VALUES
('c1000000-0000-0000-0000-000000000001', 'Estojo Gaby', 'Estojo de semijoias finas sob responsabilidade da Gaby.', 'b1000000-0000-0000-0000-000000000001', true),
('c2000000-0000-0000-0000-000000000002', 'Estojo Vanessa', 'Estojo de lançamentos e colares de luxo sob responsabilidade da Vanessa.', 'b2000000-0000-0000-0000-000000000002', true),
('c3000000-0000-0000-0000-000000000003', 'Estojo Mostruário Loja', 'Peças de pronta entrega do mostruário físico da boutique.', 'b1000000-0000-0000-0000-000000000001', true)
ON CONFLICT (id) DO NOTHING;

-- Categorias
INSERT INTO public.categories (id, nome, ativo) VALUES
('d1000000-0000-0000-0000-000000000001', 'Brincos', true),
('d2000000-0000-0000-0000-000000000002', 'Colares', true),
('d3000000-0000-0000-0000-000000000003', 'Pulseiras', true),
('d4000000-0000-0000-0000-000000000004', 'Anéis', true),
('d5000000-0000-0000-0000-000000000005', 'Pingentes', true),
('d6000000-0000-0000-0000-000000000006', 'Outros', true)
ON CONFLICT (id) DO NOTHING;

-- Produtos vinculados aos estojos
INSERT INTO public.products (id, codigo, nome, descricao, categoria, preco, case_id, imagem_url, estoque, status, ativo) VALUES
('e1000000-0000-0000-0000-000000000001', 'BR-001', 'Brinco Dourado Argola Elegance', 'Brinco argola clássica banhada a ouro 18k com acabamento polido de alto brilho.', 'Brincos', 35.00, 'c1000000-0000-0000-0000-000000000001', 'https://images.unsplash.com/photo-1630019852942-f89202989a59?w=600&auto=format&fit=crop&q=80', 5, 'Disponível', true),
('e2000000-0000-0000-0000-000000000002', 'COL-002', 'Colar Coração Zircônia Cristal', 'Colar delicado com pingente de coração em zircônia cravejada, banhado a ouro 18k.', 'Colares', 89.00, 'c2000000-0000-0000-0000-000000000002', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80', 3, 'Disponível', true),
('e3000000-0000-0000-0000-000000000003', 'PUL-003', 'Pulseira Dourada Elos Cartier', 'Pulseira feminina com elos estilo Cartier e fecho boia seguro e refinado.', 'Pulseiras', 45.00, 'c1000000-0000-0000-0000-000000000001', 'https://images.unsplash.com/photo-1611591475883-9b932bb8269e?w=600&auto=format&fit=crop&q=80', 8, 'Disponível', true),
('e4000000-0000-0000-0000-000000000004', 'ANE-004', 'Anel Solitário Cristal Luxo', 'Anel solitário elegante com pedra central brilhante lapidação navete.', 'Anéis', 69.90, 'c2000000-0000-0000-0000-000000000002', 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&auto=format&fit=crop&q=80', 4, 'Disponível', true),
('e5000000-0000-0000-0000-000000000005', 'PIN-005', 'Pingente Estrela Radiante', 'Pingente delicado em formato de estrela com micropavê de zircônias.', 'Pingentes', 39.00, 'c3000000-0000-0000-0000-000000000003', 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80', 6, 'Disponível', true)
ON CONFLICT (id) DO NOTHING;
