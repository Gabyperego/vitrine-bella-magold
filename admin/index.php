<?php
/**
 * Dashboard Administrativo — Bella Magold
 * Visão Geral dos Estojos em Aberto, Comissões e Vendas Cruzadas
 */

$pageTitle = 'Dashboard dos estojos em aberto';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once __DIR__ . '/partials/header.php';

$supabase = Supabase::getInstance();

// 1. Carrega dados do Supabase
$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$estojos = $supabase->select('cases', '*', [], 'nome.asc');
$produtos = $supabase->select('products');
$vendas = $supabase->select('sales', '*', [], 'created_at.desc');
$itensVendidos = $supabase->select('sale_items');

// Mapas por ID para buscas rápidas
$mapVendedoras = [];
foreach ($vendedoras as $v) $mapVendedoras[$v['id']] = $v;

$mapVendas = [];
foreach ($vendas as $vd) $mapVendas[$vd['id']] = $vd;

// 2. Filtros do Dashboard
$filtroVendedoraId = $_GET['vendedora_id'] ?? '';
$filtroMes = $_GET['mes'] ?? date('Y-m');

// 3. Estojos Abertos (Ativos)
$estojosAbertos = array_filter($estojos, function($est) use ($filtroVendedoraId) {
    if (empty($est['ativo'])) return false;
    if (!empty($filtroVendedoraId) && ($est['seller_id'] ?? '') !== $filtroVendedoraId) {
        return false;
    }
    return true;
});

// Percentual padrão de comissão (40%)
$taxaComissao = 0.40;
?>

<!-- ============================================================
     1. CABEÇALHO DO DASHBOARD (VISÃO GERAL)
     ============================================================ -->
<div class="page-header-box">
    <span class="page-category-tag">VISÃO GERAL</span>
    <h1 class="page-main-title">Dashboard dos estojos em aberto</h1>
    <p class="page-main-subtitle">
        O dashboard mostra somente os estojos que estão abertos no momento. Estojos fechados permanecem apenas no histórico.
    </p>
</div>

<!-- ============================================================
     2. BARRA DE FILTROS (VENDEDORA / MÊS)
     ============================================================ -->
