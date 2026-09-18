<?php
/**
 * Serviço Central de Comunicação com o Supabase (PostgreSQL, Auth e Storage)
 * Vitrine Online + Gestão de Estojos, Vendedoras e Vendas
 */

// Inicia sessão caso ainda não tenha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregador simples de arquivo .env
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
}

// Carrega o arquivo .env da raiz do projeto
loadEnv(dirname(__DIR__) . '/.env');

// Constantes Globais de Conexão
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://SEU-PROJETO.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'SUA_ANON_KEY_AQUI');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
define('SUPABASE_STORAGE_BUCKET', getenv('SUPABASE_STORAGE_BUCKET') ?: 'produtos');

define('STORE_NAME', getenv('STORE_NAME') ?: 'Bella Magold Semijoias');
define('STORE_TAGLINE', getenv('STORE_TAGLINE') ?: 'Semijoias que contam sua história');

date_default_timezone_set(getenv('STORE_TIMEZONE') ?: 'America/Campo_Grande');

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');

if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0777, true);
}

class Supabase {
    private static ?Supabase $instance = null;
    private string $url;
    private string $anonKey;
    private string $serviceRoleKey;
    private bool $isDemoMode = false;
    private string $localDbFile;

    public function __construct() {
        $this->url = rtrim(SUPABASE_URL, '/');
        $this->anonKey = SUPABASE_ANON_KEY;
        $this->serviceRoleKey = SUPABASE_SERVICE_ROLE_KEY;
        $this->localDbFile = DATA_PATH . '/local_db.json';

        // Detecta se está em modo de demonstração/local
        if (empty($this->url) || 
            str_contains($this->url, 'SEU-PROJETO') || 
            empty($this->anonKey) || 
            str_contains($this->anonKey, 'SUA_ANON_KEY')) {
            $this->isDemoMode = true;
            $this->initLocalDatabase();
        }
    }

    public static function getInstance(): Supabase {
        if (self::$instance === null) {
            self::$instance = new Supabase();
        }
        return self::$instance;
    }

    public function isDemo(): bool {
        return $this->isDemoMode;
    }

    /**
     * Chave de cabeçalho prioritária para operações no backend
     */
    private function getAuthHeaderKey(): string {
        return !empty($this->serviceRoleKey) && !str_contains($this->serviceRoleKey, 'SUA_SERVICE_ROLE')
            ? $this->serviceRoleKey
            : $this->anonKey;
    }

    /**
     * ============================================================
     * OPERAÇÕES DE BANCO DE DADOS (PostgREST)
     * ============================================================
     */

    public function select(string $table, string $columns = '*', array $filters = [], string $order = ''): array {
        if ($this->isDemoMode) {
            return $this->localSelect($table, $columns, $filters, $order);
        }

        $params = ['select' => $columns];
        foreach ($filters as $col => $expression) {
            $params[$col] = $expression;
        }
        if (!empty($order)) {
            $params['order'] = $order;
        }

        $url = $this->url . '/rest/v1/' . $table . '?' . http_build_query($params);
        $res = $this->curlRequest('GET', $url);
        return is_array($res) ? $res : [];
    }

    public function find(string $table, string $id, string $idColumn = 'id'): ?array {
        $res = $this->select($table, '*', [$idColumn => 'eq.' . $id]);
        return !empty($res) ? $res[0] : null;
    }

    public function insert(string $table, array $data): ?array {
        if ($this->isDemoMode) {
            return $this->localInsert($table, $data);
        }

        $url = $this->url . '/rest/v1/' . $table;
        $headers = ['Prefer: return=representation'];
        return $this->curlRequest('POST', $url, $data, $headers);
    }

    public function update(string $table, array $data, array $filters): ?array {
        if ($this->isDemoMode) {
            return $this->localUpdate($table, $data, $filters);
        }

        $url = $this->url . '/rest/v1/' . $table . '?' . http_build_query($filters);
        $headers = ['Prefer: return=representation'];
        return $this->curlRequest('PATCH', $url, $data, $headers);
    }

