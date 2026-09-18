<?php
/**
 * Relatório de Fechamento de Estojo e Comissões
 * VENDEDORA -> ESTOJO -> PRODUTOS -> FECHAMENTO
 */

$pageTitle = 'Fechamento de Estojo & Relatórios';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// Carrega Estojos e Vendedoras
$estojos = $supabase->select('cases', '*', [], 'nome.asc');
$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$mapVendedoras = [];
foreach ($vendedoras as $v) $mapVendedoras[$v['id']] = $v;

$mapEstojos = [];
foreach ($estojos as $e) $mapEstojos[$e['id']] = $e;

// Processa salvamento de fechamento no histórico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_fechamento') {
    $caseId = $_POST['case_id'] ?? null;
    $dataInicial = $_POST['data_inicial'] ?? '';
    $dataFinal = $_POST['data_final'] ?? '';
    $totalVendidas = (int)($_POST['total_pecas_vendidas'] ?? 0);
    $totalRestantes = (int)($_POST['total_pecas_restantes'] ?? 0);
    $totalVendido = (float)($_POST['valor_total_vendido'] ?? 0);
    $comissaoVendPct = (float)($_POST['comissao_vendedora_pct'] ?? 0);
    $valorComissaoVend = (float)($_POST['valor_comissao_vendedora'] ?? 0);
    $comissaoPropPct = (float)($_POST['comissao_proprietaria_pct'] ?? 0);
    $valorRepasse = (float)($_POST['valor_repasse'] ?? 0);
    $obs = trim($_POST['observacoes'] ?? '');

    $sellerId = null;
    if ($caseId && isset($mapEstojos[$caseId])) {
        $sellerId = $mapEstojos[$caseId]['seller_id'] ?? null;
    }

    $fechamentoData = [
        'case_id' => $caseId,
        'seller_id' => $sellerId,
        'data_inicial' => $dataInicial . 'T00:00:00Z',
        'data_final' => $dataFinal . 'T23:59:59Z',
        'total_pecas_vendidas' => $totalVendidas,
        'total_pecas_restantes' => $totalRestantes,
        'valor_total_vendido' => $totalVendido,
        'comissao_vendedora_pct' => $comissaoVendPct,
        'valor_comissao_vendedora' => $valorComissaoVend,
        'comissao_proprietaria_pct' => $comissaoPropPct,
        'valor_repasse' => $valorRepasse,
        'observacoes' => $obs,
        'status' => 'Fechado',
        'created_at' => date('c')
    ];

    $supabase->insert('case_closings', $fechamentoData);
    setFlashMessage('success', 'Fechamento do estojo gravado com sucesso no histórico!');
    header('Location: relatorios.php?estojo_id=' . $caseId . '&data_inicio=' . $dataInicial . '&data_fim=' . $dataFinal);
    exit;
}

// Parâmetros do Relatório
$selEstojoId = $_GET['estojo_id'] ?? ($estojos[0]['id'] ?? '');
$selDataInicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Início do mês atual
$selDataFim = $_GET['data_fim'] ?? date('Y-m-d');       // Hoje
$comissaoVendedoraPct = isset($_GET['comissao_vendedora']) ? (float)$_GET['comissao_vendedora'] : 30.0;
$comissaoProprietariaPct = 100.0 - $comissaoVendedoraPct;

$estojoAtual = $selEstojoId && isset($mapEstojos[$selEstojoId]) ? $mapEstojos[$selEstojoId] : null;
$vendedoraAtual = $estojoAtual && !empty($estojoAtual['seller_id']) && isset($mapVendedoras[$estojoAtual['seller_id']]) ? $mapVendedoras[$estojoAtual['seller_id']] : null;

// Produtos e Itens Vendidos do Estojo
$produtos = $supabase->select('products');
$itensVendidos = $supabase->select('sale_items');

// 1. Produtos pertencentes ao estojo
$produtosDoEstojo = array_values(array_filter($produtos, fn($p) => ($p['case_id'] ?? '') === $selEstojoId));

// 2. Produtos vendidos deste estojo no período
$itensVendidosNoPeriodo = [];
$totalVendidoEstojo = 0.0;
$totalQtdVendida = 0;

