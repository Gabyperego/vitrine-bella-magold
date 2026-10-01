<?php
/**
 * Todos os Lançamentos — dsoft Suporte
 * Lista completa compartilhada de todos os atendimentos da equipe
 * Regras:
 * - Em Aberto: Escrita PRETA marcante com botão de Concluir
 * - Finalizado: Tonalidade VERDE
 */

$pageTitle = 'Todos os Lançamentos';
require_once __DIR__ . '/partials/header.php';

$db = Database::getInstance();
$analistas = $db->getAnalistas();

// Filtros recebidos via GET
$filtroBusca = trim($_GET['busca'] ?? '');
$filtroAnalista = $_GET['analista'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$filtroPeriodo = $_GET['periodo'] ?? '';

// Carrega todos os tickets com filtros aplicados
$tickets = $db->getTickets([
    'busca' => $filtroBusca,
    'analista' => $filtroAnalista,
    'tipo' => $filtroTipo,
    'status' => $filtroStatus,
    'periodo' => $filtroPeriodo
]);

$totalGeral = count($tickets);
$abertosCount = 0;
$concluidosCount = 0;
foreach ($tickets as $t) {
    if (($t['status'] ?? '') === 'Concluído') $concluidosCount++;
    else $abertosCount++;
}
?>

<!-- CABEÇALHO DA TELA -->
<div class="page-header-row">
    <div class="page-title-group">
        <h1>
            <i class="bi bi-card-checklist" style="color: var(--dsoft-cyan);"></i>
            <span>Todos os Lançamentos da Equipe</span>
        </h1>
        <p>Visão geral compartilhada de todos os tickets registrados por todos os analistas.</p>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn-action-primary" onclick="window.print()" style="background: #ffffff; color: var(--dsoft-navy); border: 1px solid #cbd5e1; box-shadow: none;">
            <i class="bi bi-printer"></i>
            <span>Imprimir / PDF</span>
        </button>

        <button type="button" class="btn-action-primary" onclick="abrirModalNovo()">
            <i class="bi bi-plus-circle-fill"></i>
            <span>+ Novo Lançamento</span>
        </button>
    </div>
</div>

<!-- ============================================================
     BARRA DE FILTROS E PESQUISA EM TEMPO REAL
     ============================================================ -->
<div class="filter-panel" style="flex-direction: column; align-items: stretch; gap: 14px;">
    <!-- Linha 1: Abas Rápidas de Status -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div class="filter-pills-group">
            <a href="?status=&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>&periodo=<?= urlencode($filtroPeriodo) ?>" 
               class="filter-pill <?= empty($filtroStatus) ? 'active' : '' ?>">
                Todos os Chamados (<?= $totalGeral ?>)
            </a>
            <a href="?status=Em Aberto&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>&periodo=<?= urlencode($filtroPeriodo) ?>" 
               class="filter-pill <?= $filtroStatus === 'Em Aberto' ? 'active' : '' ?>" style="color: #0f172a;">
                <i class="bi bi-clock-fill"></i> Em Aberto (<?= $abertosCount ?>)
            </a>
            <a href="?status=Concluído&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>&periodo=<?= urlencode($filtroPeriodo) ?>" 
               class="filter-pill <?= $filtroStatus === 'Concluído' ? 'active' : '' ?>" style="color: #16a34a;">
                <i class="bi bi-check-circle-fill"></i> Concluídos (<?= $concluidosCount ?>)
            </a>
        </div>

        <!-- Legenda visual -->
        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.8rem; font-weight: 700;">
            <span style="display: flex; align-items: center; gap: 6px; color: #0f172a;">
                <span style="width: 12px; height: 12px; background: #0f172a; border-radius: 3px; display: inline-block;"></span>
                Em Aberto (Escrita Preta)
            </span>
            <span style="display: flex; align-items: center; gap: 6px; color: #166534;">
                <span style="width: 12px; height: 12px; background: #a7f3d0; border: 1px solid #16a34a; border-radius: 3px; display: inline-block;"></span>
                Concluído (Tonalidade Verde)
            </span>
        </div>
    </div>

    <!-- Linha 2: Campos de Filtro (Busca, Analista, Tipo, Período) -->
    <form method="GET" action="lancamentos.php" class="filter-inputs-group" style="width: 100%; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 10px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filtroStatus) ?>">

        <!-- Campo de Busca Textual -->
        <div style="position: relative;">
            <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
            <input type="text" name="busca" class="form-input-search" style="width: 100%; padding-left: 36px;" 
                   placeholder="Buscar ticket, cliente ou descrição..." value="<?= htmlspecialchars($filtroBusca) ?>" id="inputBuscaInstantanea">
        </div>

        <!-- Analista -->
        <select name="analista" class="form-select-filter" onchange="this.form.submit()">
            <option value="">👤 Todos os Analistas</option>
            <?php foreach ($analistas as $an): ?>
                <option value="<?= htmlspecialchars($an['nome']) ?>" <?= $filtroAnalista === $an['nome'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($an['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Tipo -->
        <select name="tipo" class="form-select-filter" onchange="this.form.submit()">
            <option value="">🏷️ Todos os Tipos</option>
            <option value="Bug" <?= $filtroTipo === 'Bug' ? 'selected' : '' ?>>🐛 Bugs / Erros</option>
            <option value="Melhoria" <?= $filtroTipo === 'Melhoria' ? 'selected' : '' ?>>💡 Melhorias</option>
            <option value="Dúvida" <?= $filtroTipo === 'Dúvida' ? 'selected' : '' ?>>❓ Dúvidas</option>
            <option value="Configuração" <?= $filtroTipo === 'Configuração' ? 'selected' : '' ?>>⚙️ Configurações</option>
            <option value="Treinamento" <?= $filtroTipo === 'Treinamento' ? 'selected' : '' ?>>🎓 Treinamentos</option>
        </select>

        <!-- Período -->
        <select name="periodo" class="form-select-filter" onchange="this.form.submit()">
            <option value="">📅 Qualquer Período</option>
            <option value="hoje" <?= $filtroPeriodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
            <option value="semana" <?= $filtroPeriodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
            <option value="mes" <?= $filtroPeriodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
        </select>

        <!-- Botões de Ação do Filtro -->
        <div style="display: flex; gap: 6px;">
            <button type="submit" class="btn-action-primary" style="padding: 8px 16px;">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            <?php if (!empty($filtroBusca) || !empty($filtroAnalista) || !empty($filtroTipo) || !empty($filtroStatus) || !empty($filtroPeriodo)): ?>
                <a href="lancamentos.php" class="btn-action-primary" style="background: #fee2e2; color: #dc2626; box-shadow: none; padding: 8px 12px;" title="Limpar Filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================================================
     TABELA DE TODOS OS LANÇAMENTOS
     ============================================================ -->
<div class="table-card">
    <div class="table-responsive">
        <table class="dsoft-table" id="tabelaLancamentos">
            <thead>
                <tr>
                    <th style="width: 100px;">Nº Ticket</th>
                    <th style="width: 150px;">Data / Hora</th>
                    <th style="width: 170px;">Analista</th>
                    <th style="width: 200px;">Cliente / Empresa</th>
                    <th style="width: 140px;">Como Entrou (Tipo)</th>
                    <th>Descrição do Atendimento</th>
                    <th style="width: 130px;">Status</th>
                    <th style="text-align: right; width: 180px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 50px;">
                            <i class="bi bi-inbox" style="font-size: 2.2rem; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                            <h4 style="color: var(--dsoft-navy); margin-bottom: 4px;">Nenhum chamado encontrado</h4>
                            <p style="font-size: 0.88rem;">Tente ajustar os filtros ou registre um novo chamado no botão acima.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): 
                        $isConcluido = ($t['status'] ?? '') === 'Concluído';
                        $rowClass = $isConcluido ? 'row-status-concluido' : 'row-status-aberto';
                        $tipoClass = match($t['tipo'] ?? '') {
                            'Bug' => 'tipo-bug',
                            'Melhoria' => 'tipo-melhoria',
                            'Dúvida' => 'tipo-duvida',
                            default => 'tipo-outro'
                        };
                    ?>
                        <tr class="<?= $rowClass ?>" id="ticket-row-<?= $t['id'] ?>" data-ticket="<?= htmlspecialchars($t['numero_ticket']) ?>">
                            <!-- Número do Ticket -->
                            <td>
                                <strong class="ticket-title" style="font-size: 0.95rem;">
                                    #<?= htmlspecialchars($t['numero_ticket']) ?>
                                </strong>
                            </td>

                            <!-- Data e Hora -->
                            <td style="white-space: nowrap;">
                                <div style="font-weight: 700;"><?= date('d/m/Y', strtotime($t['data_hora'] ?? $t['created_at'])) ?></div>
                                <div style="font-size: 0.78rem; opacity: 0.8;"><?= date('H:i', strtotime($t['data_hora'] ?? $t['created_at'])) ?></div>
                            </td>

                            <!-- Analista -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="width: 26px; height: 26px; border-radius: 50%; background: #00b4d8; color: #fff; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center;">
                                        <?= strtoupper(substr($t['analista_nome'] ?? 'A', 0, 1)) ?>
                                    </span>
                                    <strong><?= htmlspecialchars($t['analista_nome'] ?? '-') ?></strong>
                                </div>
                            </td>

                            <!-- Cliente / Empresa -->
                            <td>
                                <strong><?= htmlspecialchars($t['cliente_empresa'] ?? '-') ?></strong>
                            </td>

                            <!-- Como Entrou / Tipo -->
                            <td>
                                <span class="badge-tipo <?= $tipoClass ?>">
                                    <?php if (($t['tipo'] ?? '') === 'Bug'): ?>🐛<?php elseif (($t['tipo'] ?? '') === 'Melhoria'): ?>💡<?php elseif (($t['tipo'] ?? '') === 'Dúvida'): ?>❓<?php endif; ?>
                                    <?= htmlspecialchars($t['tipo'] ?? 'Outro') ?>
                                </span>
                            </td>

                            <!-- Descrição -->
                            <td>
                                <div style="line-height: 1.45; word-break: break-word;">
                                    <?= nl2br(htmlspecialchars($t['descricao'])) ?>
                                </div>
                                <?php if (!empty($t['concluido_em']) && $isConcluido): ?>
                                    <div style="font-size: 0.75rem; color: #047857; margin-top: 4px; font-weight: 700;">
                                        <i class="bi bi-check2-all"></i> Concluído em <?= date('d/m/Y H:i', strtotime($t['concluido_em'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge-status">
                                    <i class="bi bi-<?= $isConcluido ? 'check-circle-fill' : 'clock' ?>"></i>
                                    <span><?= $isConcluido ? 'Concluído' : 'Em Aberto' ?></span>
                                </span>
                            </td>

                            <!-- Botão Concluir / Ações -->
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($isConcluido): ?>
                                    <button type="button" class="btn-toggle-status btn-toggle-reabrir" onclick="alternarStatus('<?= $t['id'] ?>')" title="Clique para reabrir este chamado">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-toggle-status btn-toggle-concluir" onclick="alternarStatus('<?= $t['id'] ?>')" title="Marcar como Concluído na hora">
                                        <i class="bi bi-check-lg"></i> Concluir
                                    </button>
                                <?php endif; ?>

                                <!-- Excluir -->
                                <button type="button" class="btn-icon-action danger" onclick="excluirTicket('<?= $t['id'] ?>', '<?= htmlspecialchars($t['numero_ticket']) ?>')" title="Excluir Lançamento">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