    public function delete(string $table, array $filters): bool {
        if ($this->isDemoMode) {
            return $this->localDelete($table, $filters);
        }

        $url = $this->url . '/rest/v1/' . $table . '?' . http_build_query($filters);
        $this->curlRequest('DELETE', $url);
        return true;
    }

    /**
     * ============================================================
     * AUTENTICAÇÃO (Supabase Auth)
     * ============================================================
     */
    public function signInWithPassword(string $email, string $password): array {
        if ($this->isDemoMode) {
            if (($email === 'admin@admin.com' && $password === 'admin123') ||
                ($email === 'admin@vitrine.com' && $password === 'admin123') ||
                ($email === 'admin' && $password === 'admin')) {
                return [
                    'success' => true,
                    'user' => [
                        'id' => 'a0000000-0000-0000-0000-000000000001',
                        'email' => $email,
                        'role' => 'Administrador',
                        'user_metadata' => ['nome' => 'Administrador']
                    ],
                    'access_token' => 'demo_token_' . bin2hex(random_bytes(16))
                ];
            }
            return [
                'success' => false,
                'error' => 'Credenciais inválidas no modo demonstração (Use: admin@admin.com e senha: admin123)'
            ];
        }

        $url = $this->url . '/auth/v1/token?grant_type=password';
        $payload = ['email' => $email, 'password' => $password];
        $response = $this->curlRequest('POST', $url, $payload, [], false);

        if (isset($response['access_token'])) {
            return [
                'success' => true,
                'user' => $response['user'] ?? [],
                'access_token' => $response['access_token']
            ];
        }

        $msg = $response['error_description'] ?? $response['msg'] ?? 'Erro de autenticação no Supabase Auth.';
        return ['success' => false, 'error' => $msg];
    }

