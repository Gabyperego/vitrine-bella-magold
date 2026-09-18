<?php
/**
 * Gestão de Vendas por Estojo e Vendedora
 */

$pageTitle = 'Gestão de Vendas';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Alterar Status da Venda
    if ($acao === 'alterar_status') {
        $id = $_POST['id'] ?? null;
        $status = $_POST['status'] ?? 'Pendente';
        if ($id && in_array($status, ['Pendente', 'Concluída', 'Cancelada'])) {
            $supabase->update('sales', ['status' => $status], ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Status da venda atualizado para "' . $status . '".');
        }
        header('Location: vendas.php' . (!empty($_POST['id']) ? '?id=' . $_POST['id'] : ''));
        exit;
    }

    // 2. Registrar Venda Manual
    if ($acao === 'salvar_manual') {
        $clienteNome = trim($_POST['cliente_nome'] ?? '');
        $clienteTelefone = trim($_POST['cliente_telefone'] ?? '');
        $sellerId = $_POST['seller_id'] ?? null;
        $caseId = $_POST['case_id'] ?? null;
        $formaPagamento = $_POST['forma_pagamento'] ?? 'PIX';
        $observacoes = trim($_POST['observacoes'] ?? '');
        $totalRaw = str_replace(['R$', '.', ' '], '', $_POST['total'] ?? '0');
        $total = (float)str_replace(',', '.', $totalRaw);

        if (empty($clienteNome)) {
            setFlashMessage('error', 'O nome do cliente é obrigatório.');
            header('Location: vendas.php');
            exit;
        }

        $numeroVenda = 'VEN-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $venda = [
            'numero_venda' => $numeroVenda,
            'cliente_nome' => $clienteNome,
            'cliente_telefone' => $clienteTelefone,
            'observacoes' => $observacoes,
            'seller_id' => $sellerId ?: null,
            'forma_pagamento' => $formaPagamento,
            'total' => $total,
            'status' => 'Concluída',
            'created_at' => date('c')
        ];

        $res = $supabase->insert('sales', $venda);
        $saleId = $res[0]['id'] ?? null;

        if ($saleId) {
            $supabase->insert('sale_items', [
                'sale_id' => $saleId,
                'case_id' => $caseId ?: null,
                'seller_id' => $sellerId ?: null,
                'produto_nome' => !empty($observacoes) ? $observacoes : 'Venda Avulsa / Balcão',
                'preco_unitario' => $total,
                'quantidade' => 1,
                'subtotal' => $total,
                'created_at' => date('c')
            ]);
        }

        setFlashMessage('success', 'Venda manual registrada com sucesso!');
        header('Location: vendas.php');
        exit;
    }

    // 3. Excluir Venda
    if ($acao === 'excluir') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $supabase->delete('sales', ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Registro de venda excluído.');
        }
        header('Location: vendas.php');
        exit;
    }
}

// Carrega Dados para Relacionamentos e Filtros
$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$estojos = $supabase->select('cases', '*', [], 'nome.asc');
$vendas = $supabase->select('sales', '*', [], 'created_at.desc');
$itens = $supabase->select('sale_items');

$mapVendedoras = [];
foreach ($vendedoras as $v) $mapVendedoras[$v['id']] = $v;

$mapEstojos = [];
foreach ($estojos as $e) $mapEstojos[$e['id']] = $e;

// Indexa itens por sale_id
$itensPorVenda = [];
foreach ($itens as $it) {
    $sId = $it['sale_id'] ?? '';
    if (!isset($itensPorVenda[$sId])) {
        $itensPorVenda[$sId] = [];
    }
    $itensPorVenda[$sId][] = $it;
}

// Filtros: Período, Vendedora, Estojo, Produto, Forma de Pagamento, Status
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';
$filtroVendedora = $_GET['vendedora_id'] ?? '';
$filtroEstojo = $_GET['estojo_id'] ?? '';
$filtroProduto = trim($_GET['produto'] ?? '');
$filtroFormaPag = $_GET['forma_pagamento'] ?? '';
$filtroStatus = $_GET['status'] ?? '';

