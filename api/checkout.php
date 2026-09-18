<?php
/**
 * API REST: Finalização de Pedido e Roteamento WhatsApp
 * Gravação em sales e sale_items com rastreabilidade de case_id e seller_id
 */

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método inválido.'], 405);
}

// Recebe payload JSON ou POST normal
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

if (empty($data)) {
    $data = $_POST;
}

$clienteNome = trim($data['cliente_nome'] ?? '');
$clienteTelefone = trim($data['cliente_telefone'] ?? '');
$observacoes = trim($data['observacoes'] ?? '');
$sellerId = $data['vendedora_id'] ?? $data['seller_id'] ?? null;
$itens = $data['itens'] ?? [];

// Validações
if (empty($clienteNome)) {
    jsonResponse(['success' => false, 'error' => 'Por favor, informe seu nome.'], 400);
}

if (empty($sellerId)) {
    jsonResponse(['success' => false, 'error' => 'Por favor, selecione quem vai te atender.'], 400);
}

if (empty($itens) || !is_array($itens)) {
    jsonResponse(['success' => false, 'error' => 'Seu carrinho está vazio.'], 400);
}

try {
    $supabase = Supabase::getInstance();

    // 1. Busca vendedora selecionada (sellers)
    $vendedoras = $supabase->select('sellers', '*', ['id' => 'eq.' . $sellerId]);
    if (empty($vendedoras)) {
        jsonResponse(['success' => false, 'error' => 'Vendedora selecionada não encontrada.'], 404);
    }
    $vendedora = $vendedoras[0];
    $vendedoraNome = $vendedora['nome'];
    $whatsappDestino = sanitizeWhatsApp($vendedora['whatsapp']);

    if (empty($whatsappDestino)) {
        jsonResponse(['success' => false, 'error' => 'Número de WhatsApp da vendedora não configurado.'], 400);
    }

    // 2. Processa itens, subtotais e busca case_id de cada produto
    $totalGeral = 0.0;
    $itensVendaDb = [];
    $linhasMensagem = [];

    // Cache de produtos para pegar o case_id
    $todosProdutos = $supabase->select('products');
    $mapProdutos = [];
    foreach ($todosProdutos as $prod) {
        $mapProdutos[$prod['id']] = $prod;
    }

    foreach ($itens as $item) {
        $prodId = $item['id'] ?? null;
        $nomeItem = $item['nome'] ?? 'Peça';
        $qtd = max(1, (int)($item['quantidade'] ?? 1));
        $precoUnit = (float)($item['preco'] ?? 0.0);
        $subtotal = $precoUnit * $qtd;
        $totalGeral += $subtotal;

        $caseId = null;
        if ($prodId && isset($mapProdutos[$prodId])) {
            $caseId = $mapProdutos[$prodId]['case_id'] ?? null;
            // Atualiza estoque
            $novoEstoque = max(0, ((int)($mapProdutos[$prodId]['estoque'] ?? 1)) - $qtd);
            $dadosAtualizar = ['estoque' => $novoEstoque];
            if ($novoEstoque === 0) {
                $dadosAtualizar['status'] = 'Vendido';
            }
            $supabase->update('products', $dadosAtualizar, ['id' => 'eq.' . $prodId]);
        }

        $itensVendaDb[] = [
            'product_id' => $prodId,
            'case_id' => $caseId,
            'seller_id' => $sellerId,
            'produto_nome' => $nomeItem,
            'preco_unitario' => $precoUnit,
            'quantidade' => $qtd,
            'subtotal' => $subtotal,
            'created_at' => date('c')
        ];

        // Linha no padrão: • Nome da Peça — 1x — R$ 35,00
        $linhasMensagem[] = "• " . $nomeItem . " — " . $qtd . "x — " . formatMoney($precoUnit);
    }

    // 3. Cria registro na tabela sales
    $numeroVenda = 'VEN-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

    $vendaPayload = [
        'numero_venda' => $numeroVenda,
        'cliente_nome' => $clienteNome,
        'cliente_telefone' => $clienteTelefone,
        'observacoes' => $observacoes,
        'seller_id' => $sellerId,
        'forma_pagamento' => 'WhatsApp / A Combinar',
        'total' => $totalGeral,
        'status' => 'Pendente',
        'created_at' => date('c')
    ];

    $vendaCriada = $supabase->insert('sales', $vendaPayload);
    $vendaId = $vendaCriada[0]['id'] ?? null;

    // 4. Salva itens em sale_items vinculando ao case_id
    if ($vendaId) {
        foreach ($itensVendaDb as $itDb) {
            $itDb['sale_id'] = $vendaId;
            $supabase->insert('sale_items', $itDb);
        }
    }

    // 5. Monta a mensagem para o WhatsApp exatamente no padrão exigido:
    $mensagem = "Olá, " . $vendedoraNome . "! Tenho interesse nestas peças:\n\n";
    $mensagem .= implode("\n", $linhasMensagem) . "\n\n";
    $mensagem .= "Total: " . formatMoney($totalGeral) . "\n\n";
    $mensagem .= "Cliente: " . $clienteNome;

    if (!empty($clienteTelefone)) {
        $mensagem .= "\nTelefone: " . formatPhone($clienteTelefone);
    }

    if (!empty($observacoes)) {
        $mensagem .= "\n\nObservação: " . $observacoes;
    } else {
        $mensagem .= "\n\nObservação: Gostaria de verificar a disponibilidade.";
    }

    $mensagem .= "\nRef: #" . $numeroVenda;

    // 6. Gera URL com rawurlencode
    $whatsappUrl = "https://wa.me/" . $whatsappDestino . "?text=" . rawurlencode($mensagem);

    jsonResponse([
        'success' => true,
        'venda_id' => $vendaId,
        'numero_venda' => $numeroVenda,
        'total' => $totalGeral,
        'total_formatado' => formatMoney($totalGeral),
        'vendedora' => $vendedoraNome,
        'mensagem' => $mensagem,
        'whatsapp_url' => $whatsappUrl
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Falha ao processar checkout: ' . $e->getMessage()], 500);
}
