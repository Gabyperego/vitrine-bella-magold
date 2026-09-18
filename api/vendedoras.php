<?php
/**
 * API REST: Vendedoras Ativas (sellers)
 */

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $supabase = Supabase::getInstance();
    
    // Na vitrine, apenas vendedoras com status ativo
    $apenasAtivas = ($_GET['todas'] ?? '0') !== '1';
    
    $filters = [];
    if ($apenasAtivas) {
        $filters['ativo'] = 'eq.true';
    }

    $vendedoras = $supabase->select('sellers', '*', $filters, 'nome.asc');

    foreach ($vendedoras as &$v) {
        $v['whatsapp_formatado'] = formatPhone($v['whatsapp']);
        $v['whatsapp_limpo'] = sanitizeWhatsApp($v['whatsapp']);
    }

    jsonResponse([
        'success' => true,
        'count' => count($vendedoras),
        'data' => $vendedoras
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
