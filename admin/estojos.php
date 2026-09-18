<?php
/**
 * Gestão de Estojos (Cases)
 * VENDEDORA -> ESTOJO -> PRODUTOS
 */

$pageTitle = 'Gestão de Estojos';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. ALTERAR STATUS ATIVO / INATIVO
    if ($acao === 'alterar_status') {
        $id = $_POST['id'] ?? null;
        $novoStatus = ($_POST['ativo'] ?? '1') === '1';
        if ($id) {
            $supabase->update('cases', ['ativo' => $novoStatus], ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Status do estojo atualizado com sucesso.');
        }
        header('Location: estojos.php');
        exit;
    }

    // 2. EXCLUIR / DESATIVAR ESTOJO
    if ($acao === 'excluir') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            // Verifica se possui produtos antes de excluir
            $produtosEstojo = $supabase->select('products', '*', ['case_id' => 'eq.' . $id]);
            if (!empty($produtosEstojo)) {
                // Desativa por segurança
                $supabase->update('cases', ['ativo' => false], ['id' => 'eq.' . $id]);
                setFlashMessage('warning', 'O estojo possui produtos vinculados e foi desativado em vez de excluído.');
            } else {
                $supabase->delete('cases', ['id' => 'eq.' . $id]);
                setFlashMessage('success', 'Estojo excluído com sucesso.');
            }
        }
        header('Location: estojos.php');
        exit;
    }

    // 3. SALVAR (NOVO OU EDITAR)
    if ($acao === 'salvar') {
        $id = !empty($_POST['id']) ? $_POST['id'] : null;
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $sellerId = $_POST['seller_id'] ?? null;
        $ativo = ($_POST['ativo'] ?? '1') === '1';

        if (empty($nome)) {
            setFlashMessage('error', 'O nome do estojo é obrigatório.');
            header('Location: estojos.php');
            exit;
        }

        $dados = [
            'nome' => $nome,
            'descricao' => $descricao,
            'seller_id' => $sellerId ?: null,
            'ativo' => $ativo
        ];

        if ($id) {
            $supabase->update('cases', $dados, ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Estojo "' . $nome . '" atualizado com sucesso!');
        } else {
            $dados['created_at'] = date('c');
            $supabase->insert('cases', $dados);
            setFlashMessage('success', 'Novo estojo "' . $nome . '" cadastrado com sucesso!');
        }

        header('Location: estojos.php');
        exit;
    }
}

// Carrega Dados do Supabase
$estojos = $supabase->select('cases', '*', [], 'created_at.desc');
$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$produtos = $supabase->select('products');
$itensVendidos = $supabase->select('sale_items');

// Mapeia Vendedoras por ID
$mapVendedoras = [];
foreach ($vendedoras as $v) {
    $mapVendedoras[$v['id']] = $v;
}

// Calcula Métricas de cada Estojo
$metricasEstojos = [];
foreach ($estojos as $estojo) {
    $eId = $estojo['id'];
    
    // Produtos pertencentes ao estojo
    $pecasEstojo = array_filter($produtos, fn($p) => ($p['case_id'] ?? '') === $eId);
    $totalPecas = count($pecasEstojo);

    // Peças vendidas e valor faturado deste estojo
    $qtdVendida = 0;
    $valorVendido = 0.0;

    foreach ($itensVendidos as $iv) {
        if (($iv['case_id'] ?? '') === $eId) {
            $qtd = (int)($iv['quantidade'] ?? 1);
            $sub = (float)($iv['subtotal'] ?? 0);
            $qtdVendida += $qtd;
            $valorVendido += $sub;
        }
    }

    $metricasEstojos[$eId] = [
        'total_pecas' => $totalPecas,
        'qtd_vendida' => $qtdVendida,
        'valor_vendido' => $valorVendido,
        'pecas' => array_values($pecasEstojo)
    ];
}

// Se foi solicitado visualizar produtos de um estojo específico via modal/GET
$verEstojoId = $_GET['ver'] ?? null;
$estojoSelecionado = null;
$produtosDoEstojo = [];