    /**
     * ============================================================
     * STORAGE (Supabase Storage)
     * ============================================================
     */
    public function uploadFile(string $bucket, string $destinationPath, string $fileContent, string $mimeType = 'image/jpeg'): ?string {
        if ($this->isDemoMode) {
            $uploadsDir = ROOT_PATH . '/uploads/' . $bucket;
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0777, true);
            }
            $targetPath = $uploadsDir . '/' . basename($destinationPath);
            file_put_contents($targetPath, $fileContent);
            return 'uploads/' . $bucket . '/' . basename($destinationPath);
        }

        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . ltrim($destinationPath, '/');
        $key = $this->getAuthHeaderKey();

        $headers = [
            'Content-Type: ' . $mimeType,
            'x-upsert: true',
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return $this->getPublicUrl($bucket, $destinationPath);
        }
        return null;
    }

    public function getPublicUrl(string $bucket, string $path): string {
        if ($this->isDemoMode) {
            return 'uploads/' . $bucket . '/' . basename($path);
        }
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . ltrim($path, '/');
    }

    /**
     * ============================================================
     * HTTP CURL HELPER
     * ============================================================
     */
    private function curlRequest(string $method, string $url, ?array $body = null, array $customHeaders = [], bool $isRest = true) {
        $key = $this->getAuthHeaderKey();
        $headers = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ];

        if ($isRest) {
            $headers[] = 'Accept: application/json';
        }

        $headers = array_merge($headers, $customHeaders);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            return [];
        }

        $decoded = json_decode($result, true);
        return $decoded !== null ? $decoded : [];
    }

    /**
     * ============================================================
     * MODO DEMO / LOCAL COM UUIDs E RELACIONAMENTOS REAIS
     * Vendedora -> Estojo -> Produtos -> Vendas -> Fechamento
     * ============================================================
     */
    private function initLocalDatabase(): void {
        if (file_exists($this->localDbFile)) return;

        $gabyId = 'b1000000-0000-0000-0000-000000000001';
        $vanessaId = 'b2000000-0000-0000-0000-000000000002';

        $estojoGabyId = 'c1000000-0000-0000-0000-000000000001';
        $estojoVanessaId = 'c2000000-0000-0000-0000-000000000002';
        $estojoLojaId = 'c3000000-0000-0000-0000-000000000003';

        $initialData = [
            'sellers' => [
                [
                    'id' => $gabyId,
                    'nome' => 'Gaby',
                    'whatsapp' => '5567999887766',
                    'ativo' => true,
                    'foto_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
                    'created_at' => date('c', strtotime('-30 days'))
                ],
                [
                    'id' => $vanessaId,
                    'nome' => 'Vanessa',
                    'whatsapp' => '5567988776655',
                    'ativo' => true,
                    'foto_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
                    'created_at' => date('c', strtotime('-30 days'))
                ]
            ],
            'cases' => [
                [
                    'id' => $estojoGabyId,
                    'nome' => 'Estojo Gaby',
                    'descricao' => 'Estojo principal de semijoias finas sob responsabilidade da Gaby.',
                    'seller_id' => $gabyId,
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-25 days'))
                ],
                [
                    'id' => $estojoVanessaId,
                    'nome' => 'Estojo Vanessa',
                    'descricao' => 'Estojo de lançamentos e colares de luxo sob responsabilidade da Vanessa.',
                    'seller_id' => $vanessaId,
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-25 days'))
                ],
                [
                    'id' => $estojoLojaId,
                    'nome' => 'Estojo Mostruário Loja',
                    'descricao' => 'Peças de pronta entrega do mostruário físico da boutique.',
                    'seller_id' => $gabyId,
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-20 days'))
                ]
            ],
            'categories' => [
                ['id' => 'd1000000-0000-0000-0000-000000000001', 'nome' => 'Brincos', 'ativo' => true],
                ['id' => 'd2000000-0000-0000-0000-000000000002', 'nome' => 'Colares', 'ativo' => true],
                ['id' => 'd3000000-0000-0000-0000-000000000003', 'nome' => 'Pulseiras', 'ativo' => true],
                ['id' => 'd4000000-0000-0000-0000-000000000004', 'nome' => 'Anéis', 'ativo' => true],
                ['id' => 'd5000000-0000-0000-0000-000000000005', 'nome' => 'Pingentes', 'ativo' => true],
                ['id' => 'd6000000-0000-0000-0000-000000000006', 'nome' => 'Outros', 'ativo' => true]
            ],
            'products' => [
                [
                    'id' => 'e1000000-0000-0000-0000-000000000001',
                    'codigo' => 'BR-001',
                    'nome' => 'Brinco Dourado Argola Elegance',
                    'descricao' => 'Brinco argola clássica banhada a ouro 18k com acabamento polido de alto brilho.',
                    'categoria' => 'Brincos',
                    'preco' => 35.00,
                    'case_id' => $estojoGabyId,
                    'imagem_url' => 'https://images.unsplash.com/photo-1630019852942-f89202989a59?w=600&auto=format&fit=crop&q=80',
                    'estoque' => 5,
                    'status' => 'Disponível',
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-15 days'))
                ],
                [
                    'id' => 'e2000000-0000-0000-0000-000000000002',
                    'codigo' => 'COL-002',
                    'nome' => 'Colar Coração Zircônia Cristal',
                    'descricao' => 'Colar delicado com pingente de coração em zircônia cravejada, banhado a ouro 18k.',
                    'categoria' => 'Colares',
                    'preco' => 89.00,
                    'case_id' => $estojoVanessaId,
                    'imagem_url' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80',
                    'estoque' => 3,
                    'status' => 'Disponível',
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-14 days'))
                ],
                [
                    'id' => 'e3000000-0000-0000-0000-000000000003',
                    'codigo' => 'PUL-003',
                    'nome' => 'Pulseira Dourada Elos Cartier',
                    'descricao' => 'Pulseira feminina com elos estilo Cartier e fecho boia seguro e refinado.',
                    'categoria' => 'Pulseiras',
                    'preco' => 45.00,
                    'case_id' => $estojoGabyId,
                    'imagem_url' => 'https://images.unsplash.com/photo-1611591475883-9b932bb8269e?w=600&auto=format&fit=crop&q=80',
                    'estoque' => 8,
                    'status' => 'Disponível',
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-12 days'))
                ],
                [
                    'id' => 'e4000000-0000-0000-0000-000000000004',
                    'codigo' => 'ANE-004',
                    'nome' => 'Anel Solitário Cristal Luxo',
                    'descricao' => 'Anel solitário elegante com pedra central brilhante lapidação navete.',
                    'categoria' => 'Anéis',
                    'preco' => 69.90,
                    'case_id' => $estojoVanessaId,
                    'imagem_url' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&auto=format&fit=crop&q=80',
                    'estoque' => 4,
                    'status' => 'Disponível',
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-10 days'))
                ],
                [
                    'id' => 'e5000000-0000-0000-0000-000000000005',
                    'codigo' => 'PIN-005',
                    'nome' => 'Pingente Estrela Radiante',
                    'descricao' => 'Pingente delicado em formato de estrela com micropavê de zircônias.',
                    'categoria' => 'Pingentes',
                    'preco' => 39.00,
                    'case_id' => $estojoLojaId,
                    'imagem_url' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80',
                    'estoque' => 6,
                    'status' => 'Disponível',
                    'ativo' => true,
                    'created_at' => date('c', strtotime('-8 days'))
                ]
            ],
            'sales' => [
                [
                    'id' => 'f1000000-0000-0000-0000-000000000001',
                    'numero_venda' => 'VEN-1001',
                    'cliente_nome' => 'Maria Silva',
                    'cliente_telefone' => '67999881122',
                    'observacoes' => 'Gostaria de verificar disponibilidade para entrega hoje.',
                    'seller_id' => $gabyId,
                    'forma_pagamento' => 'PIX',
                    'total' => 125.00,
                    'status' => 'Concluída',
                    'created_at' => date('c', strtotime('-2 days'))
                ],
                [
                    'id' => 'f2000000-0000-0000-0000-000000000002',
                    'numero_venda' => 'VEN-1002',
                    'cliente_nome' => 'Juliana Ferreira',
                    'cliente_telefone' => '67998877112',
                    'observacoes' => 'Embalar para presente por favor.',
                    'seller_id' => $vanessaId,
                    'forma_pagamento' => 'Cartão de Crédito',
                    'total' => 158.90,
                    'status' => 'Concluída',
                    'created_at' => date('c', strtotime('-1 day'))
                ]
            ],
            'sale_items' => [
                [
                    'id' => 'fa000000-0000-0000-0000-000000000001',
                    'sale_id' => 'f1000000-0000-0000-0000-000000000001',
                    'product_id' => 'e1000000-0000-0000-0000-000000000001',
                    'case_id' => $estojoGabyId,
                    'seller_id' => $gabyId,
                    'produto_nome' => 'Brinco Dourado Argola Elegance',
                    'preco_unitario' => 35.00,
                    'quantidade' => 1,
                    'subtotal' => 35.00,
                    'created_at' => date('c', strtotime('-2 days'))
                ],
                [
                    'id' => 'fa000000-0000-0000-0000-000000000002',
                    'sale_id' => 'f1000000-0000-0000-0000-000000000001',
                    'product_id' => 'e3000000-0000-0000-0000-000000000003',
                    'case_id' => $estojoGabyId,
                    'seller_id' => $gabyId,
                    'produto_nome' => 'Pulseira Dourada Elos Cartier',
                    'preco_unitario' => 45.00,
                    'quantidade' => 2,
                    'subtotal' => 90.00,
                    'created_at' => date('c', strtotime('-2 days'))
                ],
                [
                    'id' => 'fa000000-0000-0000-0000-000000000003',
                    'sale_id' => 'f2000000-0000-0000-0000-000000000002',
                    'product_id' => 'e2000000-0000-0000-0000-000000000002',
                    'case_id' => $estojoVanessaId,
                    'seller_id' => $vanessaId,
                    'produto_nome' => 'Colar Coração Zircônia Cristal',
                    'preco_unitario' => 89.00,
                    'quantidade' => 1,
                    'subtotal' => 89.00,
                    'created_at' => date('c', strtotime('-1 day'))
                ],
                [
                    'id' => 'fa000000-0000-0000-0000-000000000004',
                    'sale_id' => 'f2000000-0000-0000-0000-000000000002',
                    'product_id' => 'e4000000-0000-0000-0000-000000000004',
                    'case_id' => $estojoVanessaId,
                    'seller_id' => $vanessaId,
                    'produto_nome' => 'Anel Solitário Cristal Luxo',
                    'preco_unitario' => 69.90,
                    'quantidade' => 1,
                    'subtotal' => 69.90,
                    'created_at' => date('c', strtotime('-1 day'))
                ]
            ],
            'case_closings' => []
        ];

        file_put_contents($this->localDbFile, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function getLocalData(): array {
        if (!file_exists($this->localDbFile)) {
            $this->initLocalDatabase();
        }
        $c = file_get_contents($this->localDbFile);
        return json_decode($c, true) ?: [];
    }

    private function saveLocalData(array $data): void {
        file_put_contents($this->localDbFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function localSelect(string $table, string $columns, array $filters, string $order): array {
        $db = $this->getLocalData();
        $items = $db[$table] ?? [];

        if (!empty($filters)) {
            $items = array_filter($items, function($row) use ($filters) {
                foreach ($filters as $col => $expr) {
                    $rowVal = (string)($row[$col] ?? '');
                    if (str_starts_with($expr, 'eq.')) {
                        $target = substr($expr, 3);
                        if (is_bool($row[$col] ?? null)) {
                            $targetBool = ($target === 'true' || $target === '1');
                            if ($row[$col] !== $targetBool) return false;
                        } else {
                            if ($rowVal !== $target) return false;
                        }
                    } elseif (str_starts_with($expr, 'neq.')) {
                        if ($rowVal === substr($expr, 4)) return false;
                    } elseif (str_starts_with($expr, 'gt.')) {
                        $val = (float)substr($expr, 3);
                        if ((float)$rowVal <= $val) return false;
                    } elseif (str_starts_with($expr, 'gte.')) {
                        $val = (float)substr($expr, 4);
                        if ((float)$rowVal < $val) return false;
                    } elseif (str_starts_with($expr, 'ilike.')) {
                        $pat = str_replace('%', '', substr($expr, 6));
                        if (stripos($rowVal, $pat) === false) return false;
                    }
                }
                return true;
            });
        }

        if (!empty($order)) {
            $parts = explode('.', $order);
            $col = $parts[0];
            $dir = $parts[1] ?? 'asc';
            usort($items, function($a, $b) use ($col, $dir) {
                $valA = $a[$col] ?? 0;
                $valB = $b[$col] ?? 0;
                return ($dir === 'desc') ? ($valB <=> $valA) : ($valA <=> $valB);
            });
        }

        return array_values($items);
    }

    private function localInsert(string $table, array $data): array {
        $db = $this->getLocalData();
        if (!isset($db[$table])) {
            $db[$table] = [];
        }

        if (!isset($data['id'])) {
            // Gera UUID v4
            $data['id'] = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = date('c');
        }

        $db[$table][] = $data;
        $this->saveLocalData($db);
        return [$data];
    }

    private function localUpdate(string $table, array $data, array $filters): array {
        $db = $this->getLocalData();
        if (!isset($db[$table])) return [];

        $updated = [];
        foreach ($db[$table] as &$row) {
            $match = true;
            foreach ($filters as $col => $expr) {
                $rowVal = (string)($row[$col] ?? '');
                if (str_starts_with($expr, 'eq.')) {
                    if ($rowVal !== substr($expr, 3)) {
                        $match = false;
                        break;
                    }
                }
            }
            if ($match) {
                $row = array_merge($row, $data);
                $row['updated_at'] = date('c');
                $updated[] = $row;
            }
        }

        $this->saveLocalData($db);
        return $updated;
    }

    private function localDelete(string $table, array $filters): bool {
        $db = $this->getLocalData();
        if (!isset($db[$table])) return false;

        $db[$table] = array_filter($db[$table], function($row) use ($filters) {
            foreach ($filters as $col => $expr) {
                $rowVal = (string)($row[$col] ?? '');
                if (str_starts_with($expr, 'eq.')) {
                    if ($rowVal === substr($expr, 3)) return false;
                }
            }
            return true;
        });

        $this->saveLocalData($db);
        return true;
    }
}