<form method="GET" action="index.php" class="admin-filters-bar">
    <div class="filter-item-group">
        <label for="filtroVendedora">Vendedora</label>
        <select id="filtroVendedora" name="vendedora_id" class="form-control-admin" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($vendedoras as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $filtroVendedoraId === $v['id'] ? 'selected' : '' ?>>
                    <?= sanitize($v['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item-group">
        <label for="filtroMes">Mês de Referência</label>
        <input type="month" id="filtroMes" name="mes" class="form-control-admin" value="<?= sanitize($filtroMes) ?>" onchange="this.form.submit()">
    </div>

    <?php if (!empty($filtroVendedoraId) || $filtroMes !== date('Y-m')): ?>
        <div class="filter-item-group">
            <a href="index.php" class="btn btn-secondary btn-sm" style="margin-bottom: 2px;">
                <i class="bi bi-x-circle"></i> Limpar Filtros
            </a>
        </div>
    <?php endif; ?>
</form>

<!-- ============================================================
     3. LISTAGEM DOS ESTOJOS EM ABERTO COM MÉTRICAS E VENDA CRUZADA
     ============================================================ -->
<?php if (empty($estojosAbertos)): ?>
    <div class="content-card" style="padding: 40px; text-align: center; color: var(--admin-text-muted);">
        <i class="bi bi-briefcase" style="font-size: 2.5rem; color: #d6c6b2; margin-bottom: 12px; display: block;"></i>
        <h3 style="color: var(--admin-text-title); margin-bottom: 8px;">Nenhum estojo aberto encontrado</h3>
        <p>Não há estojos ativos para a vendedora ou período selecionado.</p>
    </div>
<?php else: ?>
    <?php foreach ($estojosAbertos as $estojo): 
        $eId = $estojo['id'];
        $sellerIdResp = $estojo['seller_id'] ?? null;
        $vendedoraResp = $sellerIdResp && isset($mapVendedoras[$sellerIdResp]) ? $mapVendedoras[$sellerIdResp] : null;
        $nomeResp = $vendedoraResp ? $vendedoraResp['nome'] : 'Não vinculada';

        // 1. Filtra itens vendidos pertencentes a este estojo
        $itensEstojo = [];
        foreach ($itensVendidos as $it) {
            if (($it['case_id'] ?? '') === $eId) {
                // Checa se a venda não foi cancelada
                $vendaMae = $mapVendas[$it['sale_id'] ?? ''] ?? null;
                if ($vendaMae && ($vendaMae['status'] ?? '') === 'Cancelada') continue;

                // Checa filtro de mês
                $dataItem = $it['created_at'] ?? ($vendaMae['created_at'] ?? '');
                if (!empty($filtroMes) && !str_starts_with($dataItem, $filtroMes)) continue;

                $itensEstojo[] = $it;
            }
        }

        // 2. Cálculos Financeiros do Estojo Aberto
        $totalVendidoEstojo = 0.0;
        $vendasPropriaResp = 0.0;
        $vendasCruzadasItens = [];

        foreach ($itensEstojo as $it) {
            $sub = (float)($it['subtotal'] ?? 0);
            $totalVendidoEstojo += $sub;

            $vendedorDoItem = $it['seller_id'] ?? null;
            if ($vendedorDoItem === $sellerIdResp) {
                $vendasPropriaResp += $sub;
            } else {
                $vendasCruzadasItens[] = $it;
            }
        }

        // Comissão da responsável (40% sobre o que ela vendeu do próprio estojo)
        $comissaoResponsavel = $vendasPropriaResp * $taxaComissao;

        // Total a repassar à proprietária (Valor total vendido - comissão da responsável)
        $totalRepassar = $totalVendidoEstojo - $comissaoResponsavel;
    ?>
        <div class="case-card-box">
            <!-- Barra Superior do Estojo: Ponto Verde + Aberto -->
            <div class="case-card-header">
                <div class="case-status-indicator">
                    <span class="dot-green"></span>
                    <span class="case-name-text">Estojo: <?= sanitize($estojo['nome']) ?></span>
                    <span style="color: #cbd5e1;">•</span>
                    <span class="case-seller-text">Responsável: <strong><?= sanitize($nomeResp) ?></strong></span>
                </div>
                <div>
                    <span class="badge-open-tag">ABERTO</span>
                </div>
            </div>

            <!-- Grid de 4 Métricas Principais -->
            <div class="case-metrics-row">
                <!-- 1. Total vendido no estojo (Card Destaque Vinho/Rosé) -->
                <div class="metric-box metric-box-highlight">
                    <div class="metric-box-title">Total vendido no estojo</div>
                    <div class="metric-box-value"><?= formatMoney($totalVendidoEstojo) ?></div>
                    <div class="metric-box-detail">Somente vendas deste estojo aberto</div>
                </div>

                <!-- 2. Vendeu do próprio estojo -->
                <div class="metric-box metric-box-clean">
                    <div class="metric-box-title"><?= sanitize($nomeResp) ?> vendeu do próprio estojo</div>
                    <div class="metric-box-value"><?= formatMoney($vendasPropriaResp) ?></div>
                    <div class="metric-box-detail">Vendas feitas pela responsável</div>
                </div>

                <!-- 3. Comissão da Responsável (40%) -->
                <div class="metric-box metric-box-clean">
                    <div class="metric-box-title">Comissão de <?= sanitize($nomeResp) ?> (40%)</div>
                    <div class="metric-box-value" style="color: #059669;"><?= formatMoney($comissaoResponsavel) ?></div>
                    <div class="metric-box-detail">40% das vendas da responsável</div>
                </div>

                <!-- 4. Total a repassar (60%) -->
                <div class="metric-box metric-box-clean">
                    <div class="metric-box-title">Total a repassar (60%)</div>
                    <div class="metric-box-value" style="color: var(--admin-primary);"><?= formatMoney($totalRepassar) ?></div>
                    <div class="metric-box-detail">Inclui vendas próprias e vendas cruzadas deste estojo</div>
                </div>
            </div>

            <!-- Seção de Venda Cruzada -->
            <div class="cross-sales-section">
                <span class="cross-sales-tag">VENDA CRUZADA</span>
                
                <?php if (empty($vendasCruzadasItens)): ?>
                    <div class="cross-sales-box" style="display: flex; align-items: center; gap: 10px; color: var(--admin-text-muted); font-size: 0.88rem;">
                        <i class="bi bi-info-circle" style="color: var(--admin-primary);"></i>
                        <span>Nenhuma venda cruzada registrada para o estojo de <strong><?= sanitize($nomeResp) ?></strong> neste período.</span>
                    </div>
                <?php else: 
                    // Agrupa por vendedora que realizou a venda cruzada
                    $cruzadasPorVendedora = [];
                    foreach ($vendasCruzadasItens as $vc) {
                        $vId = $vc['seller_id'] ?? 'geral';
                        if (!isset($cruzadasPorVendedora[$vId])) {
                            $cruzadasPorVendedora[$vId] = [
                                'nome' => isset($mapVendedoras[$vId]) ? $mapVendedoras[$vId]['nome'] : 'Geral / Balcão',
                                'total' => 0.0,
                                'itens' => []
                            ];
                        }
                        $cruzadasPorVendedora[$vId]['total'] += (float)($vc['subtotal'] ?? 0);
                        $cruzadasPorVendedora[$vId]['itens'][] = $vc;
                    }
                ?>
                    <?php foreach ($cruzadasPorVendedora as $cpv): ?>
                        <div class="cross-sales-box" style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <h4 class="cross-sales-title" style="margin-bottom: 0;">
                                    Quando <strong><?= sanitize($cpv['nome']) ?></strong> vende do estojo de <?= sanitize($nomeResp) ?>
                                </h4>
                                <strong style="font-size: 1.05rem; color: var(--admin-primary);">
                                    <?= formatMoney($cpv['total']) ?>
                                </strong>
                            </div>
                            <div style="font-size: 0.82rem; color: var(--admin-text-muted);">
                                <?php foreach ($cpv['itens'] as $idx => $itCruz): ?>
                                    <span>• <?= sanitize($itCruz['produto_nome']) ?> (<?= (int)($itCruz['quantidade'] ?? 1) ?>x — <?= formatMoney($itCruz['preco_unitario'] ?? 0) ?>)</span>
                                    <?php if ($idx < count($cpv['itens']) - 1) echo '<br>'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ============================================================
     4. VENDAS RECENTES (HISTÓRICO RÁPIDO)
     ============================================================ -->
<div class="content-card">
    <div class="content-card-header">
        <h3 class="card-heading">
            <i class="bi bi-clock-history text-primary" style="margin-right: 8px; color: var(--admin-primary);"></i>
            Últimas Vendas Registradas
        </h3>
        <a href="vendas.php" class="btn btn-secondary btn-sm">
            <span>Ver Todas as Vendas</span>
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref / Venda</th>
                    <th>Data & Hora</th>
                    <th>Cliente</th>
                    <th>Vendedora</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $vendasRecentes = array_slice($vendas, 0, 5);
                if (empty($vendasRecentes)): 
                ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--admin-muted); padding: 32px;">
                            Nenhuma venda registrada ainda.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendasRecentes as $vr): 
                        $sellerR = !empty($vr['seller_id']) && isset($mapVendedoras[$vr['seller_id']]) ? $mapVendedoras[$vr['seller_id']]['nome'] : 'Geral';
                        $st = strtolower($vr['status'] ?? 'pendente');
                    ?>
                        <tr>
                            <td><strong>#<?= sanitize($vr['numero_venda'] ?? $vr['id']) ?></strong></td>
                            <td><?= formatDateTime($vr['created_at'] ?? '') ?></td>
                            <td>
                                <strong><?= sanitize($vr['cliente_nome']) ?></strong>
                                <?php if (!empty($vr['cliente_telefone'])): ?>
                                    <br><small style="color: var(--admin-text-muted);"><?= formatPhone($vr['cliente_telefone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #2d2018;">
                                    <i class="bi bi-person text-primary" style="color: var(--admin-primary);"></i>
                                    <?= sanitize($sellerR) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--admin-primary); font-size: 0.95rem;">
                                    <?= formatMoney($vr['total'] ?? 0) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-<?= $st ?>"><?= sanitize($vr['status'] ?? 'Pendente') ?></span>
                            </td>
                            <td style="text-align: right;">
                                <a href="vendas.php?id=<?= $vr['id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-eye"></i> Detalhes
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
