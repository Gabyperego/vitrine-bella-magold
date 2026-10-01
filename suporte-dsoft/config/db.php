<?php
/**
 * Gerenciador de Banco de Dados — dsoft Suporte
 * Armazenamento em JSON de alta performance com concorrência segura
 */

date_default_timezone_set('America/Campo_Grande');

define('DATA_DIR', __DIR__ . '/../data');

class Database {
    private static ?Database $instance = null;
    private string $ticketsFile;
    private string $usersFile;

    private function __construct() {
        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0777, true);
        }
        $this->ticketsFile = DATA_DIR . '/tickets.json';
        $this->usersFile = DATA_DIR . '/analistas.json';

        $this->ensureSeedData();
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    private function readJson(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $filePath, array $data): bool {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($filePath, $json, LOCK_EX) !== false;
    }

    // ==========================================
    // USUÁRIOS / ANALISTAS
    // ==========================================
    public function getAnalistas(): array {
        return $this->readJson($this->usersFile);
    }

    public function findAnalistaByEmail(string $email): ?array {
        $users = $this->getAnalistas();
        $email = mb_strtolower(trim($email), 'UTF-8');
        foreach ($users as $u) {
            if (mb_strtolower($u['email'], 'UTF-8') === $email) {
                return $u;
            }
        }
        return null;
    }

    public function findAnalistaById(string $id): ?array {
        $users = $this->getAnalistas();
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    public function addAnalista(array $analista): array {
        $users = $this->getAnalistas();
        if (empty($analista['id'])) {
            $analista['id'] = uniqid('usr_');
        }
        if (empty($analista['created_at'])) {
            $analista['created_at'] = date('Y-m-d H:i:s');
        }
        $users[] = $analista;
        $this->writeJson($this->usersFile, $users);
        return $analista;
    }

    public function updateAnalista(string $id, array $dados): bool {
        $users = $this->getAnalistas();
        $found = false;
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if (!empty($dados['senha'])) {
                    $u['senha_hash'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
                }
                if (isset($dados['nome'])) {
                    $u['nome'] = $dados['nome'];
                    $partes = explode(' ', trim($dados['nome']));
                    $u['avatar'] = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : substr($partes[0], 1, 1)));
                }
                if (isset($dados['email'])) {
                    $u['email'] = $dados['email'];
                }
                if (isset($dados['cargo'])) {
                    $u['cargo'] = $dados['cargo'];
                }
                if (isset($dados['ativo'])) {
                    $u['ativo'] = (bool)$dados['ativo'];
                }
                $u['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        if ($found) {
            $this->writeJson($this->usersFile, $users);
        }
        return $found;
    }

    public function deleteAnalista(string $id): bool {
        $users = $this->getAnalistas();
        $novos = array_values(array_filter($users, fn($u) => $u['id'] !== $id));
        if (count($novos) !== count($users)) {
            $this->writeJson($this->usersFile, $novos);
            return true;
        }
        return false;
    }

    // ==========================================
    // TICKETS / LANÇAMENTOS
    // ==========================================
    public function getTickets(array $filters = []): array {
        $tickets = $this->readJson($this->ticketsFile);

        // Ordenação padrão: mais recentes primeiro
        usort($tickets, function($a, $b) {
            return strtotime($b['data_hora'] ?? $b['created_at']) <=> strtotime($a['data_hora'] ?? $a['created_at']);
        });

        if (empty($filters)) {
            return $tickets;
        }

        return array_values(array_filter($tickets, function($t) use ($filters) {
            // Filtro por Analista
            if (!empty($filters['analista']) && ($t['analista_nome'] ?? '') !== $filters['analista']) {
                return false;
            }

            // Filtro por Tipo (Bug, Melhoria, etc.)
            if (!empty($filters['tipo']) && ($t['tipo'] ?? '') !== $filters['tipo']) {
                return false;
            }

            // Filtro por Status (Em Aberto, Concluído)
            if (!empty($filters['status']) && ($t['status'] ?? '') !== $filters['status']) {
                return false;
            }

            // Filtro por Período: hoje, semana, mes, ou intervalo
            $ticketDate = substr($t['data_hora'] ?? $t['created_at'], 0, 10);
            $hoje = date('Y-m-d');

            if (!empty($filters['periodo'])) {
                if ($filters['periodo'] === 'hoje') {
                    if ($ticketDate !== $hoje) return false;
                } elseif ($filters['periodo'] === 'semana') {
                    // Início da semana (Segunda-feira) até hoje
                    $segunda = date('Y-m-d', strtotime('monday this week'));
                    $domingo = date('Y-m-d', strtotime('sunday this week'));
                    if ($ticketDate < $segunda || $ticketDate > $domingo) return false;
                } elseif ($filters['periodo'] === 'mes') {
                    $mesAtual = date('Y-m');
                    if (!str_starts_with($ticketDate, $mesAtual)) return false;
                }
            }

            // Filtro por busca textual (número ticket, cliente ou descrição)
            if (!empty($filters['busca'])) {
                $termo = mb_strtolower(trim($filters['busca']), 'UTF-8');
                $textoBusca = mb_strtolower(
                    ($t['numero_ticket'] ?? '') . ' ' .
                    ($t['cliente_empresa'] ?? '') . ' ' .
                    ($t['analista_nome'] ?? '') . ' ' .
                    ($t['descricao'] ?? ''),
                    'UTF-8'
                );
                if (!str_contains($textoBusca, $termo)) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function findTicketById(string $id): ?array {
        $tickets = $this->readJson($this->ticketsFile);
        foreach ($tickets as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    public function addTicket(array $ticket): array {
        $tickets = $this->readJson($this->ticketsFile);
        if (empty($ticket['id'])) {
            $ticket['id'] = uniqid('tck_');
        }
        if (empty($ticket['created_at'])) {
            $ticket['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($ticket['status'])) {
            $ticket['status'] = 'Em Aberto';
        }
        $tickets[] = $ticket;
        $this->writeJson($this->ticketsFile, $tickets);
        return $ticket;
    }

    public function updateTicket(string $id, array $dados): bool {
        $tickets = $this->readJson($this->ticketsFile);
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] === $id) {
                $dados['updated_at'] = date('Y-m-d H:i:s');
                $t = array_merge($t, $dados);
                $found = true;
                break;
            }
        }
        if ($found) {
            $this->writeJson($this->ticketsFile, $tickets);
        }
        return $found;
    }

    public function toggleTicketStatus(string $id): ?string {
        $tickets = $this->readJson($this->ticketsFile);
        $novoStatus = null;
        foreach ($tickets as &$t) {
            if ($t['id'] === $id) {
                if (($t['status'] ?? 'Em Aberto') === 'Concluído') {
                    $t['status'] = 'Em Aberto';
                    unset($t['concluido_em']);
                } else {
                    $t['status'] = 'Concluído';
                    $t['concluido_em'] = date('Y-m-d H:i:s');
                }
                $t['updated_at'] = date('Y-m-d H:i:s');
                $novoStatus = $t['status'];
                break;
            }
        }
        if ($novoStatus !== null) {
            $this->writeJson($this->ticketsFile, $tickets);
        }
        return $novoStatus;
    }

    public function deleteTicket(string $id): bool {
        $tickets = $this->readJson($this->ticketsFile);
        $novos = array_values(array_filter($tickets, fn($t) => $t['id'] !== $id));
        if (count($novos) !== count($tickets)) {
            $this->writeJson($this->ticketsFile, $novos);
            return true;
        }
        return false;
    }

    // ==========================================
    // SEED INICIAL COM DADOS DE EXEMPLO
    // ==========================================
    private function ensureSeedData(): void {
        // Usuários Iniciais
        if (!file_exists($this->usersFile) || empty($this->readJson($this->usersFile))) {
            $seedUsers = [
                [
                    'id' => 'usr_001',
                    'nome' => 'Gaby Perego',
                    'email' => 'gaby@dsoft.com.br',
                    'senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'cargo' => 'Suporte N2 / Analista',
                    'avatar' => 'GP',
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 'usr_002',
                    'nome' => 'Lucas Silva',
                    'email' => 'lucas@dsoft.com.br',
                    'senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'cargo' => 'Analista de Suporte',
                    'avatar' => 'LS',
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 'usr_003',
                    'nome' => 'Fernanda Costa',
                    'email' => 'fernanda@dsoft.com.br',
                    'senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'cargo' => 'Analista de Atendimento',
                    'avatar' => 'FC',
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 'usr_004',
                    'nome' => 'Suporte Geral',
                    'email' => 'suporte@dsoft.com.br',
                    'senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                    'cargo' => 'Equipe Suporte',
                    'avatar' => 'DS',
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            $this->writeJson($this->usersFile, $seedUsers);
        }

        // Tickets Iniciais
        if (!file_exists($this->ticketsFile) || empty($this->readJson($this->ticketsFile))) {
            $agora = date('Y-m-d H:i:s');
            $ontem = date('Y-m-d H:i:s', strtotime('-1 day'));
            $doisDias = date('Y-m-d H:i:s', strtotime('-2 days'));
            $tresDias = date('Y-m-d H:i:s', strtotime('-3 days'));

            $seedTickets = [
                [
                    'id' => 'tck_1001',
                    'numero_ticket' => '10452',
                    'analista_nome' => 'Gaby Perego',
                    'cliente_empresa' => 'Supermercado Central',
                    'data_hora' => $agora,
                    'tipo' => 'Bug',
                    'status' => 'Em Aberto',
                    'prioridade' => 'Alta',
                    'descricao' => 'Erro ao emitir NFC-e em contingência offline. Rejeição 539: Duplicidade com diferença de chave.',
                    'created_at' => $agora
                ],
                [
                    'id' => 'tck_1002',
                    'numero_ticket' => '10451',
                    'analista_nome' => 'Lucas Silva',
                    'cliente_empresa' => 'Drogaria Boa Saúde',
                    'data_hora' => $agora,
                    'tipo' => 'Melhoria',
                    'status' => 'Concluído',
                    'concluido_em' => $agora,
                    'prioridade' => 'Média',
                    'descricao' => 'Solicitada inclusão de atalho rápido no PDV para consulta de estoque entre filiais.',
                    'created_at' => $agora
                ],
                [
                    'id' => 'tck_1003',
                    'numero_ticket' => '10448',
                    'analista_nome' => 'Fernanda Costa',
                    'cliente_empresa' => 'Auto Posto Alvorada',
                    'data_hora' => $ontem,
                    'tipo' => 'Dúvida',
                    'status' => 'Concluído',
                    'concluido_em' => $ontem,
                    'prioridade' => 'Baixa',
                    'descricao' => 'Orientação sobre fechamento de caixa diário e conferência de recebíveis em cartão.',
                    'created_at' => $ontem
                ],
                [
                    'id' => 'tck_1004',
                    'numero_ticket' => '10445',
                    'analista_nome' => 'Gaby Perego',
                    'cliente_empresa' => 'Distribuidora Pantanal',
                    'data_hora' => $doisDias,
                    'tipo' => 'Melhoria',
                    'status' => 'Em Aberto',
                    'prioridade' => 'Média',
                    'descricao' => 'Ajuste no layout do relatório financeiro de DRE por centro de custo.',
                    'created_at' => $doisDias
                ],
                [
                    'id' => 'tck_1005',
                    'numero_ticket' => '10440',
                    'analista_nome' => 'Lucas Silva',
                    'cliente_empresa' => 'Móveis & Design Ltda',
                    'data_hora' => $tresDias,
                    'tipo' => 'Bug',
                    'status' => 'Concluído',
                    'concluido_em' => $doisDias,
                    'prioridade' => 'Crítica',
                    'descricao' => 'Lentidão no sync do banco de dados em horário de pico. Otimizados índices e reiniciado serviço.',
                    'created_at' => $tresDias
                ]
            ];
            $this->writeJson($this->ticketsFile, $seedTickets);
        }
    }
}
