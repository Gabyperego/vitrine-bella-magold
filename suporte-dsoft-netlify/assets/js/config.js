/**
 * Configuração Supabase — dsoft Suporte para Netlify
 * Insira aqui sua URL e Chave Anon do Supabase, ou configure diretamente pela tela do sistema.
 */
const SUPABASE_CONFIG = {
    url: localStorage.getItem('dsoft_supabase_url') || "https://SEU-PROJETO.supabase.co",
    anonKey: localStorage.getItem('dsoft_supabase_anon_key') || "SUA_CHAVE_ANON_AQUI"
};
