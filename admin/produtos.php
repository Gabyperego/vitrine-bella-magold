<?php
/**
 * Gestão de Produtos (Listagem com Estojo, Vendedora e Filtros Múltiplos)
 */

$pageTitle = 'Gestão de Produtos';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// ============================================================
// PROCESSAMENTO DE AÇÕES RÁPIDAS (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. AÇÃO RÁPIDA: ALTERAR STATUS
    if ($acao === 'alterar_status') {
        $id = $_POST['id'] ?? null;
        $novoStatus = $_POST['status'] ?? 'Disponível';
        if ($id && in_array($novoStatus, ['Disponível', 'Indisponível', 'Vendido'])) {
            $supabase->update('products', ['status' => $novoStatus], ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Status do produto atualizado para "' . $novoStatus . '".');
        }
        header('Location: produtos.php');
        exit;
    }

    // 2. EXCLUIR PRODUTO
    if ($acao === 'excluir') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $supabase->delete('products', ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Produto removido com sucesso.');
        }
        header('Location: produtos.php');
        exit;
    }
}

// Carrega Dados Relacionados (Sellers, Cases, Categories)
$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$estojos = $supabase->select('cases', '*', [], 'nome.asc');
$categoriasDb = $supabase->select('categories', '*', ['ativo' => 'eq.true'], 'nome.asc');
$listaCategorias = !empty($categoriasDb) ? array_column($categoriasDb, 'nome') : ['Brincos', 'Colares', 'Pulseiras', 'Anéis', 'Pingentes', 'Outros'];

// Mapas por ID
$mapVendedoras = [];
foreach ($vendedoras as $v) {
    $mapVendedoras[$v['id']] = $v;
}

$mapEstojos = [];
foreach ($estojos as $e) {
    $mapEstojos[$e['id']] = $e;
}

// Filtros
$filtroProduto = trim($_GET['produto'] ?? '');
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroEstojo = $_GET['estojo_id'] ?? '';
$filtroVendedora = $_GET['vendedora_id'] ?? '';
$filtroStatus = $_GET['status'] ?? '';

$queryFilters = [];
if (!empty($filtroCategoria)) {
    $queryFilters['categoria'] = 'eq.' . $filtroCategoria;
}
if (!empty($filtroEstojo)) {
    $queryFilters['case_id'] = 'eq.' . $filtroEstojo;
}
if (!empty($filtroStatus)) {
    $queryFilters['status'] = 'eq.' . $filtroStatus;
}

$produtos = $supabase->select('products', '*', $queryFilters, 'created_at.desc');

// Filtro por Vendedora (via Estojo -> seller_id)
if (!empty($filtroVendedora)) {
    $produtos = array_values(array_filter($produtos, function($p) use ($filtroVendedora, $mapEstojos) {
        $caseId = $p['case_id'] ?? null;
        if (!$caseId || !isset($mapEstojos[$caseId])) return false;
        return ($mapEstojos[$caseId]['seller_id'] ?? '') === $filtroVendedora;
    }));
}

