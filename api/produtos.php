<?php
/**
 * API REST: Produtos da Vitrine Pública
 * Regras: Ativos, Disponíveis, Estoque > 0 e pertencentes a um Estojo Ativo
 */

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $supabase = Supabase::getInstance();

    // Carrega estojos ativos
    $estojosAtivos = $supabase->select('cases', '*', ['ativo' => 'eq.true']);
    $estojosAtivosIds = array_column($estojosAtivos, 'id');

    // Carrega produtos com status Disponível e ativo
    $produtos = $supabase->select('products', '*', [
        'status' => 'eq.Disponível',
        'ativo' => 'eq.true'
    ], 'created_at.desc');

    // Filtra no PHP: Estoque > 0 e pertencentes a um estojo ativo
    $produtosFiltrados = array_filter($produtos, function($p) use ($estojosAtivosIds) {
        $estoque = (int)($p['estoque'] ?? 0);
        $caseId = $p['case_id'] ?? null;
        return $estoque > 0 && $caseId && in_array($caseId, $estojosAtivosIds);
    });

    // Filtros de busca e categoria opcionais
    $categoria = trim($_GET['categoria'] ?? '');
    $busca = trim($_GET['busca'] ?? '');

    if (!empty($categoria) && $categoria !== 'Todos') {
        $produtosFiltrados = array_filter($produtosFiltrados, fn($p) => ($p['categoria'] ?? '') === $categoria);
    }

    if (!empty($busca)) {
        $termo = mb_strtolower($busca, 'UTF-8');
        $produtosFiltrados = array_filter($produtosFiltrados, function($p) use ($termo) {
            $nome = mb_strtolower($p['nome'] ?? '', 'UTF-8');
            $desc = mb_strtolower($p['descricao'] ?? '', 'UTF-8');
            $cod = mb_strtolower($p['codigo'] ?? '', 'UTF-8');
            return str_contains($nome, $termo) || str_contains($desc, $termo) || str_contains($cod, $termo);
        });
    }

    $resultado = [];
    foreach ($produtosFiltrados as $p) {
        $p['preco_formatado'] = formatMoney($p['preco']);
        if (empty($p['imagem_url'])) {
            $p['imagem_url'] = 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80';
        }
        $resultado[] = $p;
    }

    jsonResponse([
        'success' => true,
        'count' => count($resultado),
        'data' => $resultado,
        'is_demo' => $supabase->isDemo()
    ]);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
