<?php
/**
 * Gerenciador de Banco de Dados — dsoft Suporte
 * Suporte Híbrido: Conexão Direta ao Supabase (PostgreSQL) com Fallback Local (JSON)
 */

date_default_timezone_set('America/Campo_Grande');

define('DATA_DIR', __DIR__ . '/../data');

// Carregador simples de .env se existir
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

class Database {
    private static ?Database $instance = null;
    private string $ticketsFile;
    private string $usersFile;
    private string $supabaseUrl;
    private string $supabaseAnonKey;
    private bool $useSupabase = false;

    private function __construct() {
        if (!is_dir(DATA_DIR)) {
            @mkdir(DATA_DIR, 0777, true);
        }
        $this->ticketsFile = DATA_DIR . '/tickets.json';
        $this->usersFile = DATA_DIR . '/analistas.json';

        $this->supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: '', '/');
        $this->supabaseAnonKey = getenv('SUPABASE_ANON_KEY') ?: '';

        if (!empty($this->supabaseUrl) && 
            !empty($this->supabaseAnonKey) && 
            !str_contains($this->supabaseUrl, 'SEU-PROJETO') &&
            !str_contains($this->supabaseAnonKey, 'SUA_CHAVE_ANON')) {
            $this->useSupabase = true;
        }

        $this->ensureSeedData();
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function isSupabase(): bool {
        return $this->useSupabase;
    }

