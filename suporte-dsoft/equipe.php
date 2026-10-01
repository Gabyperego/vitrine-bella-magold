<?php
/**
 * Gestão da Equipe de Suporte — dsoft
 * Painel administrativo para cadastro, edição de e-mail e redefinição de senha de analistas
 */

$pageTitle = 'Equipe de Suporte';
require_once __DIR__ . '/partials/header.php';

$db = Database::getInstance();
$usuarioAtual = currentUser();

// ==========================================
// AÇÕES POST: CADASTRAR, EDITAR OU EXCLUIR
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Cadastrar Novo Analista
    if ($acao === 'novo_analista') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $cargo = trim($_POST['cargo'] ?? 'Analista de Suporte');

        if (empty($nome) || empty($email) || empty($senha)) {
            setFlash('error', 'Nome, e-mail e senha são obrigatórios.');
        } else {
            $existente = $db->findAnalistaByEmail($email);
            if ($existente) {
                setFlash('error', 'Já existe um analista cadastrado com este e-mail.');
            } else {
                $partes = explode(' ', $nome);
                $avatar = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : substr($partes[0], 1, 1)));
                $db->addAnalista([
                    'nome' => $nome,
                    'email' => $email,
                    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    'cargo' => $cargo,
                    'avatar' => $avatar,
                    'ativo' => true
                ]);
                setFlash('success', "Analista '{$nome}' cadastrado(a) com sucesso!");
                header('Location: equipe.php');
                exit;
            }
        }
    }

    // 2. Editar Analista (Nome, E-mail, Cargo e Senha)
    if ($acao === 'editar_analista') {
        $id = trim($_POST['id'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cargo = trim($_POST['cargo'] ?? 'Analista de Suporte');
        $novaSenha = trim($_POST['nova_senha'] ?? '');

        if (empty($id) || empty($nome) || empty($email)) {
            setFlash('error', 'Nome e e-mail não podem ficar vazios.');
        } else {
            // Verifica se o e-mail pertence a outro analista
            $outro = $db->findAnalistaByEmail($email);
            if ($outro && $outro['id'] !== $id) {
                setFlash('error', 'Este e-mail já está sendo utilizado por outro analista.');
            } else {
                $dadosAtualizar = [
                    'nome' => $nome,
                    'email' => $email,
                    'cargo' => $cargo
                ];

                // Se informou nova senha, atualiza o hash
                if (!empty($novaSenha)) {
                    if (strlen($novaSenha) < 4) {
                        setFlash('error', 'A nova senha deve ter no mínimo 4 caracteres.');
                        header('Location: equipe.php');
                        exit;
                    }
                    $dadosAtualizar['senha'] = $novaSenha;
                }

                $db->updateAnalista($id, $dadosAtualizar);

                // Se o usuário editou seu próprio perfil, atualiza a sessão
                if (($usuarioAtual['id'] ?? '') === $id) {
                    $_SESSION['dsoft_user']['nome'] = $nome;
                    $_SESSION['dsoft_user']['email'] = $email;
                    $_SESSION['dsoft_user']['cargo'] = $cargo;
                }

                setFlash('success', "Dados do analista '{$nome}' atualizados com sucesso!");
                header('Location: equipe.php');
                exit;
            }
        }
    }

    // 3. Excluir Analista
    if ($acao === 'excluir_analista') {
        $id = trim($_POST['id'] ?? '');
        if ($id === ($usuarioAtual['id'] ?? '')) {
            setFlash('error', 'Você não pode excluir sua própria conta enquanto estiver logado.');
        } else {
            $db->deleteAnalista($id);
            setFlash('success', 'Analista removido da equipe com sucesso.');
        }
        header('Location: equipe.php');
        exit;
    }
}

$analistas = $db->getAnalistas();
$todosTickets = $db->getTickets();

// Estatísticas por analista
$statsAnalistas = [];
foreach ($todosTickets as $t) {
    $nome = $t['analista_nome'] ?? '';
    if (!isset($statsAnalistas[$nome])) {
        $statsAnalistas[$nome] = ['total' => 0, 'abertos' => 0, 'concluidos' => 0];
    }
    $statsAnalistas[$nome]['total']++;
    if (($t['status'] ?? '') === 'Concluído') {
        $statsAnalistas[$nome]['concluidos']++;
    } else {
        $statsAnalistas[$nome]['abertos']++;
    }
}
?>

<!-- CABEÇALHO DA TELA -->
<div class="page-header-row">
    <div class="page-title-group">
        <h1>
            <i class="bi bi-people-fill" style="color: var(--dsoft-cyan);"></i>
            <span>Painel da Equipe de Suporte dsoft</span>
        </h1>
        <p>Gerencie analistas, altere e-mails de login, redefina senhas e acompanhe a produtividade.</p>
    </div>

    <div>
        <button type="button" class="btn-action-primary" onclick="abrirModalNovoAnalista()">
            <i class="bi bi-person-plus-fill"></i>
            <span>+ Cadastrar Novo Analista</span>
        </button>
    </div>
</div>

<!-- TABELA DE ANALISTAS -->
<div class="table-card">
    <div class="table-responsive">
        <table class="dsoft-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Avatar</th>
                    <th>Nome do Analista</th>
                    <th>E-mail de Acesso (Login)</th>
                    <th>Cargo / Nível</th>
                    <th style="text-align: center;">Total Atendimentos</th>
                    <th style="text-align: center;">Em Aberto</th>
                    <th style="text-align: center;">Concluídos</th>
                    <th style="text-align: right; width: 220px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analistas as $an): 
                    $st = $statsAnalistas[$an['nome']] ?? ['total' => 0, 'abertos' => 0, 'concluidos' => 0];
                    $isVoce = ($usuarioAtual['id'] ?? '') === ($an['id'] ?? '');
                ?>
                    <tr id="analista-row-<?= $an['id'] ?>">
                        <td>
                            <div class="user-avatar" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                <?= htmlspecialchars($an['avatar'] ?? 'DS') ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($an['nome']) ?></strong>
                            <?php if ($isVoce): ?>
                                <span style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 10px; font-weight: 700; margin-left: 6px;">Você</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?= htmlspecialchars($an['email']) ?></code>
                        </td>
                        <td>
                            <?= htmlspecialchars($an['cargo'] ?? 'Analista') ?>
                        </td>
                        <td style="text-align: center; font-weight: 800; color: var(--dsoft-navy);">
                            <?= $st['total'] ?>
                        </td>
                        <td style="text-align: center; font-weight: 800; color: #0f172a;">
                            <?= $st['abertos'] ?>
                        </td>
                        <td style="text-align: center; font-weight: 800; color: #16a34a;">
                            <?= $st['concluidos'] ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Botão Editar Dados & Senha -->
                            <button type="button" class="btn-action-primary" style="padding: 6px 12px; font-size: 0.8rem; background: #ffffff; color: var(--dsoft-navy); border: 1px solid #cbd5e1; box-shadow: none;" 
                                    onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($an)) ?>)" title="Editar Nome, E-mail ou Senha">
                                <i class="bi bi-pencil-square" style="color: var(--dsoft-cyan);"></i> Editar / Senha
                            </button>

                            <!-- Excluir se não for o usuário logado -->
                            <?php if (!$isVoce): ?>
                                <button type="button" class="btn-icon-action danger" onclick="confirmarExcluirAnalista('<?= $an['id'] ?>', '<?= htmlspecialchars($an['nome']) ?>')" title="Excluir Analista">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     MODAL 1: CADASTRAR NOVO ANALISTA
     ============================================================ -->
