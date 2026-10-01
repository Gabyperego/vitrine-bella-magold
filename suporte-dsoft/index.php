<?php
/**
 * Dashboard Oficial — dsoft Suporte
 * Métricas e Filtros (Dia, Semana, Mês, Analista, Tipo)
 */

$pageTitle = 'Dashboard da Equipe';
require_once __DIR__ . '/partials/header.php';

$db = Database::getInstance();
$analistas = $db->getAnalistas();

// Filtros recebidos via GET
$filtroPeriodo = $_GET['periodo'] ?? 'mes'; // Padrão: mês atual
$filtroAnalista = $_GET['analista'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

// Carrega tickets com base nos filtros
$tickets = $db->getTickets([
    'periodo' => $filtroPeriodo !== 'todos' ? $filtroPeriodo : '',
    'analista' => $filtroAnalista,
    'tipo' => $filtroTipo
]);

// Cálculos das Métricas
$totalTickets = count($tickets);
$totalAbertos = 0;
$totalConcluidos = 0;
$totalBugs = 0;
$totalMelhorias = 0;
$totalDuvidas = 0;

$contagemPorAnalista = [];
$contagemPorTipo = [];

foreach ($tickets as $t) {
    $st = $t['status'] ?? 'Em Aberto';
    if ($st === 'Concluído') {
        $totalConcluidos++;
    } else {
        $totalAbertos++;
    }

    $tipo = $t['tipo'] ?? 'Outro';
    if ($tipo === 'Bug') $totalBugs++;
    elseif ($tipo === 'Melhoria') $totalMelhorias++;
    elseif ($tipo === 'Dúvida') $totalDuvidas++;

    $contagemPorTipo[$tipo] = ($contagemPorTipo[$tipo] ?? 0) + 1;

    $anNome = $t['analista_nome'] ?? 'Não informado';
    $contagemPorAnalista[$anNome] = ($contagemPorAnalista[$anNome] ?? 0) + 1;
}

$taxaResolucao = $totalTickets > 0 ? round(($totalConcluidos / $totalTickets) * 100) : 0;
?>

<!-- TÍTULO DA PÁGINA -->
<div class="page-header-row">
    <div class="page-title-group">
        <h1>
            <i class="bi bi-speedometer2" style="color: var(--dsoft-cyan);"></i>
            <span>Dashboard de Suporte & Atendimentos</span>
        </h1>
        <p>Acompanhamento em tempo real da produtividade da equipe e chamados registrados.</p>
    </div>

    <div>
        <a href="lancamentos.php" class="btn-action-primary" style="background: #ffffff; color: var(--dsoft-navy); border: 1px solid #cbd5e1; box-shadow: none;">
            <i class="bi bi-table"></i>
            <span>Ver Tabela Completa</span>
        </a>
    </div>
</div>

<!-- ============================================================
     PAINEL DE FILTROS: DIA, SEMANA, MÊS, ANALISTA E TIPO
     ============================================================ -->
<div class="filter-panel">
    <!-- Pílulas de Período (Dia, Semanal, Mensal, Todos) -->
    <div class="filter-pills-group">
        <a href="?periodo=hoje&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>" 
           class="filter-pill <?= $filtroPeriodo === 'hoje' ? 'active' : '' ?>">
            <i class="bi bi-calendar-day"></i> Hoje (Dia)
        </a>
        <a href="?periodo=semana&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>" 
           class="filter-pill <?= $filtroPeriodo === 'semana' ? 'active' : '' ?>">
            <i class="bi bi-calendar-week"></i> Esta Semana
        </a>
        <a href="?periodo=mes&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>" 
           class="filter-pill <?= $filtroPeriodo === 'mes' ? 'active' : '' ?>">
            <i class="bi bi-calendar-month"></i> Este Mês
        </a>
        <a href="?periodo=todos&analista=<?= urlencode($filtroAnalista) ?>&tipo=<?= urlencode($filtroTipo) ?>" 
           class="filter-pill <?= $filtroPeriodo === 'todos' ? 'active' : '' ?>">
            <i class="bi bi-collection"></i> Todos
        </a>
    </div>

    <!-- Dropdowns de Analista e Tipo -->
    <form method="GET" action="index.php" class="filter-inputs-group" id="filterForm">
        <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtroPeriodo) ?>">

        <!-- Filtro por Analista -->
        <select name="analista" class="form-select-filter" onchange="document.getElementById('filterForm').submit()">
            <option value="">👤 Todos os Analistas</option>
            <?php foreach ($analistas as $an): ?>
                <option value="<?= htmlspecialchars($an['nome']) ?>" <?= $filtroAnalista === $an['nome'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($an['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Filtro por Tipo -->
        <select name="tipo" class="form-select-filter" onchange="document.getElementById('filterForm').submit()">
            <option value="">🏷️ Todos os Tipos</option>
            <option value="Bug" <?= $filtroTipo === 'Bug' ? 'selected' : '' ?>>🐛 Bugs / Erros</option>
            <option value="Melhoria" <?= $filtroTipo === 'Melhoria' ? 'selected' : '' ?>>💡 Melhorias</option>
            <option value="Dúvida" <?= $filtroTipo === 'Dúvida' ? 'selected' : '' ?>>❓ Dúvidas</option>
            <option value="Configuração" <?= $filtroTipo === 'Configuração' ? 'selected' : '' ?>>⚙️ Configurações</option>
            <option value="Treinamento" <?= $filtroTipo === 'Treinamento' ? 'selected' : '' ?>>🎓 Treinamentos</option>
        </select>

        <?php if (!empty($filtroAnalista) || !empty($filtroTipo) || $filtroPeriodo !== 'mes'): ?>
            <a href="index.php" class="filter-pill" style="background: #fee2e2; color: #dc2626;" title="Limpar Filtros">
                <i class="bi bi-x-circle"></i> Limpar
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ============================================================
     CARDS DE MÉTRICAS DO SUPORTE
     ============================================================ -->
<div class="metrics-grid">
    <!-- Card Total -->
    <div class="metric-card card-total">
        <div class="metric-info">
            <h3>Total Lançados</h3>
            <div class="metric-number"><?= $totalTickets ?></div>
            <div class="metric-sub">No período selecionado</div>
        </div>
        <div class="metric-icon-circle">
            <i class="bi bi-folder2-open"></i>
        </div>
    </div>

    <!-- Card Em Aberto (Preto) -->
    <div class="metric-card card-aberto">
        <div class="metric-info">
            <h3>Em Aberto</h3>
            <div class="metric-number" style="color: #0f172a;"><?= $totalAbertos ?></div>
            <div class="metric-sub">Aguardando conclusão</div>
        </div>
        <div class="metric-icon-circle">
            <i class="bi bi-clock-history"></i>
        </div>
    </div>

    <!-- Card Concluídos (Verde) -->
    <div class="metric-card card-concluido">
        <div class="metric-info">
            <h3>Finalizados</h3>
            <div class="metric-number" style="color: #16a34a;"><?= $totalConcluidos ?></div>
            <div class="metric-sub"><?= $taxaResolucao ?>% de resolução</div>
        </div>
        <div class="metric-icon-circle">
            <i class="bi bi-check-circle-fill"></i>
        </div>
    </div>

    <!-- Card Bugs -->
    <div class="metric-card card-bug">
        <div class="metric-info">
            <h3>Bugs / Erros</h3>
            <div class="metric-number" style="color: #dc2626;"><?= $totalBugs ?></div>
            <div class="metric-sub"><?= $totalTickets > 0 ? round(($totalBugs / $totalTickets) * 100) : 0 ?>% do volume</div>
        </div>
        <div class="metric-icon-circle">
            <i class="bi bi-bug-fill"></i>
        </div>
    </div>

    <!-- Card Melhorias -->
    <div class="metric-card card-melhoria">
        <div class="metric-info">
            <h3>Melhorias</h3>
            <div class="metric-number" style="color: #7c3aed;"><?= $totalMelhorias ?></div>
            <div class="metric-sub">Sugestões de produto</div>
        </div>
        <div class="metric-icon-circle">
            <i class="bi bi-lightbulb-fill"></i>
        </div>
    </div>
</div>

<!-- ============================================================
     SEÇÃO GRÁFICA / DISTRIBUIÇÃO E PRODUTIVIDADE DA EQUIPE
     ============================================================ -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Gráfico / Distribuição por Tipo -->
    <div class="table-card" style="padding: 20px;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--dsoft-navy); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-pie-chart" style="color: var(--dsoft-cyan);"></i>
            <span>Distribuição por Tipo de Entrada</span>
        </h3>

        <?php if (empty($contagemPorTipo)): ?>
            <p style="color: var(--text-muted); font-size: 0.88rem; text-align: center; padding: 20px;">Nenhum chamado no período.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($contagemPorTipo as $tipoNome => $qtd): 
                    $pct = $totalTickets > 0 ? round(($qtd / $totalTickets) * 100) : 0;
                    $corBarra = match($tipoNome) {
                        'Bug' => '#ef4444',
                        'Melhoria' => '#0284c7',
                        'Dúvida' => '#f59e0b',
                        'Configuração' => '#8b5cf6',
                        'Treinamento' => '#10b981',
                        default => '#64748b'
                    };
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; margin-bottom: 4px;">
                            <span><?= htmlspecialchars($tipoNome) ?></span>
                            <span style="color: var(--text-muted);"><?= $qtd ?> chamados (<?= $pct ?>%)</span>
                        </div>
                        <div style="height: 10px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $pct ?>%; background: <?= $corBarra ?>; border-radius: 6px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Atendimentos por Analista -->
    <div class="table-card" style="padding: 20px;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--dsoft-navy); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-people" style="color: var(--dsoft-cyan);"></i>
            <span>Atendimentos por Analista</span>
        </h3>

        <?php if (empty($contagemPorAnalista)): ?>
            <p style="color: var(--text-muted); font-size: 0.88rem; text-align: center; padding: 20px;">Nenhum registro para o período.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php 
                arsort($contagemPorAnalista);
                foreach ($contagemPorAnalista as $anNome => $qtd): 
                    $pct = $totalTickets > 0 ? round(($qtd / $totalTickets) * 100) : 0;
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; margin-bottom: 4px;">
                            <span style="color: var(--dsoft-navy);">
                                <i class="bi bi-person-badge"></i> <?= htmlspecialchars($anNome) ?>
                            </span>
                            <span style="color: var(--dsoft-cyan); font-weight: 800;"><?= $qtd ?> atendimentos</span>
                        </div>
                        <div style="height: 10px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $pct ?>%; background: linear-gradient(90deg, var(--dsoft-cyan) 0%, #0077b6 100%); border-radius: 6px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     TABELA: ÚLTIMOS LANÇAMENTOS COM REGRAS DE CORES
     "Em aberto fica preto a escrita"
     "Finalizado na tonalidade verde com botão de concluído"
     ============================================================ -->
<div class="table-card">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
        <h3 style="font-size: 1rem; font-weight: 800; color: var(--dsoft-navy); display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-clock-history" style="color: var(--dsoft-cyan);"></i>
            <span>Lançamentos Recentes da Equipe</span>
        </h3>
        <a href="lancamentos.php" style="font-size: 0.85rem; font-weight: 700; color: var(--dsoft-cyan); text-decoration: none;">
            Ver todos (<?= $totalTickets ?>) &rarr;
        </a>
    </div>

    <div class="table-responsive">
        <table class="dsoft-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Ticket</th>
                    <th>Data / Hora</th>
                    <th>Analista</th>
                    <th>Cliente / Empresa</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Status</th>
                    <th style="text-align: right; width: 140px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            Nenhum atendimento encontrado com os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_slice($tickets, 0, 8) as $t): 
                        $isConcluido = ($t['status'] ?? '') === 'Concluído';
                        $rowClass = $isConcluido ? 'row-status-concluido' : 'row-status-aberto';
                        $tipoClass = match($t['tipo'] ?? '') {
                            'Bug' => 'tipo-bug',
                            'Melhoria' => 'tipo-melhoria',
                            'Dúvida' => 'tipo-duvida',
                            default => 'tipo-outro'
                        };
                    ?>
                        <tr class="<?= $rowClass ?>" id="ticket-row-<?= $t['id'] ?>">
                            <!-- Número do Ticket -->
                            <td>
                                <strong class="ticket-title">#<?= htmlspecialchars($t['numero_ticket']) ?></strong>
                            </td>

                            <!-- Data e Hora -->
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y H:i', strtotime($t['data_hora'] ?? $t['created_at'])) ?>
                            </td>

                            <!-- Analista -->
                            <td>
                                <strong><?= htmlspecialchars($t['analista_nome'] ?? '-') ?></strong>
                            </td>

                            <!-- Cliente / Empresa -->
                            <td>
                                <?= htmlspecialchars($t['cliente_empresa'] ?? '-') ?>
                            </td>

                            <!-- Tipo (Bug, Melhoria, etc.) -->
                            <td>
                                <span class="badge-tipo <?= $tipoClass ?>">
                                    <?= htmlspecialchars($t['tipo'] ?? 'Outro') ?>
                                </span>
                            </td>

                            <!-- Descrição -->
                            <td style="max-width: 320px;" title="<?= htmlspecialchars($t['descricao']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($t['descricao'], 0, 75, '...')) ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge-status">
                                    <i class="bi bi-<?= $isConcluido ? 'check-circle-fill' : 'clock' ?>"></i>
                                    <span><?= $isConcluido ? 'Concluído' : 'Em Aberto' ?></span>
                                </span>
                            </td>

                            <!-- Botão de Concluído / Ação Rápida -->
                            <td style="text-align: right;">
                                <?php if ($isConcluido): ?>
                                    <button type="button" class="btn-toggle-status btn-toggle-reabrir" onclick="alternarStatus('<?= $t['id'] ?>')" title="Clique para reabrir este chamado">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-toggle-status btn-toggle-concluir" onclick="alternarStatus('<?= $t['id'] ?>')" title="Marcar como Concluído">
                                        <i class="bi bi-check-lg"></i> Concluir
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
