<?php
/**
 * Cadastro de Novo Produto (com campo obrigatório de Estojo)
 */

$pageTitle = 'Novo Produto';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// Carrega Estojos Ativos diretamente do Supabase
$estojosAtivos = $supabase->select('cases', '*', ['ativo' => 'eq.true'], 'nome.asc');

// Carrega Categorias
$categorias = $supabase->select('categories', '*', ['ativo' => 'eq.true'], 'nome.asc');
if (empty($categorias)) {
    $listaCategorias = ['Brincos', 'Colares', 'Pulseiras', 'Anéis', 'Pingentes', 'Outros'];
} else {
    $listaCategorias = array_column($categorias, 'nome');
}

// Estojo pré-selecionado se vier via GET
$caseIdPreSelecionado = $_GET['case_id'] ?? '';

// Processamento do Formulário (POST)
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

    // Validação obrigatória de Estojo
    if (empty($caseId)) {
        setFlashMessage('error', 'A seleção do Estojo é obrigatória.');
        header('Location: produto-novo.php');
        exit;
    }

    if (empty($nome)) {
        setFlashMessage('error', 'O nome do produto é obrigatório.');
        header('Location: produto-novo.php');
        exit;
    }

    // Upload de Imagem para Supabase Storage se enviada
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

    $produtoData = [
        'codigo' => !empty($codigo) ? $codigo : ('PECA-' . strtoupper(substr(uniqid(), -4))),
        'nome' => $nome,
        'descricao' => $descricao,
        'categoria' => $categoria,
        'preco' => $preco,
        'case_id' => $caseId,
        'imagem_url' => $imagemUrl,
        'estoque' => $estoque,
        'status' => $status,
        'ativo' => true,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];

    $res = $supabase->insert('products', $produtoData);

    if ($res) {
        setFlashMessage('success', 'Produto "' . $nome . '" cadastrado e vinculado ao estojo com sucesso!');
        header('Location: produtos.php');
        exit;
    } else {
        setFlashMessage('error', 'Erro ao salvar o produto no Supabase.');
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="content-card" style="max-width: 860px; margin: 0 auto;">
    <div class="content-card-header">
        <h2 class="card-heading">
            <i class="bi bi-plus-circle-fill text-primary" style="margin-right: 8px;"></i>
            Cadastrar Novo Produto
        </h2>
        <a href="produtos.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar à Lista
        </a>
    </div>

    <form method="POST" action="produto-novo.php" enctype="multipart/form-data" style="padding: 24px;">
        <div class="form-grid">
            
            <!-- Campo Obrigatório: ESTOJO -->
            <div class="form-group col-span-2" style="background: #fdf8eb; padding: 16px; border-radius: 8px; border: 1px solid #f9df98;">
                <label class="form-label" for="caseId" style="font-size: 0.95rem; font-weight: 700; color: #855b08;">
                    <i class="bi bi-briefcase-fill"></i> Selecione o Estojo (Obrigatório) <span style="color: red;">*</span>
                </label>
                <select id="caseId" name="case_id" class="form-control" required style="font-size: 0.95rem; font-weight: 600;">
                    <option value="">-- Selecione o estojo responsável --</option>
                    <?php if (empty($estojosAtivos)): ?>
                        <option value="" disabled>Nenhum estojo ativo cadastrado. Cadastre um estojo primeiro!</option>
                    <?php else: ?>
                        <?php foreach ($estojosAtivos as $est): ?>
                            <option value="<?= $est['id'] ?>" <?= $caseIdPreSelecionado === $est['id'] ? 'selected' : '' ?>>
                                📁 <?= sanitize($est['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small style="color: #855b08; font-size: 0.8rem; margin-top: 4px; display: block;">
                    Somente estojos ativos aparecem nesta lista. Cada peça cadastrada pertence obrigatoriamente a um estojo.
                </small>
            </div>

            <!-- Código SKU -->
            <div class="form-group">
                <label class="form-label" for="prodCodigo">Código do Produto (SKU)</label>
                <input type="text" id="prodCodigo" name="codigo" class="form-control" placeholder="Ex: BR-001, COL-002">
            </div>

            <!-- Categoria -->
            <div class="form-group">
                <label class="form-label" for="prodCategoria">Categoria <span style="color: red;">*</span></label>
                <select id="prodCategoria" name="categoria" class="form-control" required>
                    <?php foreach ($listaCategorias as $cat): ?>
                        <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Nome / Título -->
            <div class="form-group col-span-2">
                <label class="form-label" for="prodNome">Nome / Título do Produto <span style="color: red;">*</span></label>
                <input type="text" id="prodNome" name="nome" class="form-control" placeholder="Ex: Brinco Dourado Argola Elegance" required>
            </div>

            <!-- Descrição -->
            <div class="form-group col-span-2">
                <label class="form-label" for="prodDescricao">Descrição da Peça</label>
                <textarea id="prodDescricao" name="descricao" class="form-control" rows="3" placeholder="Detalhes da peça, banho a ouro 18k, tamanho, pedrarias..."></textarea>
            </div>

            <!-- Preço -->
            <div class="form-group">
                <label class="form-label" for="prodPreco">Preço (R$) <span style="color: red;">*</span></label>
                <input type="text" id="prodPreco" name="preco" class="form-control" placeholder="35,00" required>
            </div>

            <!-- Estoque -->
            <div class="form-group">
                <label class="form-label" for="prodEstoque">Estoque Disponível <span style="color: red;">*</span></label>
                <input type="number" id="prodEstoque" name="estoque" class="form-control" min="0" value="1" required>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label class="form-label" for="prodStatus">Status do Produto</label>
                <select id="prodStatus" name="status" class="form-control">
                    <option value="Disponível">Disponível</option>
                    <option value="Indisponível">Indisponível</option>
                    <option value="Vendido">Vendido</option>
                </select>
            </div>

            <!-- URL Direta da Imagem (Opcional) -->
            <div class="form-group">
                <label class="form-label" for="prodImagemUrl">URL da Imagem (ou envie abaixo)</label>
                <input type="url" id="prodImagemUrl" name="imagem_url" class="form-control" placeholder="https://...">
            </div>

            <!-- Upload para Supabase Storage -->
            <div class="form-group col-span-2">
                <label class="form-label" for="prodImagemFile">Upload de Foto (Supabase Storage)</label>
                <input type="file" id="prodImagemFile" name="imagem_file" class="form-control" accept="image/*">
                <small style="color: var(--admin-muted); font-size: 0.8rem;">Envie JPG, PNG ou WEBP. A foto será armazenada no bucket de Storage e a URL gravada no produto.</small>
            </div>

        </div>

        <div style="margin-top: 28px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--admin-border); padding-top: 20px;">
            <a href="produtos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="bi bi-check-lg"></i>
                <span>Salvar e Vincular ao Estojo</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
