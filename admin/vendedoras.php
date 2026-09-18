<?php
/**
 * Gestão de Vendedoras (sellers)
 */

$pageTitle = 'Gestão de Vendedoras';
require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$supabase = Supabase::getInstance();

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Alternar Status Ativo / Inativo
    if ($acao === 'alterar_status') {
        $id = $_POST['id'] ?? null;
        $novoStatus = ($_POST['ativo'] ?? '1') === '1';
        if ($id) {
            $supabase->update('sellers', ['ativo' => $novoStatus], ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Status da vendedora atualizado com sucesso.');
        }
        header('Location: vendedoras.php');
        exit;
    }

    // 2. Excluir Vendedora
    if ($acao === 'excluir') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $estojosVinculados = $supabase->select('cases', '*', ['seller_id' => 'eq.' . $id]);
            if (!empty($estojosVinculados)) {
                $supabase->update('sellers', ['ativo' => false], ['id' => 'eq.' . $id]);
                setFlashMessage('warning', 'A vendedora possui estojos vinculados e foi desativada em vez de excluída.');
            } else {
                $supabase->delete('sellers', ['id' => 'eq.' . $id]);
                setFlashMessage('success', 'Vendedora excluída com sucesso.');
            }
        }
        header('Location: vendedoras.php');
        exit;
    }

    // 3. Salvar (Novo ou Editar)
    if ($acao === 'salvar') {
        $id = !empty($_POST['id']) ? $_POST['id'] : null;
        $nome = trim($_POST['nome'] ?? '');
        $whatsapp = sanitizeWhatsApp($_POST['whatsapp'] ?? '');
        $ativo = ($_POST['ativo'] ?? '1') === '1';
        $fotoUrl = trim($_POST['foto_url'] ?? '');

        // Upload de Foto para Supabase Storage
        if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'vend_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
            $content = file_get_contents($file['tmp_name']);
            $uploaded = $supabase->uploadFile(SUPABASE_STORAGE_BUCKET, $filename, $content, $file['type']);
            if ($uploaded) {
                $fotoUrl = $uploaded;
            }
        }

        if (empty($nome) || empty($whatsapp)) {
            setFlashMessage('error', 'Nome e WhatsApp são obrigatórios.');
            header('Location: vendedoras.php');
            exit;
        }

        $dados = [
            'nome' => $nome,
            'whatsapp' => $whatsapp,
            'ativo' => $ativo,
            'foto_url' => $fotoUrl
        ];

        if ($id) {
            $supabase->update('sellers', $dados, ['id' => 'eq.' . $id]);
            setFlashMessage('success', 'Vendedora "' . $nome . '" atualizada com sucesso!');
        } else {
            $dados['created_at'] = date('c');
            $supabase->insert('sellers', $dados);
            setFlashMessage('success', 'Vendedora "' . $nome . '" cadastrada com sucesso!');
        }

        header('Location: vendedoras.php');
        exit;
    }
}

$vendedoras = $supabase->select('sellers', '*', [], 'nome.asc');
$estojos = $supabase->select('cases');

