<?php
/**
 * Gestão de Categorias
 */

$pageTitle = 'Gestão de Categorias';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = !empty($_POST['id']) ? $_POST['id'] : null;
        $nome = trim($_POST['nome'] ?? '');
        $ativo = ($_POST['ativo'] ?? '1') === '1';

        if (!empty($nome)) {
            if ($id) {
                $supabase->update('categories', ['nome' => $nome, 'ativo' => $ativo], ['id' => 'eq.' . $id]);
                setFlashMessage('success', 'Categoria atualizada com sucesso.');
            } else {
                $supabase->insert('categories', ['nome' => $nome, 'ativo' => $ativo, 'created_at' => date('c')]);
                setFlashMessage('success', 'Categoria cadastrada com sucesso.');
            }
        }
        header('Location: categorias.php');
        exit;
    }

    if ($acao === 'excluir') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $supabase->delete('categories', ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Categoria excluída.');
        }
        header('Location: categorias.php');
        exit;
    }
}

$categorias = $supabase->select('categories', '*', [], 'nome.asc');
$produtos = $supabase->select('products');

// Contagem de produtos por categoria
$contagem = [];
foreach ($produtos as $p) {
    $cat = $p['categoria'] ?? 'Outros';
    $contagem[$cat] = ($contagem[$cat] ?? 0) + 1;
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="content-card" style="margin-bottom: 20px;">
    <div class="content-card-header">
        <p style="color: var(--admin-muted); font-size: 0.88rem;">
            Organize os tipos de peças exibidas na vitrine e nos filtros administrativos.
        </p>
        <button type="button" class="btn btn-primary" onclick="abrirModalCategoria()">
            <i class="bi bi-plus-lg"></i> Nova Categoria
        </button>
    </div>
</div>

<div class="content-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome da Categoria</th>
                    <th>Qtd. de Produtos Cadastrados</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--admin-muted); padding: 32px;">
                            Nenhuma categoria cadastrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categorias as $c): 
                        $isAtiva = !empty($c['ativo']);
                        $qtdProd = $contagem[$c['nome']] ?? 0;
                    ?>
                        <tr>
                            <td><strong><?= sanitize($c['nome']) ?></strong></td>
                            <td><?= $qtdProd ?> produto(s)</td>
                            <td>
                                <span class="badge <?= $isAtiva ? 'badge-ativo' : 'badge-inativo' ?>">
                                    <?= $isAtiva ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-secondary btn-icon" onclick='editarCategoria(<?= json_encode($c) ?>)' title="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="categorias.php" style="display: inline;" onsubmit="return confirm('Deseja excluir a categoria <?= sanitize($c['nome']) ?>?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-icon" style="color: #ef4444;" title="Excluir">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Categoria -->
<div class="modal-backdrop" id="modalCategoria">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalCatTitulo">Nova Categoria</h3>
            <button type="button" class="btn-close-modal">&times;</button>
        </div>
        <form method="POST" action="categorias.php">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="catId" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="catNome">Nome da Categoria</label>
                    <input type="text" id="catNome" name="nome" class="form-control" placeholder="Ex: Gargantilhas, Conjuntos..." required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="catAtivo">Status</label>
                    <select id="catAtivo" name="ativo" class="form-control">
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalCategoria')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCategoria() {
    document.getElementById('modalCatTitulo').textContent = 'Nova Categoria';
    document.getElementById('catId').value = '';
    document.getElementById('catNome').value = '';
    document.getElementById('catAtivo').value = '1';
    abrirModal('modalCategoria');
}

function editarCategoria(cat) {
    document.getElementById('modalCatTitulo').textContent = 'Editar Categoria';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catNome').value = cat.nome || '';
    document.getElementById('catAtivo').value = cat.ativo ? '1' : '0';
    abrirModal('modalCategoria');
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
