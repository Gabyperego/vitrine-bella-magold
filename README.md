# 💎 Vitrine Elegance — Loja Online com Supabase & WhatsApp
# 💎 Vitrine Elegance — Sistema de Vitrine Online & Gestão de Estojos

Sistema completo de Vitrine Virtual de Produtos e Painel Administrativo de Vendas, desenvolvido em **PHP**, **HTML5**, **CSS3**, **JavaScript**, **Supabase (PostgreSQL, Storage e Auth)** e integrado diretamente com o **WhatsApp** para roteamento de pedidos por vendedora.
Sistema web profissional e completo desenvolvido em **PHP**, **HTML5**, **CSS3**, **JavaScript** e **Supabase (PostgreSQL, Storage e Auth)**, integrado com **WhatsApp** para atendimento e fechamento de pedidos por vendedora.

---

## 🚀 Funcionalidades Principais
## 🏛️ Arquitetura e Relacionamentos

### 1. Vitrine Pública
- **Design Elegante & Responsivo**: Adaptável para computadores, tablets e smartphones com navegação touch-friendly.
- **Catálogo de Peças**: Cards ricos com foto, nome, código, descrição, categoria, preço formatado em Real (`R$`) e badge de status (*Disponível*, *Indisponível*, *Vendido*).
- **Pesquisa Instantânea**: Barra de busca com ícone de lupa filtrando em tempo real por nome, código ou descrição.
- **Filtros por Categoria**: Navegação rápida em pílulas (*Todos, Brincos, Colares, Pulseiras, Anéis, Pingentes, Outros*).
- **Carrinho Dinâmico**:
  - Adição de múltiplos produtos com controle de quantidade (+ / -) e remoção.
  - Não exige cadastro de conta prévio para o cliente.
  - Solicitação de Nome (obrigatório), Telefone (opcional com máscara) e Observações (opcional).
  - **Seleção Dinâmica de Vendedoras**: Carrega do banco de dados apenas as profissionais ativas (ex: *Gaby*, *Vanessa*).
  - **Finalização pelo WhatsApp**: Cria o pedido no banco de dados e abre automaticamente o WhatsApp da vendedora com a mensagem perfeitamente formatada e codificada via URL Encode:
    ```
    Olá, Gaby! Tenho interesse nestas peças:
    • Brinco Dourado Argola Elegance — 1x — R$ 35,00
    • Colar Coração Zircônia Cristal — 1x — R$ 89,00
    Total: R$ 124,00
    Cliente: Maria
    Telefone: (67) 99999-9999
    Observação: Gostaria de verificar disponibilidade para entrega.
    Pedido Ref: #PED-260917-ABCD
    ```
O sistema é fundamentado no modelo hierárquico:

$$\textbf{Vendedora (sellers)} \longrightarrow \textbf{Estojo (cases)} \longrightarrow \textbf{Produtos (products)}$$

- **Vendedora (`sellers`)**: Pode possuir um ou mais estojos sob sua custódia e número exclusivo de WhatsApp.
- **Estojo (`cases`)**: Pertence a uma vendedora responsável e agrega múltiplos produtos físicos.
- **Produtos (`products`)**: Cada peça é cadastrada e vinculada obrigatoriamente a um estojo (`case_id`).
- **Vendas (`sales`) & Itens (`sale_items`)**: Registram a venda, o cliente, o produto e mantêm a rastreabilidade do **estojo de origem** e da **vendedora** para apuração de comissões e fechamento.

---

### 2. Painel de Gestão (`/admin`)
- **Autenticação Segura**: Integrado ao Supabase Auth (E-mail e Senha) com bloqueio automático de rotas não autorizadas.
- **Dashboard com Métricas em Tempo Real**:
  - Total de produtos cadastrados, produtos disponíveis e vendidos/indisponíveis.
  - Total de pedidos, faturamento acumulado e vendas do mês atual.
  - **Desempenho por Vendedora**: Quantidade de vendas e valor faturado por cada vendedora.
  - Tabela de **Vendas Recentes** com detalhes e status.
- **Cadastro e Gestão de Produtos (`/admin/produtos.php`)**:
  - Cadastro, edição e exclusão de produtos.
  - Upload de imagens integrado diretamente ao **Supabase Storage** (bucket `produtos`).
  - Ações rápidas de 1 clique: *"Marcar como Vendido"* e *"Marcar como Disponível"*.
  - Alteração de preço, categoria, descrição e quantidade em estoque.
