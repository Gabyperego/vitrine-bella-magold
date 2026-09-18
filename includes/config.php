<?php
/**
 * Configurações Gerais do Sistema
 * Vitrine Online + Supabase
 * Ponte de Compatibilidade para config/supabase.php
 */

// Inicia sessão caso ainda não tenha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do Supabase
// Substitua pelas credenciais do seu projeto em: https://app.supabase.com -> Project Settings -> API
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://SEU-PROJETO.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'SUA_ANON_KEY_AQUI');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: ''); // Opcional, para operações administrativas

// Nome do Bucket de Storage no Supabase para imagens de produtos e fotos
define('SUPABASE_STORAGE_BUCKET', 'produtos');

// Configurações da Loja
define('STORE_NAME', getenv('STORE_NAME') ?: 'Bella Magold Semijoias');
define('STORE_TAGLINE', getenv('STORE_TAGLINE') ?: 'Semijoias que contam sua história');
define('STORE_CURRENCY', 'R$');

// Configurações de Fuso Horário e Idioma
date_default_timezone_set('America/Campo_Grande');

// Caminhos base
define('BASE_PATH', dirname(__DIR__));
define('DATA_DIR', BASE_PATH . '/data');

// Cria pasta de dados locais para cache/modo fallback se não existir
if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0777, true);
}

require_once dirname(__DIR__) . '/config/supabase.php';
