    </main>

    <!-- RODAPÉ -->
    <footer style="margin-top: auto; padding: 18px 24px; text-align: center; font-size: 0.82rem; color: #94a3b8; border-top: 1px solid #e2e8f0; background: #ffffff;">
        <strong>dsoft</strong> • Software de Gestão — Central de Chamados & Agenda da Equipe de Suporte
    </footer>
</div>

<!-- ============================================================
     MODAL DE NOVO LANÇAMENTO (GLOBAL)
     ============================================================ -->
<?php
$db = Database::getInstance();
$listaAnalistas = $db->getAnalistas();
$usuarioLogado = currentUser();
?>
<div class="modal-overlay" id="modalNovoTicket">
    <div class="modal-box">
        <div class="modal-header">
            <h3>
                <i class="bi bi-ticket-perforated-fill"></i>
                <span>Novo Lançamento de Atendimento</span>
            </h3>
            <button type="button" class="btn-close-modal" onclick="fecharModalNovo()">&times;</button>
        </div>

        <form action="api/salvar_ticket.php" method="POST" id="formNovoTicket">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Número do Ticket -->
                    <div class="form-group">
                        <label for="numero_ticket">Número do Ticket / Chamado <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="numero_ticket" name="numero_ticket" class="form-control" placeholder="Ex: 10455" required autofocus>
                    </div>

                    <!-- Analista Responsável -->
                    <div class="form-group">
                        <label for="analista_nome">Analista Responsável <span style="color: #ef4444;">*</span></label>
                        <select id="analista_nome" name="analista_nome" class="form-control" required>
                            <?php foreach ($listaAnalistas as $an): ?>
                                <option value="<?= htmlspecialchars($an['nome']) ?>" <?= ($usuarioLogado['nome'] ?? '') === $an['nome'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($an['nome']) ?> (<?= htmlspecialchars($an['cargo'] ?? 'Suporte') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cliente / Empresa -->
                    <div class="form-group">
                        <label for="cliente_empresa">Cliente / Empresa <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="cliente_empresa" name="cliente_empresa" class="form-control" placeholder="Ex: Supermercado Central" required>
                    </div>

                    <!-- Data e Hora -->
                    <div class="form-group">
                        <label for="data_hora">Data e Hora <span style="color: #ef4444;">*</span></label>
                        <input type="datetime-local" id="data_hora" name="data_hora" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <!-- Como Entrou / Tipo -->
                    <div class="form-group">
                        <label for="tipo">Como Entrou (Tipo) <span style="color: #ef4444;">*</span></label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="Bug">🐛 Bug / Erro de Sistema</option>
                            <option value="Melhoria">💡 Melhoria / Solicitação</option>
                            <option value="Dúvida">❓ Dúvida / Suporte Geral</option>
                            <option value="Configuração">⚙️ Configuração / Implantação</option>
                            <option value="Treinamento">🎓 Treinamento</option>
                            <option value="Outro">📁 Outro</option>
                        </select>
                    </div>

                    <!-- Status Inicial -->
                    <div class="form-group">
                        <label for="status">Status Inicial <span style="color: #ef4444;">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Em Aberto">⏳ Em Aberto (escrita preta)</option>
                            <option value="Concluído">✅ Concluído (tonalidade verde)</option>
                        </select>
                    </div>

                    <!-- Prioridade -->
                    <div class="form-group form-group-full">
                        <label for="prioridade">Prioridade</label>
                        <select id="prioridade" name="prioridade" class="form-control">
                            <option value="Média">Média</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Alta">Alta</option>
                            <option value="Crítica">🚨 Crítica / Urgente</option>
                        </select>
                    </div>

                    <!-- Descrição Detalhada -->
                    <div class="form-group form-group-full">
                        <label for="descricao">Descrição do Atendimento / Ocorrência <span style="color: #ef4444;">*</span></label>
                        <textarea id="descricao" name="descricao" rows="4" class="form-control" placeholder="Descreva os detalhes do atendimento, procedimento realizado ou erro reportado..." required></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-action-primary" style="background: #e2e8f0; color: #475569; box-shadow: none;" onclick="fecharModalNovo()">Cancelar</button>
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i>
                    <span>Gravar Lançamento</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