$vendasFiltradas = array_filter($vendas, function($v) use (
    $filtroDataInicio, $filtroDataFim, $filtroVendedora, $filtroEstojo, 
    $filtroProduto, $filtroFormaPag, $filtroStatus, $itensPorVenda
) {
    if (!empty($filtroDataInicio)) {
        $dataVenda = substr($v['created_at'] ?? '', 0, 10);
        if ($dataVenda < $filtroDataInicio) return false;
    }

    if (!empty($filtroDataFim)) {
        $dataVenda = substr($v['created_at'] ?? '', 0, 10);
        if ($dataVenda > $filtroDataFim) return false;
    }

    if (!empty($filtroVendedora) && ($v['seller_id'] ?? '') !== $filtroVendedora) {
        return false;
    }

    if (!empty($filtroFormaPag) && ($v['forma_pagamento'] ?? '') !== $filtroFormaPag) {
        return false;
    }

    if (!empty($filtroStatus) && ($v['status'] ?? '') !== $filtroStatus) {
        return false;
    }

    $vendaItens = $itensPorVenda[$v['id']] ?? [];

    if (!empty($filtroEstojo)) {
        $temEstojo = false;
        foreach ($vendaItens as $vi) {
            if (($vi['case_id'] ?? '') === $filtroEstojo) {
                $temEstojo = true;
                break;
            }
        }
        if (!$temEstojo) return false;
    }

    if (!empty($filtroProduto)) {
        $termo = mb_strtolower($filtroProduto, 'UTF-8');
        $temProduto = false;
        if (str_contains(mb_strtolower($v['cliente_nome'] ?? '', 'UTF-8'), $termo) ||
            str_contains(mb_strtolower($v['numero_venda'] ?? '', 'UTF-8'), $termo)) {
            $temProduto = true;
        } else {
            foreach ($vendaItens as $vi) {
                if (str_contains(mb_strtolower($vi['produto_nome'] ?? '', 'UTF-8'), $termo)) {
                    $temProduto = true;
                    break;
                }
            }
        }
        if (!$temProduto) return false;
    }

    return true;
});

// Pedido em Detalhe
$detalheId = $_GET['id'] ?? null;
$vendaDetalhe = null;
$itensDetalhe = [];