<div class="modal-overlay" id="modalNovoAnalista">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <i class="bi bi-person-plus-fill"></i>
                <span>Cadastrar Novo Analista de Suporte</span>
            </h3>
            <button type="button" class="btn-close-modal" onclick="document.getElementById('modalNovoAnalista').classList.remove('active')">&times;</button>
        </div>

        <form action="equipe.php" method="POST">
            <input type="hidden" name="acao" value="novo_analista">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label for="cad_nome">Nome Completo <span style="color: red;">*</span></label>
                        <input type="text" id="cad_nome" name="nome" class="form-control" placeholder="Ex: Rodrigo Mendes" required>
                    </div>

                    <div class="form-group">
                        <label for="cad_email">E-mail de Login <span style="color: red;">*</span></label>
                        <input type="email" id="cad_email" name="email" class="form-control" placeholder="rodrigo@dsoft.com.br" required>
                    </div>

                    <div class="form-group">
                        <label for="cad_cargo">Cargo / Função</label>
                        <input type="text" id="cad_cargo" name="cargo" class="form-control" placeholder="Ex: Analista N1 / Implantação" value="Analista de Suporte">
                    </div>

                    <div class="form-group form-group-full">
                        <label for="cad_senha">Senha Inicial de Acesso <span style="color: red;">*</span></label>
                        <input type="password" id="cad_senha" name="senha" class="form-control" placeholder="Mínimo 4 caracteres" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-action-primary" style="background: #e2e8f0; color: #475569; box-shadow: none;" onclick="document.getElementById('modalNovoAnalista').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> Salvar Cadastro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL 2: EDITAR ANALISTA (NOME, E-MAIL E SENHA)
     ============================================================ -->
