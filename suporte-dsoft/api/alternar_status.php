<?php
/**
 * Alternar Status do Ticket (Em Aberto <-> Concluído)
 * Responde tanto via AJAX (JSON) quanto via POST padrão com redirect
 */
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$id = $_POST['id'] ?? $_GET['id'] ?? null;
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$id) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }
    header('Location: ../lancamentos.php');
    exit;
}

$db = Database::getInstance();
$novoStatus = $db->toggleTicketStatus($id);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $id,
        'status' => $novoStatus
    ]);
    exit;
}

setFlash('success', "Status do chamado atualizado para '{$novoStatus}'.");
$referer = $_SERVER['HTTP_REFERER'] ?? '../lancamentos.php';
header("Location: {$referer}");
exit;