if ($verEstojoId) {
    foreach ($estojos as $e) {
        if ($e['id'] === $verEstojoId) {
            $estojoSelecionado = $e;
            $produtosDoEstojo = $metricasEstojos[$verEstojoId]['pecas'] ?? [];
            break;
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Barra de Ações Superior -->
<div class="content-card" style="margin-bottom: 20px;">
    <div class="content-card-header" style="flex-wrap: wrap; gap: 14px;">
        <div>
            <p style="color: var(--admin-muted); font-size: 0.88rem;">
                Gerenciamento hierárquico: <strong>Vendedora ➔ Estojo ➔ Produtos</strong>. Acompanhe a circulação de peças e faturamento por estojo.
            </p>
        </div>
        <button type="button" class="btn btn-primary" onclick="abrirModalEstojo()">
            <i class="bi bi-briefcase-fill"></i>
            <span>Cadastrar Novo Estojo</span>
        </button>
    </div>
</div>

<!-- Listagem de Estojos -->
<div class="content-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Estojo</th>
                    <th>Vendedora Responsável</th>
                    <th>Qtd. Peças</th>
                    <th>Peças Vendidas</th>
                    <th>Valor Vendido</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($estojos)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 36px;">
                            Nenhum estojo cadastrado no momento. Clique em "Cadastrar Novo Estojo" acima.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($estojos as $e): 
                        $eId = $e['id'];
                        $vId = $e['seller_id'] ?? null;
                        $vendedora = $vId && isset($mapVendedoras[$vId]) ? $mapVendedoras[$vId] : null;
                        $metrica = $metricasEstojos[$eId] ?? ['total_pecas' => 0, 'qtd_vendida' => 0, 'valor_vendido' => 0.0];
                        $isAtivo = !empty($e['ativo']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($e['nome']) ?></strong>
                                <?php if (!empty($e['descricao'])): ?>
                                    <br><small style="color: var(--admin-muted);"><?= sanitize($e['descricao']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vendedora): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                                        <i class="bi bi-person-check text-primary" style="color: var(--admin-primary);"></i>
                                        <?= sanitize($vendedora['nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--admin-muted); font-style: italic;">Não vinculada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $metrica['total_pecas'] ?></strong> peças
                            </td>
                            <td>
                                <span style="font-weight: 700; color: #059669;"><?= $metrica['qtd_vendida'] ?></span> vendida(s)
                            </td>
                            <td>
                                <strong style="color: var(--admin-primary-dark); font-size: 0.95rem;">
                                    <?= formatMoney($metrica['valor_vendido']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge <?= $isAtivo ? 'badge-ativo' : 'badge-inativo' ?>">
                                    <?= $isAtivo ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <small style="color: var(--admin-muted);"><?= formatDateOnly($e['created_at'] ?? '') ?></small>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <!-- Ver Produtos do Estojo -->
                                    <a href="estojos.php?ver=<?= $e['id'] ?>" class="btn btn-secondary btn-sm" title="Visualizar peças deste estojo">
                                        <i class="bi bi-gem"></i> Peças (<?= $metrica['total_pecas'] ?>)
                                    </a>

                                    <!-- Alternar Status Ativo / Inativo -->
                                    <form method="POST" action="estojos.php" style="display: inline;">
                                        <input type="hidden" name="acao" value="alterar_status">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <input type="hidden" name="ativo" value="<?= $isAtivo ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Alternar status do estojo">
                                            <?= $isAtivo ? '<i class="bi bi-pause-circle"></i> Desativar' : '<i class="bi bi-play-circle"></i> Ativar' ?>
                                        </button>
                                    </form>

                                    <!-- Editar -->
                                    <button type="button" class="btn btn-secondary btn-icon" onclick='editarEstojo(<?= json_encode($e) ?>)' title="Editar Estojo">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Excluir -->
                                    <form method="POST" action="estojos.php" style="display: inline;" onsubmit="return confirm('Deseja excluir ou desativar o estojo <?= sanitize($e['nome']) ?>?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-icon" style="color: #ef4444;" title="Excluir Estojo">
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

<!-- MODAL DE CADASTRO E EDIÇÃO DE ESTOJO -->
<div class="modal-backdrop" id="modalEstojo">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="modalEstojoTitulo">Cadastrar Novo Estojo</h3>
            <button type="button" class="btn-close-modal" aria-label="Fechar">&times;</button>
        </div>

        <form method="POST" action="estojos.php">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="estojoId" value="">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label" for="estojoNome">Nome do Estojo <span style="color: red;">*</span></label>
                        <input type="text" id="estojoNome" name="nome" class="form-control" placeholder="Ex: Estojo Gaby ou Estojo Mostruário" required>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="estojoVendedora">Vendedora Responsável <span style="color: red;">*</span></label>
                        <select id="estojoVendedora" name="seller_id" class="form-control" required>
                            <option value="">-- Selecione a Vendedora --</option>
                            <?php foreach ($vendedoras as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= sanitize($v['nome']) ?> (WhatsApp: <?= formatPhone($v['whatsapp']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="estojoDescricao">Descrição / Observações</label>
                        <textarea id="estojoDescricao" name="descricao" class="form-control" rows="3" placeholder="Finalidade do estojo, região de atendimento ou especificações..."></textarea>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="estojoAtivo">Status</label>
                        <select id="estojoAtivo" name="ativo" class="form-control">
                            <option value="1">Ativo (Permite vincular e exibir produtos)</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEstojo')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i>
                    <span>Salvar Estojo</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA VISUALIZAR PRODUTOS DO ESTOJO -->
<?php if ($estojoSelecionado): ?>
    <div class="modal-backdrop show" id="modalProdutosEstojo">
        <div class="modal-dialog" style="max-width: 750px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="bi bi-briefcase" style="margin-right: 8px;"></i>
                    Peças no <?= sanitize($estojoSelecionado['nome']) ?>
                </h3>
                <a href="estojos.php" class="btn-close-modal">&times;</a>
            </div>

            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <strong>Total:</strong> <?= count($produtosDoEstojo) ?> produtos vinculados
                    </div>
                    <a href="produto-novo.php?case_id=<?= $estojoSelecionado['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Adicionar Peça a Este Estojo
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Código</th>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Preço</th>
                                <th>Estoque</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produtosDoEstojo)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--admin-muted); padding: 24px;">
                                        Nenhuma peça cadastrada neste estojo ainda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produtosDoEstojo as $pe): 
                                    $img = !empty($pe['imagem_url']) ? $pe['imagem_url'] : 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80';
                                    $st = strtolower($pe['status'] ?? 'disponível');
                                ?>
                                    <tr>
                                        <td>
                                            <img src="<?= sanitize($img) ?>" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover;">
                                        </td>
                                        <td><code><?= sanitize($pe['codigo'] ?? '-') ?></code></td>
                                        <td><strong><?= sanitize($pe['nome']) ?></strong></td>
                                        <td><?= sanitize($pe['categoria'] ?? 'Outros') ?></td>
                                        <td><strong style="color: var(--admin-primary-dark);"><?= formatMoney($pe['preco']) ?></strong></td>
                                        <td><?= (int)($pe['estoque'] ?? 0) ?> un.</td>
                                        <td><span class="badge badge-<?= $st ?>"><?= sanitize($pe['status'] ?? 'Disponível') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <a href="estojos.php" class="btn btn-secondary">Fechar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function abrirModalEstojo() {
    document.getElementById('modalEstojoTitulo').textContent = 'Cadastrar Novo Estojo';
    document.getElementById('estojoId').value = '';
    document.getElementById('estojoNome').value = '';
    document.getElementById('estojoDescricao').value = '';
    document.getElementById('estojoVendedora').value = '';
    document.getElementById('estojoAtivo').value = '1';
    abrirModal('modalEstojo');
}

function editarEstojo(est) {
    document.getElementById('modalEstojoTitulo').textContent = 'Editar Estojo: ' + est.nome;
    document.getElementById('estojoId').value = est.id;
    document.getElementById('estojoNome').value = est.nome || '';
    document.getElementById('estojoDescricao').value = est.descricao || '';
    document.getElementById('estojoVendedora').value = est.seller_id || '';
    document.getElementById('estojoAtivo').value = est.ativo ? '1' : '0';
    abrirModal('modalEstojo');
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