<div class="modal-overlay" id="modalEditarAnalista">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <i class="bi bi-person-gear"></i>
                <span>Editar Analista & Alterar Senha</span>
            </h3>
            <button type="button" class="btn-close-modal" onclick="fecharModalEditar()">&times;</button>
        </div>

        <form action="equipe.php" method="POST" id="formEditarAnalista">
            <input type="hidden" name="acao" value="editar_analista">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label for="edit_nome">Nome Completo <span style="color: red;">*</span></label>
                        <input type="text" id="edit_nome" name="nome" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_email">E-mail de Login (Acesso) <span style="color: red;">*</span></label>
                        <input type="email" id="edit_email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_cargo">Cargo / Função</label>
                        <input type="text" id="edit_cargo" name="cargo" class="form-control" required>
                    </div>

                    <!-- Alteração de Senha -->
                    <div class="form-group form-group-full" style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <label for="edit_nova_senha" style="color: var(--dsoft-navy); font-weight: 800; display: flex; align-items: center; gap: 6px;">
                            <i class="bi bi-key-fill" style="color: var(--dsoft-cyan);"></i> Redefinir Senha de Acesso
                        </label>
                        <input type="password" id="edit_nova_senha" name="nova_senha" class="form-control" placeholder="Digite uma nova senha ou deixe em branco para manter a atual">
                        <span style="font-size: 0.78rem; color: #64748b; margin-top: 4px; display: block;">
                            ℹ️ Se você <strong>não</strong> quiser trocar a senha, basta deixar este campo vazio.
                        </span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-action-primary" style="background: #e2e8f0; color: #475569; box-shadow: none;" onclick="fecharModalEditar()">Cancelar</button>
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> Atualizar Dados & Senha
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalNovoAnalista() {
    document.getElementById('modalNovoAnalista').classList.add('active');
}

function abrirModalEditar(analista) {
    document.getElementById('edit_id').value = analista.id;
    document.getElementById('edit_nome').value = analista.nome;
    document.getElementById('edit_email').value = analista.email;
    document.getElementById('edit_cargo').value = analista.cargo || 'Analista de Suporte';
    document.getElementById('edit_nova_senha').value = '';
    document.getElementById('modalEditarAnalista').classList.add('active');
}

function fecharModalEditar() {
    document.getElementById('modalEditarAnalista').classList.remove('active');
}

function confirmarExcluirAnalista(id, nome) {
    if (confirm(`Tem certeza que deseja remover o analista "${nome}" da equipe? Ele não poderá mais fazer login.`)) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = 'equipe.php';
        
        const i1 = document.createElement('input');
        i1.type = 'hidden';
        i1.name = 'acao';
        i1.value = 'excluir_analista';
        
        const i2 = document.createElement('input');
        i2.type = 'hidden';
        i2.name = 'id';
        i2.value = id;
        
        f.appendChild(i1);
        f.appendChild(i2);
        document.body.appendChild(f);
        f.submit();
    }
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