    // ==========================================
    // SUPABASE REST REQUEST HELPER (cURL)
    // ==========================================
    private function supabaseRequest(string $method, string $path, array $data = []): ?array {
        if (!$this->useSupabase) return null;

        $url = $this->supabaseUrl . '/rest/v1/' . $path;
        $headers = [
            'apikey: ' . $this->supabaseAnonKey,
            'Authorization: Bearer ' . $this->supabaseAnonKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (!empty($data) && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : [];
        }

        return null;
    }

    // ==========================================
    // LEITURA / ESCRITA JSON LOCAL
    // ==========================================
    private function readJson(string $filePath): array {
        if (!file_exists($filePath)) return [];
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
        if ($this->useSupabase) {
            $res = $this->supabaseRequest('GET', 'analistas?select=*&order=nome.asc');
            if ($res !== null) return $res;
        }
        return $this->readJson($this->usersFile);
    }

    public function findAnalistaByEmail(string $email): ?array {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($this->useSupabase) {
            $res = $this->supabaseRequest('GET', 'analistas?email=eq.' . urlencode($email) . '&select=*');
            if (!empty($res)) return $res[0];
        }

        $users = $this->readJson($this->usersFile);
        foreach ($users as $u) {
            if (mb_strtolower($u['email'], 'UTF-8') === $email) {
                return $u;
            }
        }
        return null;
    }

    public function findAnalistaById(string $id): ?array {
        if ($this->useSupabase) {
            $res = $this->supabaseRequest('GET', 'analistas?id=eq.' . urlencode($id) . '&select=*');
            if (!empty($res)) return $res[0];
        }

        $users = $this->readJson($this->usersFile);
        foreach ($users as $u) {
            if ($u['id'] === $id) return $u;
        }
        return null;
    }

    public function addAnalista(array $analista): array {
        if (empty($analista['id'])) {
            $analista['id'] = 'usr_' . substr(uniqid(), -8);
        }
        if (empty($analista['created_at'])) {
            $analista['created_at'] = date('Y-m-d H:i:s');
        }

        if ($this->useSupabase) {
            $res = $this->supabaseRequest('POST', 'analistas', $analista);
            if (!empty($res)) return $res[0];
        }

        $users = $this->readJson($this->usersFile);
        $users[] = $analista;
        $this->writeJson($this->usersFile, $users);
        return $analista;
    }

    public function updateAnalista(string $id, array $dados): bool {
        $dadosPatch = [];
        if (!empty($dados['senha'])) {
            $dadosPatch['senha_hash'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        }
        if (isset($dados['nome'])) {
            $dadosPatch['nome'] = $dados['nome'];
            $partes = explode(' ', trim($dados['nome']));
            $dadosPatch['avatar'] = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : substr($partes[0], 1, 1)));
        }
        if (isset($dados['email'])) {
            $dadosPatch['email'] = $dados['email'];
        }
        if (isset($dados['cargo'])) {
            $dadosPatch['cargo'] = $dados['cargo'];
        }
        if (isset($dados['ativo'])) {
            $dadosPatch['ativo'] = (bool)$dados['ativo'];
        }
        $dadosPatch['updated_at'] = date('Y-m-d H:i:s');

        if ($this->useSupabase) {
            $res = $this->supabaseRequest('PATCH', 'analistas?id=eq.' . urlencode($id), $dadosPatch);
            if ($res !== null) return true;
        }

        $users = $this->readJson($this->usersFile);
        $found = false;
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u = array_merge($u, $dadosPatch);
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
        if ($this->useSupabase) {
            $this->supabaseRequest('DELETE', 'analistas?id=eq.' . urlencode($id));
            return true;
        }

        $users = $this->readJson($this->usersFile);
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
        $tickets = [];

        if ($this->useSupabase) {
            $params = ['select=*', 'order=created_at.desc'];
            if (!empty($filters['analista'])) {
                $params[] = 'analista_nome=eq.' . urlencode($filters['analista']);
            }
            if (!empty($filters['tipo'])) {
                $params[] = 'tipo=eq.' . urlencode($filters['tipo']);
            }
            if (!empty($filters['status'])) {
                $params[] = 'status=eq.' . urlencode($filters['status']);
            }
            $queryStr = implode('&', $params);
            $res = $this->supabaseRequest('GET', 'tickets?' . $queryStr);
            if ($res !== null) {
                $tickets = $res;
            }
        }

        if (empty($tickets) && !$this->useSupabase) {
            $tickets = $this->readJson($this->ticketsFile);
        }

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

            // Filtro por Período: hoje, semana, mes
            $ticketDate = substr($t['data_hora'] ?? $t['created_at'], 0, 10);
            $hoje = date('Y-m-d');

            if (!empty($filters['periodo'])) {
                if ($filters['periodo'] === 'hoje') {
                    if ($ticketDate !== $hoje) return false;
                } elseif ($filters['periodo'] === 'semana') {
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
        if ($this->useSupabase) {
            $res = $this->supabaseRequest('GET', 'tickets?id=eq.' . urlencode($id) . '&select=*');
            if (!empty($res)) return $res[0];
        }

        $tickets = $this->readJson($this->ticketsFile);
        foreach ($tickets as $t) {
            if ($t['id'] === $id) return $t;
        }
        return null;
    }

    public function addTicket(array $ticket): array {
        if (empty($ticket['id'])) {
            $ticket['id'] = 'tck_' . substr(uniqid(), -8);
        }
        if (empty($ticket['created_at'])) {
            $ticket['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($ticket['status'])) {
            $ticket['status'] = 'Em Aberto';
        }

        if ($this->useSupabase) {
            $res = $this->supabaseRequest('POST', 'tickets', $ticket);
            if (!empty($res)) return $res[0];
        }

        $tickets = $this->readJson($this->ticketsFile);
        $tickets[] = $ticket;
        $this->writeJson($this->ticketsFile, $tickets);
        return $ticket;
    }

    public function updateTicket(string $id, array $dados): bool {
        $dados['updated_at'] = date('Y-m-d H:i:s');

        if ($this->useSupabase) {
            $res = $this->supabaseRequest('PATCH', 'tickets?id=eq.' . urlencode($id), $dados);
            if ($res !== null) return true;
        }

        $tickets = $this->readJson($this->ticketsFile);
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] === $id) {
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
        $ticket = $this->findTicketById($id);
        if (!$ticket) return null;

        $novoStatus = ($ticket['status'] ?? 'Em Aberto') === 'Concluído' ? 'Em Aberto' : 'Concluído';
        $concluidoEm = $novoStatus === 'Concluído' ? date('Y-m-d H:i:s') : null;

        $dadosPatch = [
            'status' => $novoStatus,
            'concluido_em' => $concluidoEm,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->useSupabase) {
            $this->supabaseRequest('PATCH', 'tickets?id=eq.' . urlencode($id), $dadosPatch);
            return $novoStatus;
        }

        $tickets = $this->readJson($this->ticketsFile);
        foreach ($tickets as &$t) {
            if ($t['id'] === $id) {
                $t['status'] = $novoStatus;
                $t['concluido_em'] = $concluidoEm;
                $t['updated_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        $this->writeJson($this->ticketsFile, $tickets);
        return $novoStatus;
    }

    public function deleteTicket(string $id): bool {
        if ($this->useSupabase) {
            $this->supabaseRequest('DELETE', 'tickets?id=eq.' . urlencode($id));
            return true;
        }

        $tickets = $this->readJson($this->ticketsFile);
        $novos = array_values(array_filter($tickets, fn($t) => $t['id'] !== $id));
        if (count($novos) !== count($tickets)) {
            $this->writeJson($this->ticketsFile, $novos);
            return true;
        }
        return false;
    }

    // ==========================================
    // SEED INICIAL COM DADOS DE EXEMPLO (LOCAL)
    // ==========================================
    private function ensureSeedData(): void {
        if ($this->useSupabase) return;

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
                ]
            ];
            $this->writeJson($this->usersFile, $seedUsers);
        }

        // Tickets Iniciais
        if (!file_exists($this->ticketsFile) || empty($this->readJson($this->ticketsFile))) {
            $agora = date('Y-m-d H:i:s');
            $ontem = date('Y-m-d H:i:s', strtotime('-1 day'));
            $doisDias = date('Y-m-d H:i:s', strtotime('-2 days'));

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
                ]
            ];
            $this->writeJson($this->ticketsFile, $seedTickets);
        }
    }
}