- **Cadastro de Vendedoras (`/admin/vendedoras.php`)**:
  - Cadastro de Nome, WhatsApp (com sanitização e link `55...`), Foto e Status (Ativo/Inativo).
  - Controle de disponibilidade: Somente vendedoras ativas aparecem no carrinho da vitrine.
  - Botão de teste de WhatsApp direto pelo painel.
- **Gestão de Vendas (`/admin/vendas.php`)**:
  - Listagem completa de pedidos recebidos da vitrine.
  - Visualização detalhada de cada pedido (cliente, itens solicitados, subtotal e observações).
  - Atualização de status do pedido (*Pendente*, *Concluído*, *Cancelado*).
  - Registro manual de vendas balcão/diretas.
## 📋 Menu Administrativo

O painel de controle administrativo (`/admin`) possui o menu:

$$\textbf{Dashboard} \mid \textbf{Produtos} \mid \textbf{Vendas} \mid \textbf{Vendedoras} \mid \textbf{Estojos} \mid \textbf{Categorias} \mid \textbf{Relatórios} \mid \textbf{Sair}$$

### Módulos do Sistema:
1. **Dashboard (`/admin/index.php`)**:
   - **Vendas gerais**: Valor total faturado e vendas do mês.
   - **Indicadores por Vendedora**: Valor vendido pela **Gaby**, valor vendido pela **Vanessa** e outras vendedoras.
   - **Vendas por Estojo**: Faturamento do *Estojo Gaby*, *Estojo Vanessa*, etc.
   - **Controle de Peças por Estojo**: Total de produtos cadastrados, quantidade vendida e saldo de estoque restante por estojo.
   - **Vendas Recentes**: Últimos pedidos recebidos da vitrine.

2. **Produtos (`/admin/produtos.php` e `/admin/produto-novo.php`)**:
   - Tela dedicada de cadastro (`/admin/produto-novo.php`) com seleção **obrigatória** de Estojo carregada do Supabase.
   - Listagem com as colunas: `Código | Produto | Categoria | Estojo | Vendedora | Preço | Estoque | Status | Ações`.
   - Filtros múltiplos: *Produto* (busca por nome/código), *Categoria*, *Estojo*, *Vendedora* e *Status*.
   - Upload de imagens integrado diretamente ao **Supabase Storage** (bucket `produtos`).
   - Ações rápidas de 1 clique: *"Marcar como Vendido"* e *"Marcar como Disponível"*.

3. **Estojos (`/admin/estojos.php`)**:
   - Cadastrar, editar e excluir/desativar estojos.
   - Vincular a uma vendedora responsável.
   - Consultar quantidade de peças, peças vendidas e valor total vendido pelo estojo.
   - Modal com listagem completa de produtos pertencentes ao estojo.

4. **Vendedoras (`/admin/vendedoras.php`)**:
   - Cadastro de Nome, WhatsApp (com sanitização para link internacional `55...`), Foto e Status (Ativo/Inativo).
   - Somente vendedoras ativas aparecem para o cliente selecionar no carrinho da vitrine.
   - Botão para testar o chat no WhatsApp com 1 clique.

5. **Categorias (`/admin/categorias.php`)**:
   - Gestão de categorias (Brincos, Colares, Pulseiras, Anéis, Pingentes, Outros) e contagem de produtos vinculados.

6. **Vendas (`/admin/vendas.php`)**:
   - Rastreabilidade completa de: *Venda*, *Cliente*, *Produto*, *Estojo de Origem*, *Vendedora*, *Quantidade*, *Valor*, *Forma de Pagamento*, *Data* e *Status*.
   - Filtros por: *Período*, *Vendedora*, *Estojo*, *Produto* e *Forma de Pagamento*.
   - Modal detalhado com histórico de itens e atualização de status (*Pendente*, *Concluída*, *Cancelada*).
   - Registro de vendas balcão manuais.

7. **Fechamento de Estojo & Relatórios (`/admin/relatorios.php`)**:
   - Seleção de: *Estojo*, *Data Inicial*, *Data Final* e *% de Comissão da Vendedora*.
   - Relatório detalhado contendo:
     - Produtos vendidos no período com quantidade e valores.
     - Produtos restantes no estojo (saldo físico atual).
     - Total vendido do fechamento.
     - Cálculo configurável de **Comissão da Vendedora** e **Valor Líquido de Repasse à Proprietária**.
   - Gravação e consulta do **Histórico de Fechamentos Anteriores** (`case_closings`).
   - Botão de impressão / PDF.

---

## 🛠️ Como Configurar no Supabase
## 🛍️ Vitrine Pública & Integração WhatsApp

