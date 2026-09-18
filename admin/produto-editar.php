<?php
/**
 * Edição de Produto (com alteração de Estojo)
 */

$pageTitle = 'Editar Produto';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: produtos.php');
    exit;
}

$produto = $supabase->find('products', $id);
if (!$produto) {
    setFlashMessage('error', 'Produto não encontrado.');
    header('Location: produtos.php');
    exit;
}

$estojosAtivos = $supabase->select('cases', '*', ['ativo' => 'eq.true'], 'nome.asc');
$categorias = $supabase->select('categories', '*', ['ativo' => 'eq.true'], 'nome.asc');
$listaCategorias = !empty($categorias) ? array_column($categorias, 'nome') : ['Brincos', 'Colares', 'Pulseiras', 'Anéis', 'Pingentes', 'Outros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'Outros');
    $caseId = trim($_POST['case_id'] ?? '');
    $precoRaw = str_replace(['R$', '.', ' '], '', $_POST['preco'] ?? '0');
    $preco = (float)str_replace(',', '.', $precoRaw);
    $estoque = max(0, (int)($_POST['estoque'] ?? 1));
    $status = $_POST['status'] ?? 'Disponível';
    $imagemUrl = trim($_POST['imagem_url'] ?? '');

    if (empty($caseId)) {
        setFlashMessage('error', 'A seleção do Estojo é obrigatória.');
        header('Location: produto-editar.php?id=' . $id);
        exit;
    }

    if (empty($nome)) {
        setFlashMessage('error', 'O nome do produto é obrigatório.');
        header('Location: produto-editar.php?id=' . $id);
        exit;
    }

    if (isset($_FILES['imagem_file']) && $_FILES['imagem_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imagem_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
        $content = file_get_contents($file['tmp_name']);
        $uploaded = $supabase->uploadFile(SUPABASE_STORAGE_BUCKET, $filename, $content, $file['type']);
        if ($uploaded) {
            $imagemUrl = $uploaded;
        }
    }

    $dados = [
        'codigo' => $codigo,
        'nome' => $nome,
        'descricao' => $descricao,
        'categoria' => $categoria,
        'preco' => $preco,
        'case_id' => $caseId,
        'imagem_url' => $imagemUrl,
        'estoque' => $estoque,
        'status' => $status,
        'updated_at' => date('c')
    ];

    $supabase->update('products', $dados, ['id' => 'eq.' . $id]);
    setFlashMessage('success', 'Produto "' . $nome . '" atualizado com sucesso!');
    header('Location: produtos.php');
    exit;
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="content-card" style="max-width: 860px; margin: 0 auto;">
    <div class="content-card-header">
        <h2 class="card-heading">
            <i class="bi bi-pencil-square text-primary" style="margin-right: 8px;"></i>
            Editar Produto: <?= sanitize($produto['nome']) ?>
        </h2>
        <a href="produtos.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar à Lista
        </a>
    </div>

    <form method="POST" action="produto-editar.php?id=<?= $produto['id'] ?>" enctype="multipart/form-data" style="padding: 24px;">
        <div class="form-grid">
            
            <!-- Campo Obrigatório: ESTOJO -->
            <div class="form-group col-span-2" style="background: #fdf8eb; padding: 16px; border-radius: 8px; border: 1px solid #f9df98;">
                <label class="form-label" for="caseId" style="font-size: 0.95rem; font-weight: 700; color: #855b08;">
                    <i class="bi bi-briefcase-fill"></i> Estojo Vinculado (Obrigatório) <span style="color: red;">*</span>
                </label>
                <select id="caseId" name="case_id" class="form-control" required style="font-size: 0.95rem; font-weight: 600;">
                    <option value="">-- Selecione o estojo responsável --</option>
                    <?php foreach ($estojosAtivos as $est): ?>
                        <option value="<?= $est['id'] ?>" <?= ($produto['case_id'] ?? '') === $est['id'] ? 'selected' : '' ?>>
                            📁 <?= sanitize($est['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Código SKU -->
            <div class="form-group">
                <label class="form-label" for="prodCodigo">Código do Produto (SKU)</label>
                <input type="text" id="prodCodigo" name="codigo" class="form-control" value="<?= sanitize($produto['codigo'] ?? '') ?>">
            </div>

            <!-- Categoria -->
            <div class="form-group">
                <label class="form-label" for="prodCategoria">Categoria</label>
                <select id="prodCategoria" name="categoria" class="form-control" required>
                    <?php foreach ($listaCategorias as $cat): ?>
                        <option value="<?= sanitize($cat) ?>" <?= ($produto['categoria'] ?? '') === $cat ? 'selected' : '' ?>><?= sanitize($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Nome / Título -->
            <div class="form-group col-span-2">
                <label class="form-label" for="prodNome">Nome / Título do Produto <span style="color: red;">*</span></label>
                <input type="text" id="prodNome" name="nome" class="form-control" value="<?= sanitize($produto['nome']) ?>" required>
            </div>

            <!-- Descrição -->
            <div class="form-group col-span-2">
                <label class="form-label" for="prodDescricao">Descrição da Peça</label>
                <textarea id="prodDescricao" name="descricao" class="form-control" rows="3"><?= sanitize($produto['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Preço -->
            <div class="form-group">
                <label class="form-label" for="prodPreco">Preço (R$) <span style="color: red;">*</span></label>
                <input type="text" id="prodPreco" name="preco" class="form-control" value="<?= number_format((float)($produto['preco'] ?? 0), 2, ',', '.') ?>" required>
            </div>

            <!-- Estoque -->
            <div class="form-group">
                <label class="form-label" for="prodEstoque">Estoque Disponível</label>
                <input type="number" id="prodEstoque" name="estoque" class="form-control" min="0" value="<?= (int)($produto['estoque'] ?? 0) ?>" required>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label class="form-label" for="prodStatus">Status do Produto</label>
                <select id="prodStatus" name="status" class="form-control">
                    <option value="Disponível" <?= ($produto['status'] ?? '') === 'Disponível' ? 'selected' : '' ?>>Disponível</option>
                    <option value="Indisponível" <?= ($produto['status'] ?? '') === 'Indisponível' ? 'selected' : '' ?>>Indisponível</option>
                    <option value="Vendido" <?= ($produto['status'] ?? '') === 'Vendido' ? 'selected' : '' ?>>Vendido</option>
                </select>
            </div>

            <!-- Imagem Atual / Upload -->
            <div class="form-group">
                <label class="form-label" for="prodImagemUrl">URL da Foto</label>
                <input type="url" id="prodImagemUrl" name="imagem_url" class="form-control" value="<?= sanitize($produto['imagem_url'] ?? '') ?>">
            </div>

            <div class="form-group col-span-2">
                <label class="form-label" for="prodImagemFile">Substituir Imagem (Upload Supabase Storage)</label>
                <input type="file" id="prodImagemFile" name="imagem_file" class="form-control" accept="image/*">
            </div>

            <?php if (!empty($produto['imagem_url'])): ?>
                <div class="form-group col-span-2">
                    <label class="form-label">Foto Atual:</label>
                    <img src="<?= sanitize($produto['imagem_url']) ?>" style="max-height: 120px; border-radius: 8px; border: 1px solid var(--admin-border); object-fit: cover;">
                </div>
            <?php endif; ?>

        </div>

        <div style="margin-top: 28px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--admin-border); padding-top: 20px;">
            <a href="produtos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="bi bi-check-lg"></i>
                <span>Atualizar Produto</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