foreach ($itensVendidos as $iv) {
    if (($iv['case_id'] ?? '') === $selEstojoId) {
        $dt = substr($iv['created_at'] ?? '', 0, 10);
        if ($dt >= $selDataInicio && $dt <= $selDataFim) {
            $itensVendidosNoPeriodo[] = $iv;
            $qtd = (int)($iv['quantidade'] ?? 1);
            $sub = (float)($iv['subtotal'] ?? 0);
            $totalQtdVendida += $qtd;
            $totalVendidoEstojo += $sub;
        }
    }
}

// 3. Produtos restantes no estojo
$totalEstoqueRestante = array_sum(array_map(fn($p) => (int)($p['estoque'] ?? 0), $produtosDoEstojo));

// 4. Cálculos de Comissão e Repasse
$valorComissaoVendedora = ($totalVendidoEstojo * $comissaoVendedoraPct) / 100.0;
$valorRepasseProprietaria = ($totalVendidoEstojo * $comissaoProprietariaPct) / 100.0;

// Histórico de Fechamentos Anteriores
$historicoFechamentos = $supabase->select('case_closings', '*', [], 'created_at.desc');

require_once __DIR__ . '/partials/header.php';
?>

<!-- Formulário de Seleção do Fechamento de Estojo -->
<div class="content-card" style="margin-bottom: 24px;">
    <div class="content-card-header">
        <h3 class="card-heading">
            <i class="bi bi-calculator-fill text-primary" style="margin-right: 8px;"></i>
            Fechamento de Estojo & Apuração de Comissões
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir Relatório
        </button>
    </div>

    <form method="GET" action="relatorios.php" style="padding: 20px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; background: #f8fafc; border-bottom: 1px solid var(--admin-border);">
        <div class="form-group" style="min-width: 200px;">
            <label class="form-label" for="selEstojo">Selecionar Estojo</label>
            <select id="selEstojo" name="estojo_id" class="form-control" required>
                <?php foreach ($estojos as $est): ?>
                    <option value="<?= $est['id'] ?>" <?= $selEstojoId === $est['id'] ? 'selected' : '' ?>>
                        📁 <?= sanitize($est['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="dtIni">Data Inicial</label>
            <input type="date" id="dtIni" name="data_inicio" class="form-control" value="<?= sanitize($selDataInicio) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="dtFim">Data Final</label>
            <input type="date" id="dtFim" name="data_final" class="form-control" value="<?= sanitize($selDataFim) ?>" required>
        </div>

        <div class="form-group" style="width: 140px;">
            <label class="form-label" for="comVend">% Comissão Vendedora</label>
            <input type="number" step="0.5" id="comVend" name="comissao_vendedora" class="form-control" value="<?= $comissaoVendedoraPct ?>" min="0" max="100">
        </div>

        <button type="submit" class="btn btn-primary" style="height: 42px;">
            <i class="bi bi-search"></i> Gerar Fechamento
        </button>
    </form>
</div>

<!-- ============================================================
     RELATÓRIO GERADO
     ============================================================ -->
<?php if ($estojoAtual): ?>
    <div class="content-card" id="relatorioImpressao">
        <div style="padding: 24px; border-bottom: 1px solid var(--admin-border); background: linear-gradient(135deg, #fdf8eb 0%, #ffffff 100%);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <span class="badge" style="background: var(--admin-primary); color: #fff; margin-bottom: 8px;">
                        Relatório Oficial de Fechamento
                    </span>
                    <h2 style="font-size: 1.6rem; color: #0f172a; margin-bottom: 4px;">
                        <?= sanitize($estojoAtual['nome']) ?>
                    </h2>
                    <p style="color: var(--admin-muted); font-size: 0.9rem;">
                        <strong>Vendedora Responsável:</strong> <?= $vendedoraAtual ? sanitize($vendedoraAtual['nome']) : 'Não vinculada' ?>
                        <?php if ($vendedoraAtual): ?>
                            &bull; <strong>WhatsApp:</strong> <?= formatPhone($vendedoraAtual['whatsapp']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.85rem; color: var(--admin-muted);">Período de Apuração:</div>
                    <strong style="font-size: 1.05rem; color: #0f172a;">
                        <?= formatDateOnly($selDataInicio) ?> até <?= formatDateOnly($selDataFim) ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo Financeiro do Fechamento -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding: 24px; background: #fafafa; border-bottom: 1px solid var(--admin-border);">
            <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--admin-border);">
                <div style="font-size: 0.78rem; color: var(--admin-muted); text-transform: uppercase; font-weight: 700;">Total Vendido no Período</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #0f172a;"><?= formatMoney($totalVendidoEstojo) ?></div>
                <small style="color: #059669; font-weight: 600;"><?= $totalQtdVendida ?> peça(s) vendida(s)</small>
            </div>

            <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--admin-border);">
                <div style="font-size: 0.78rem; color: var(--admin-muted); text-transform: uppercase; font-weight: 700;">Comissão da Vendedora (<?= $comissaoVendedoraPct ?>%)</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #059669;"><?= formatMoney($valorComissaoVendedora) ?></div>
                <small style="color: var(--admin-muted);">Ganho da profissional</small>
            </div>

            <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--admin-border);">
                <div style="font-size: 0.78rem; color: var(--admin-muted); text-transform: uppercase; font-weight: 700;">Valor Líquido de Repasse (<?= $comissaoProprietariaPct ?>%)</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: var(--admin-primary-dark);"><?= formatMoney($valorRepasseProprietaria) ?></div>
                <small style="color: var(--admin-muted);">Valor a ser repassado à proprietária</small>
            </div>

            <div style="background: #ffffff; padding: 16px; border-radius: 8px; border: 1px solid var(--admin-border);">
                <div style="font-size: 0.78rem; color: var(--admin-muted); text-transform: uppercase; font-weight: 700;">Saldo de Peças no Estojo</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #d97706;"><?= $totalEstoqueRestante ?> un.</div>
                <small style="color: var(--admin-muted);"><?= count($produtosDoEstojo) ?> modelos cadastrados</small>
            </div>
        </div>

        <!-- 1. Tabela de Produtos Vendidos -->
        <div style="padding: 24px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 14px;">
                <i class="bi bi-cart-check-fill text-primary" style="color: var(--admin-primary);"></i>
                1. Produtos Vendidos no Período
            </h3>
            
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Produto</th>
                            <th>Qtd. Vendida</th>
                            <th>Valor Unitário</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itensVendidosNoPeriodo)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--admin-muted); padding: 24px;">
                                    Nenhuma peça vendida deste estojo no período selecionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($itensVendidosNoPeriodo as $ivp): ?>
                                <tr>
                                    <td><?= formatDateTime($ivp['created_at'] ?? '') ?></td>
                                    <td><strong><?= sanitize($ivp['produto_nome']) ?></strong></td>
                                    <td><?= (int)($ivp['quantidade'] ?? 1) ?>x</td>
                                    <td><?= formatMoney($ivp['preco_unitario'] ?? 0) ?></td>
                                    <td><strong style="color: var(--admin-primary-dark);"><?= formatMoney($ivp['subtotal'] ?? 0) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align: right; font-weight: 700; padding: 14px 20px;">Total Vendido do Fechamento:</td>
                            <td style="font-weight: 800; font-size: 1.15rem; color: var(--admin-primary-dark); padding: 14px 20px;">
                                <?= formatMoney($totalVendidoEstojo) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- 2. Tabela de Produtos Restantes no Estojo -->
        <div style="padding: 0 24px 24px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 14px;">
                <i class="bi bi-box-seam text-primary" style="color: var(--admin-primary);"></i>
                2. Produtos Restantes no Estojo (Estoque Atual)
            </h3>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Preço Tabela</th>
                            <th>Estoque Restante</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtosDoEstojo)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--admin-muted); padding: 24px;">
                                    Nenhum produto vinculado a este estojo.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtosDoEstojo as $pe): 
                                $st = strtolower($pe['status'] ?? 'disponível');
                            ?>
                                <tr>
                                    <td><code><?= sanitize($pe['codigo'] ?? '-') ?></code></td>
                                    <td><strong><?= sanitize($pe['nome']) ?></strong></td>
                                    <td><?= sanitize($pe['categoria'] ?? 'Outros') ?></td>
                                    <td><?= formatMoney($pe['preco']) ?></td>
                                    <td>
                                        <strong style="color: <?= ($pe['estoque'] ?? 0) > 0 ? '#059669' : '#ef4444' ?>;">
                                            <?= (int)($pe['estoque'] ?? 0) ?> un.
                                        </strong>
                                    </td>
                                    <td><span class="badge badge-<?= $st ?>"><?= sanitize($pe['status'] ?? 'Disponível') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Formulário para Gravar Fechamento no Histórico -->
        <form method="POST" action="relatorios.php" style="padding: 20px 24px; background: #fffbeb; border-top: 1px solid #fde68a; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <input type="hidden" name="acao" value="salvar_fechamento">
            <input type="hidden" name="case_id" value="<?= $estojoAtual['id'] ?>">
            <input type="hidden" name="data_inicial" value="<?= sanitize($selDataInicio) ?>">
            <input type="hidden" name="data_final" value="<?= sanitize($selDataFim) ?>">
            <input type="hidden" name="total_pecas_vendidas" value="<?= $totalQtdVendida ?>">
            <input type="hidden" name="total_pecas_restantes" value="<?= $totalEstoqueRestante ?>">
            <input type="hidden" name="valor_total_vendido" value="<?= $totalVendidoEstojo ?>">
            <input type="hidden" name="comissao_vendedora_pct" value="<?= $comissaoVendedoraPct ?>">
            <input type="hidden" name="valor_comissao_vendedora" value="<?= $valorComissaoVendedora ?>">
            <input type="hidden" name="comissao_proprietaria_pct" value="<?= $comissaoProprietariaPct ?>">
            <input type="hidden" name="valor_repasse" value="<?= $valorRepasseProprietaria ?>">

            <div style="flex: 1; min-width: 240px;">
                <input type="text" name="observacoes" class="form-control" placeholder="Observações opcionais do fechamento...">
            </div>

            <button type="submit" class="btn btn-primary" onclick="return confirm('Confirma o registro deste fechamento de estojo no histórico?')">
                <i class="bi bi-save-fill"></i> Salvar Fechamento no Histórico
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- ============================================================
     HISTÓRICO DE FECHAMENTOS ANTERIORES
     ============================================================ -->