1. Crie uma conta ou acesse o seu projeto no [Supabase](https://supabase.com/).
2. Vá em **SQL Editor** -> **New Query**.
3. Copie todo o conteúdo do arquivo [`database/schema.sql`](database/schema.sql) e clique em **Run**.
   - Isso criará as tabelas `produtos`, `vendedoras`, `pedidos`, `pedido_itens`, as políticas RLS, o bucket de Storage `produtos` e dados de exemplo iniciais.
4. Vá em **Project Settings** -> **API**:
   - Copie a **Project URL** (ex: `https://xyzcompany.supabase.co`).
   - Copie a chave **anon / public** (ex: `eyJhbGci...`).
5. Abra o arquivo [`includes/config.php`](includes/config.php) e preencha:
   ```php
   define('SUPABASE_URL', 'https://SEU-PROJETO.supabase.co');
   define('SUPABASE_ANON_KEY', 'SUA_ANON_KEY_AQUI');
- A vitrine pública (`index.php`) carrega os produtos do Supabase via backend PHP.
- Regra de exibição rigorosa: Somente apresenta peças que estejam **Ativas**, **Disponíveis**, com **Estoque maior que zero** e pertencentes a um **Estojo Ativo**.
- O cliente pode adicionar peças de diferentes estojos ao carrinho sem necessidade de cadastro.
- Na finalização, escolhe a vendedora desejada (ex: **Gaby** ou **Vanessa**).
- O backend PHP grava o pedido no banco de dados e abre o WhatsApp com a mensagem formatada e codificada via `rawurlencode()`:
  ```
  Olá, Vanessa! Tenho interesse nestas peças:

  • Brinco Dourado — 1x — R$ 35,00
  • Colar Dourado — 1x — R$ 89,00

  Total: R$ 124,00

  Cliente: Maria

  Observação: Gostaria de verificar a disponibilidade.
  ```

---

## ⚙️ Configuração no Supabase

1. Crie seu projeto em [https://supabase.com](https://supabase.com).
2. Abra o **SQL Editor** no painel do Supabase, copie todo o código do arquivo [`database/schema.sql`](database/schema.sql) e clique em **Run**.
3. Em **Project Settings ➔ API**, copie:
   - **Project URL**
   - **anon / public key**
   - **service_role key** (usada estritamente no servidor PHP para operações administrativas seguras)
4. Abra o arquivo `.env` na raiz do projeto e insira as credenciais:
   ```env
   SUPABASE_URL=https://SEU-PROJETO.supabase.co
   SUPABASE_ANON_KEY=SUA_ANON_KEY_AQUI
   SUPABASE_SERVICE_ROLE_KEY=SUA_SERVICE_ROLE_KEY_AQUI
   SUPABASE_STORAGE_BUCKET=produtos
   ```
6. No Supabase, vá em **Authentication** -> **Users** e crie o usuário do administrador:
   - E-mail: `admin@seudominio.com`
   - Senha: `sua-senha-segura`
5. No Supabase, acesse **Authentication ➔ Users** e cadastre seu usuário administrador.

> **Nota:** O sistema possui um modo inteligente de demonstração / local (`local_db.json`). Caso as credenciais do Supabase ainda não tenham sido inseridas, o sistema funciona perfeitamente com dados de teste para visualização imediata!
> No modo de demonstração, use o login:
> - **E-mail:** `admin@admin.com`
> - **Senha:** `admin123`
> **💡 Modo Demonstração / Local Integrado:**  
> Caso o projeto ainda não esteja conectado ao Supabase, o sistema funciona imediatamente através de armazenamento local simulado (`data/local_db.json`) contendo vendedoras (Gaby, Vanessa), estojos e semijoias para testes de ponta a ponta!  
> **Login Demo:** `admin@admin.com` | **Senha:** `admin123`

---

## 💻 Como Rodar o Sistema Localmente
## 🚀 Como Executar Localmente

Com o PHP instalado no seu computador, abra o terminal nesta pasta e execute o servidor embutido do PHP:
No terminal, acesse a pasta e inicie o servidor PHP embutido:

```bash
```powershell
cd C:\Users\Comercial\.gemini\antigravity\scratch\vitrine-loja
php -S localhost:8000
```

Em seguida acesse:
- **Vitrine da Loja:** [http://localhost:8000](http://localhost:8000)
Abra no navegador:
- **Vitrine:** [http://localhost:8000](http://localhost:8000)
- **Painel Administrativo:** [http://localhost:8000/admin](http://localhost:8000/admin)

Ou utilize seu ambiente Apache / XAMPP / Laragon apontando o DocumentRoot ou copiando esta pasta para o diretório `htdocs` / `www`.