if ($detalheId) {
    foreach ($vendas as $v) {
        if ($v['id'] === $detalheId) {
            $vendaDetalhe = $v;
            $itensDetalhe = $itensPorVenda[$v['id']] ?? [];
            break;
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Barra de Filtros Avançados -->
<div class="content-card" style="margin-bottom: 20px;">
    <div class="content-card-header" style="flex-direction: column; align-items: stretch; gap: 14px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <p style="color: var(--admin-muted); font-size: 0.88rem; margin: 0;">
                Consulte vendas detalhadas com rastreabilidade de <strong>Produto</strong>, <strong>Estojo</strong> e <strong>Vendedora</strong>.
            </p>
            <button type="button" class="btn btn-primary" onclick="abrirModal('modalNovaVenda')">
                <i class="bi bi-plus-lg"></i> Registrar Venda Manual
            </button>
        </div>

        <form method="GET" action="vendas.php" style="display: flex; flex-wrap: wrap; gap: 10px;">
            <!-- Período: De / Até -->
            <div style="display: flex; align-items: center; gap: 6px;">
                <input type="date" name="data_inicio" class="form-control" style="width: 140px;" value="<?= sanitize($filtroDataInicio) ?>" title="Data Inicial">
                <span style="font-size: 0.8rem; color: var(--admin-muted);">até</span>
                <input type="date" name="data_fim" class="form-control" style="width: 140px;" value="<?= sanitize($filtroDataFim) ?>" title="Data Final">
            </div>

            <!-- Vendedora -->
            <select name="vendedora_id" class="form-control" style="max-width: 160px;">
                <option value="">Todas Vendedoras</option>
                <?php foreach ($vendedoras as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $filtroVendedora === $v['id'] ? 'selected' : '' ?>><?= sanitize($v['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Estojo -->
            <select name="estojo_id" class="form-control" style="max-width: 170px;">
                <option value="">Todos os Estojos</option>
                <?php foreach ($estojos as $est): ?>
                    <option value="<?= $est['id'] ?>" <?= $filtroEstojo === $est['id'] ? 'selected' : '' ?>><?= sanitize($est['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Produto / Cliente -->
            <input type="text" name="produto" class="form-control" style="max-width: 160px;" placeholder="Produto ou Cliente..." value="<?= sanitize($filtroProduto) ?>">

            <!-- Forma de Pagamento -->
            <select name="forma_pagamento" class="form-control" style="max-width: 160px;">
                <option value="">Forma de Pagto</option>
                <option value="PIX" <?= $filtroFormaPag === 'PIX' ? 'selected' : '' ?>>PIX</option>
                <option value="Cartão de Crédito" <?= $filtroFormaPag === 'Cartão de Crédito' ? 'selected' : '' ?>>Cartão de Crédito</option>
                <option value="Cartão de Débito" <?= $filtroFormaPag === 'Cartão de Débito' ? 'selected' : '' ?>>Cartão de Débito</option>
                <option value="Dinheiro" <?= $filtroFormaPag === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                <option value="WhatsApp / A Combinar" <?= $filtroFormaPag === 'WhatsApp / A Combinar' ? 'selected' : '' ?>>WhatsApp / A Combinar</option>
            </select>

            <!-- Status -->
            <select name="status" class="form-control" style="max-width: 140px;">
                <option value="">Status</option>
                <option value="Pendente" <?= $filtroStatus === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="Concluída" <?= $filtroStatus === 'Concluída' ? 'selected' : '' ?>>Concluída</option>
                <option value="Cancelada" <?= $filtroStatus === 'Cancelada' ? 'selected' : '' ?>>Cancelada</option>
            </select>

            <button type="submit" class="btn btn-secondary">
                <i class="bi bi-funnel"></i> Filtrar
            </button>

            <?php if (!empty($filtroDataInicio) || !empty($filtroDataFim) || !empty($filtroVendedora) || !empty($filtroEstojo) || !empty($filtroProduto) || !empty($filtroFormaPag) || !empty($filtroStatus)): ?>
                <a href="vendas.php" class="btn btn-secondary" title="Limpar Filtros">
                    <i class="bi bi-x-circle"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela de Vendas -->
<div class="content-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref / Venda</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Vendedora</th>
                    <th>Estojo(s) de Origem</th>
                    <th>Forma de Pagamento</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendasFiltradas)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--admin-muted); padding: 36px;">
                            Nenhuma venda encontrada com os filtros informados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendasFiltradas as $vd): 
                        $seller = !empty($vd['seller_id']) && isset($mapVendedoras[$vd['seller_id']]) ? $mapVendedoras[$vd['seller_id']] : null;
                        $vItens = $itensPorVenda[$vd['id']] ?? [];
                        
                        // Coleta nomes dos estojos envolvidos
                        $estojosNomes = [];
                        foreach ($vItens as $item) {
                            $cId = $item['case_id'] ?? null;
                            if ($cId && isset($mapEstojos[$cId])) {
                                $estojosNomes[$cId] = $mapEstojos[$cId]['nome'];
                            }
                        }

                        $st = strtolower($vd['status'] ?? 'pendente');
                    ?>
                        <tr>
                            <td><strong>#<?= sanitize($vd['numero_venda'] ?? $vd['id']) ?></strong></td>
                            <td><?= formatDateTime($vd['created_at'] ?? '') ?></td>
                            <td>
                                <strong><?= sanitize($vd['cliente_nome']) ?></strong>
                                <?php if (!empty($vd['cliente_telefone'])): ?>
                                    <br><small style="color: var(--admin-muted);"><?= formatPhone($vd['cliente_telefone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($seller): ?>
                                    <span style="font-weight: 600; color: #0f172a;">
                                        <i class="bi bi-person text-primary" style="color: var(--admin-primary);"></i>
                                        <?= sanitize($seller['nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--admin-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($estojosNomes)): ?>
                                    <?php foreach ($estojosNomes as $nomeEstojo): ?>
                                        <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fde047; margin: 2px;">
                                            <i class="bi bi-briefcase"></i> <?= sanitize($nomeEstojo) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--admin-muted); font-size: 0.8rem;">Geral / Balcão</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">
                                    <?= sanitize($vd['forma_pagamento'] ?? 'A Combinar') ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--admin-primary-dark); font-size: 0.95rem;">
                                    <?= formatMoney($vd['total']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-<?= $st ?>"><?= sanitize($vd['status'] ?? 'Pendente') ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <a href="vendas.php?id=<?= $vd['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Detalhes">
                                        <i class="bi bi-eye"></i> Detalhes
                                    </a>

                                    <form method="POST" action="vendas.php" style="display: inline;" onsubmit="return confirm('Deseja excluir o registro desta venda?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $vd['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-icon" style="color: #ef4444;" title="Excluir">
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

<!-- MODAL DE DETALHES DA VENDA -->
<?php if ($vendaDetalhe): 
    $sellerDet = !empty($vendaDetalhe['seller_id']) && isset($mapVendedoras[$vendaDetalhe['seller_id']]) ? $mapVendedoras[$vendaDetalhe['seller_id']] : null;
?>
    <div class="modal-backdrop show" id="modalDetalhes">
        <div class="modal-dialog" style="max-width: 680px;">
            <div class="modal-header">
                <h3 class="modal-title">Detalhes da Venda #<?= sanitize($vendaDetalhe['numero_venda'] ?? $vendaDetalhe['id']) ?></h3>
                <a href="vendas.php" class="btn-close-modal">&times;</a>
            </div>

            <div class="modal-body">
                <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--admin-border); margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span><strong>Cliente:</strong> <?= sanitize($vendaDetalhe['cliente_nome']) ?></span>
                        <span><strong>Data:</strong> <?= formatDateTime($vendaDetalhe['created_at'] ?? '') ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>
                            <strong>Telefone:</strong> 
                            <?= !empty($vendaDetalhe['cliente_telefone']) ? formatPhone($vendaDetalhe['cliente_telefone']) : 'Não informado' ?>
                        </span>
                        <span>
                            <strong>Vendedora:</strong> <?= $sellerDet ? sanitize($sellerDet['nome']) : 'Geral' ?>
                        </span>
                    </div>
                    <div style="margin-top: 6px;">
                        <strong>Forma de Pagamento:</strong> <?= sanitize($vendaDetalhe['forma_pagamento'] ?? 'A Combinar') ?>
                    </div>
                    <?php if (!empty($vendaDetalhe['observacoes'])): ?>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 0.88rem;">
                            <strong>Observações:</strong><br>
                            <em><?= sanitize($vendaDetalhe['observacoes']) ?></em>
                        </div>
                    <?php endif; ?>
                </div>

                <h4 style="font-size: 0.95rem; margin-bottom: 12px; font-weight: 700;">Produtos / Itens Vendidos:</h4>
                <div class="table-responsive" style="margin-bottom: 20px;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Estojo de Origem</th>
                                <th>Qtd</th>
                                <th>Valor Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($itensDetalhe)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--admin-muted);">Nenhum item detalhado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($itensDetalhe as $it): 
                                    $estOrigem = !empty($it['case_id']) && isset($mapEstojos[$it['case_id']]) ? $mapEstojos[$it['case_id']]['nome'] : 'Avulso';
                                ?>
                                    <tr>
                                        <td><strong><?= sanitize($it['produto_nome']) ?></strong></td>
                                        <td>
                                            <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fde047;">
                                                <i class="bi bi-briefcase"></i> <?= sanitize($estOrigem) ?>
                                            </span>
                                        </td>
                                        <td><?= (int)($it['quantidade'] ?? 1) ?>x</td>
                                        <td><?= formatMoney($it['preco_unitario'] ?? 0) ?></td>
                                        <td><strong><?= formatMoney($it['subtotal'] ?? 0) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right; font-weight: 700; padding: 12px 20px;">Total da Venda:</td>
                                <td style="font-weight: 800; font-size: 1.1rem; color: var(--admin-primary-dark); padding: 12px 20px;">
                                    <?= formatMoney($vendaDetalhe['total'] ?? 0) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Alterar Status -->
                <form method="POST" action="vendas.php" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #fffbeb; padding: 14px; border-radius: 8px; border: 1px solid #fde68a;">
                    <input type="hidden" name="acao" value="alterar_status">
                    <input type="hidden" name="id" value="<?= $vendaDetalhe['id'] ?>">
                    
                    <div style="font-weight: 600; font-size: 0.9rem;">Atualizar Status:</div>
                    
                    <div style="display: flex; gap: 8px;">
                        <select name="status" class="form-control" style="width: auto;">
                            <option value="Pendente" <?= ($vendaDetalhe['status'] ?? '') === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="Concluída" <?= ($vendaDetalhe['status'] ?? '') === 'Concluída' ? 'selected' : '' ?>>Concluída</option>
                            <option value="Cancelada" <?= ($vendaDetalhe['status'] ?? '') === 'Cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <a href="vendas.php" class="btn btn-secondary">Fechar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL REGISTRO MANUAL DE VENDA -->
<div class="modal-backdrop" id="modalNovaVenda">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Registrar Venda Manualmente</h3>
            <button type="button" class="btn-close-modal" onclick="fecharModal('modalNovaVenda')">&times;</button>
        </div>

        <form method="POST" action="vendas.php">
            <input type="hidden" name="acao" value="salvar_manual">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label" for="manualCliente">Nome do Cliente <span style="color: red;">*</span></label>
                        <input type="text" id="manualCliente" name="cliente_nome" class="form-control" placeholder="Nome completo" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manualTelefone">WhatsApp / Telefone</label>
                        <input type="tel" id="manualTelefone" name="cliente_telefone" class="form-control" placeholder="(67) 99999-9999">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manualVendedora">Vendedora</label>
                        <select id="manualVendedora" name="seller_id" class="form-control">
                            <option value="">Venda Direta / Balcão</option>
                            <?php foreach ($vendedoras as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= sanitize($v['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manualEstojo">Estojo de Origem</label>
                        <select id="manualEstojo" name="case_id" class="form-control">
                            <option value="">Nenhum / Mostruário Geral</option>
                            <?php foreach ($estojos as $est): ?>
                                <option value="<?= $est['id'] ?>"><?= sanitize($est['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manualForma">Forma de Pagamento</label>
                        <select id="manualForma" name="forma_pagamento" class="form-control">
                            <option value="PIX">PIX</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="Cartão de Débito">Cartão de Débito</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="WhatsApp / A Combinar">WhatsApp / A Combinar</option>
                        </select>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="manualTotal">Valor Total da Venda (R$) <span style="color: red;">*</span></label>
                        <input type="text" id="manualTotal" name="total" class="form-control" placeholder="0,00" required>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="manualObs">Observações / Descrição dos Itens</label>
                        <textarea id="manualObs" name="observacoes" class="form-control" rows="2" placeholder="Peças vendidas, descontos ou condições especiais..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalNovaVenda')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar Venda
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