<div class="content-card">
    <div class="content-card-header">
        <h3 class="card-heading">
            <i class="bi bi-clock-history" style="margin-right: 8px;"></i>
            Histórico de Fechamentos de Estojo Realizados
        </h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Data Fechamento</th>
                    <th>Estojo</th>
                    <th>Vendedora</th>
                    <th>Período</th>
                    <th>Peças Vendidas</th>
                    <th>Total Vendido</th>
                    <th>Comissão Vendedora</th>
                    <th>Valor Repasse</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historicoFechamentos)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--admin-muted); padding: 32px;">
                            Nenhum fechamento registrado no histórico ainda.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historicoFechamentos as $hf): 
                        $nomeEst = $hf['case_id'] && isset($mapEstojos[$hf['case_id']]) ? $mapEstojos[$hf['case_id']]['nome'] : 'Estojo';
                        $nomeVend = $hf['seller_id'] && isset($mapVendedoras[$hf['seller_id']]) ? $mapVendedoras[$hf['seller_id']]['nome'] : '-';
                    ?>
                        <tr>
                            <td><?= formatDateTime($hf['created_at'] ?? '') ?></td>
                            <td><strong><?= sanitize($nomeEst) ?></strong></td>
                            <td><?= sanitize($nomeVend) ?></td>
                            <td>
                                <small><?= formatDateOnly($hf['data_inicial'] ?? '') ?> a <?= formatDateOnly($hf['data_final'] ?? '') ?></small>
                            </td>
                            <td><?= (int)($hf['total_pecas_vendidas'] ?? 0) ?> peças</td>
                            <td><strong><?= formatMoney($hf['valor_total_vendido'] ?? 0) ?></strong></td>
                            <td><span style="color: #059669; font-weight: 600;"><?= formatMoney($hf['valor_comissao_vendedora'] ?? 0) ?></span></td>
                            <td><strong style="color: var(--admin-primary-dark);"><?= formatMoney($hf['valor_repasse'] ?? 0) ?></strong></td>
                            <td><span class="badge badge-concluido"><?= sanitize($hf['status'] ?? 'Fechado') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