// Filtro por Nome / Código (Busca textual)
if (!empty($filtroProduto)) {
    $termo = mb_strtolower($filtroProduto, 'UTF-8');
    $produtos = array_values(array_filter($produtos, function($p) use ($termo) {
        return str_contains(mb_strtolower($p['nome'] ?? '', 'UTF-8'), $termo) ||
               str_contains(mb_strtolower($p['codigo'] ?? '', 'UTF-8'), $termo) ||
               str_contains(mb_strtolower($p['descricao'] ?? '', 'UTF-8'), $termo);
    }));
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Filtros Múltiplos: Produto, Categoria, Estojo, Vendedora, Status -->
<div class="content-card" style="margin-bottom: 20px;">
    <div class="content-card-header" style="flex-wrap: wrap; gap: 14px;">
        <form method="GET" action="produtos.php" style="display: flex; flex-wrap: wrap; gap: 10px; flex: 1;">
            <!-- Filtro Produto -->
            <input type="text" name="produto" class="form-control" style="max-width: 180px;" placeholder="Nome ou código..." value="<?= sanitize($filtroProduto) ?>">
            
            <!-- Filtro Categoria -->
            <select name="categoria" class="form-control" style="max-width: 160px;">
                <option value="">Todas Categorias</option>
                <?php foreach ($listaCategorias as $cat): ?>
                    <option value="<?= sanitize($cat) ?>" <?= $filtroCategoria === $cat ? 'selected' : '' ?>><?= sanitize($cat) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filtro Estojo -->
            <select name="estojo_id" class="form-control" style="max-width: 170px;">
                <option value="">Todos os Estojos</option>
                <?php foreach ($estojos as $est): ?>
                    <option value="<?= $est['id'] ?>" <?= $filtroEstojo === $est['id'] ? 'selected' : '' ?>><?= sanitize($est['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filtro Vendedora -->
            <select name="vendedora_id" class="form-control" style="max-width: 170px;">
                <option value="">Todas Vendedoras</option>
                <?php foreach ($vendedoras as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $filtroVendedora === $v['id'] ? 'selected' : '' ?>><?= sanitize($v['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filtro Status -->
            <select name="status" class="form-control" style="max-width: 150px;">
                <option value="">Todos Status</option>
                <option value="Disponível" <?= $filtroStatus === 'Disponível' ? 'selected' : '' ?>>Disponível</option>
                <option value="Indisponível" <?= $filtroStatus === 'Indisponível' ? 'selected' : '' ?>>Indisponível</option>
                <option value="Vendido" <?= $filtroStatus === 'Vendido' ? 'selected' : '' ?>>Vendido</option>
            </select>

            <button type="submit" class="btn btn-secondary">
                <i class="bi bi-funnel"></i> Filtrar
            </button>

            <?php if (!empty($filtroProduto) || !empty($filtroCategoria) || !empty($filtroEstojo) || !empty($filtroVendedora) || !empty($filtroStatus)): ?>
                <a href="produtos.php" class="btn btn-secondary" title="Limpar Filtros">
                    <i class="bi bi-x-circle"></i>
                </a>
            <?php endif; ?>
        </form>

        <a href="produto-novo.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Cadastrar Produto</span>
        </a>
    </div>
</div>

<!-- Tabela de Produtos: Foto | Código | Produto | Categoria | Estojo | Vendedora | Preço | Estoque | Status | Ações -->
<div class="content-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Foto</th>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Estojo</th>
                    <th>Vendedora</th>
                    <th>Preço</th>
                    <th>Estoque</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produtos)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--admin-muted); padding: 36px;">
                            Nenhum produto encontrado com os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($produtos as $p): 
                        $caseId = $p['case_id'] ?? null;
                        $estojo = $caseId && isset($mapEstojos[$caseId]) ? $mapEstojos[$caseId] : null;
                        $vendedora = null;
                        if ($estojo && !empty($estojo['seller_id']) && isset($mapVendedoras[$estojo['seller_id']])) {
                            $vendedora = $mapVendedoras[$estojo['seller_id']];
                        }
                        $status = $p['status'] ?? 'Disponível';
                        $stClass = 'badge-' . strtolower($status);
                        $img = !empty($p['imagem_url']) ? $p['imagem_url'] : 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80';
                    ?>
                        <tr>
                            <td>
                                <img src="<?= sanitize($img) ?>" alt="" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid var(--admin-border);">
                            </td>
                            <td>
                                <code><?= sanitize($p['codigo'] ?? '-') ?></code>
                            </td>
                            <td>
                                <strong><?= sanitize($p['nome']) ?></strong>
                                <?php if (!empty($p['descricao'])): ?>
                                    <br><small style="color: var(--admin-muted); max-width: 260px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= sanitize($p['descricao']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; font-size: 0.85rem;"><?= sanitize($p['categoria'] ?? 'Outros') ?></span>
                            </td>
                            <td>
                                <?php if ($estojo): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: #855b08;">
                                        <i class="bi bi-briefcase"></i> <?= sanitize($estojo['nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-size: 0.8rem;">Sem Estojo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vendedora): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="bi bi-person text-primary" style="color: var(--admin-primary);"></i>
                                        <strong><?= sanitize($vendedora['nome']) ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--admin-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--admin-primary-dark); font-size: 0.95rem;"><?= formatMoney($p['preco']) ?></strong>
                            </td>
                            <td>
                                <span style="font-weight: 700;"><?= (int)($p['estoque'] ?? 0) ?> un.</span>
                            </td>
                            <td>
                                <span class="badge <?= $stClass ?>"><?= sanitize($status) ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <!-- Ação Rápida de Status -->
                                    <?php if ($status === 'Disponível'): ?>
                                        <form method="POST" action="produtos.php" style="display: inline;">
                                            <input type="hidden" name="acao" value="alterar_status">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Vendido">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Marcar como Vendido">
                                                <i class="bi bi-tag-fill"></i> Vendido
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="produtos.php" style="display: inline;">
                                            <input type="hidden" name="acao" value="alterar_status">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="Disponível">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Marcar como Disponível">
                                                <i class="bi bi-check-circle-fill" style="color: #059669;"></i> Disponível
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Editar -->
                                    <a href="produto-editar.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-icon" title="Editar Produto">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <!-- Excluir -->
                                    <form method="POST" action="produtos.php" style="display: inline;" onsubmit="return confirm('Deseja realmente excluir o produto <?= sanitize($p['nome']) ?>?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-icon" style="color: #ef4444;" title="Excluir Produto">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