// Contagem de estojos por vendedora
$estojosPorVendedora = [];
foreach ($estojos as $e) {
    $sId = $e['seller_id'] ?? '';
    if ($sId) {
        $estojosPorVendedora[$sId] = ($estojosPorVendedora[$sId] ?? 0) + 1;
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Cabeçalho com Botão de Novo Cadastro -->
<div class="content-card" style="margin-bottom: 20px;">
    <div class="content-card-header">
        <div>
            <p style="color: var(--admin-muted); font-size: 0.88rem; margin: 0;">
                Gerencie as vendedoras responsáveis pelo atendimento no WhatsApp e custódia de estojos. <strong>Somente vendedoras ativas</strong> aparecem para escolha do cliente no carrinho.
            </p>
        </div>
        <button type="button" class="btn btn-primary" onclick="abrirModalVendedora()">
            <i class="bi bi-person-plus-fill"></i> Nova Vendedora
        </button>
    </div>
</div>

<!-- Listagem de Vendedoras -->
<div class="content-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Foto</th>
                    <th>Nome</th>
                    <th>WhatsApp de Atendimento</th>
                    <th>Estojos Sob Responsabilidade</th>
                    <th>Status</th>
                    <th>Testar WhatsApp</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendedoras)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--admin-muted); padding: 36px;">
                            Nenhuma vendedora cadastrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendedoras as $v): 
                        $isAtiva = !empty($v['ativo']);
                        $foto = !empty($v['foto_url']) ? $v['foto_url'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80';
                        $zapLimpo = sanitizeWhatsApp($v['whatsapp']);
                        $qtdEstojos = $estojosPorVendedora[$v['id']] ?? 0;
                    ?>
                        <tr>
                            <td>
                                <img src="<?= sanitize($foto) ?>" alt="<?= sanitize($v['nome']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1px solid var(--admin-border);">
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem;"><?= sanitize($v['nome']) ?></strong>
                            </td>
                            <td>
                                <code><?= formatPhone($v['whatsapp']) ?></code>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #855b08;">
                                    <i class="bi bi-briefcase"></i> <?= $qtdEstojos ?> estojo(s)
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $isAtiva ? 'badge-ativo' : 'badge-inativo' ?>">
                                    <?= $isAtiva ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <a href="https://wa.me/<?= $zapLimpo ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: #25d366; font-weight: 600;">
                                    <i class="bi bi-whatsapp"></i> Testar Chat
                                </a>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <!-- Alternar Status -->
                                    <form method="POST" action="vendedoras.php" style="display: inline;">
                                        <input type="hidden" name="acao" value="alterar_status">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                        <input type="hidden" name="ativo" value="<?= $isAtiva ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Alternar status Ativo/Inativo">
                                            <?= $isAtiva ? '<i class="bi bi-pause-circle"></i> Desativar' : '<i class="bi bi-play-circle"></i> Ativar' ?>
                                        </button>
                                    </form>

                                    <!-- Editar -->
                                    <button type="button" class="btn btn-secondary btn-icon" onclick='editarVendedora(<?= json_encode($v) ?>)' title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <!-- Excluir -->
                                    <form method="POST" action="vendedoras.php" style="display: inline;" onsubmit="return confirm('Deseja excluir ou desativar a vendedora <?= sanitize($v['nome']) ?>?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
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

<!-- Modal Vendedora -->
<div class="modal-backdrop" id="modalVendedora">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="modalVendTitulo">Nova Vendedora</h3>
            <button type="button" class="btn-close-modal" onclick="fecharModal('modalVendedora')">&times;</button>
        </div>

        <form method="POST" action="vendedoras.php" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="vendId" value="">
            <input type="hidden" name="foto_url" id="vendFotoUrl" value="">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label" for="vendNome">Nome da Vendedora <span style="color: red;">*</span></label>
                        <input type="text" id="vendNome" name="nome" class="form-control" placeholder="Ex: Gaby" required>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="vendWhatsApp">Telefone / WhatsApp <span style="color: red;">*</span></label>
                        <input type="tel" id="vendWhatsApp" name="whatsapp" class="form-control" placeholder="5567999887766 ou (67) 99999-9999" required>
                        <small style="color: var(--admin-muted); font-size: 0.8rem;">O sistema formata automaticamente com o código do país 55.</small>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="vendAtivo">Status</label>
                        <select id="vendAtivo" name="ativo" class="form-control">
                            <option value="1">Ativo (Aparece no carrinho da loja)</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label" for="vendFotoFile">Foto de Perfil (Opcional)</label>
                        <input type="file" id="vendFotoFile" name="foto_file" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalVendedora')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar Vendedora
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalVendedora() {
    document.getElementById('modalVendTitulo').textContent = 'Cadastrar Nova Vendedora';
    document.getElementById('vendId').value = '';
    document.getElementById('vendNome').value = '';
    document.getElementById('vendWhatsApp').value = '';
    document.getElementById('vendAtivo').value = '1';
    document.getElementById('vendFotoUrl').value = '';
    document.getElementById('vendFotoFile').value = '';
    abrirModal('modalVendedora');
}

function editarVendedora(vend) {
    document.getElementById('modalVendTitulo').textContent = 'Editar Vendedora: ' + vend.nome;
    document.getElementById('vendId').value = vend.id;
    document.getElementById('vendNome').value = vend.nome || '';
    document.getElementById('vendWhatsApp').value = vend.whatsapp || '';
    document.getElementById('vendAtivo').value = vend.ativo ? '1' : '0';
    document.getElementById('vendFotoUrl').value = vend.foto_url || '';
    document.getElementById('vendFotoFile').value = '';
    abrirModal('modalVendedora');
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
