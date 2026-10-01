<?php
/**
 * Excluir Ticket — dsoft Suporte
 */
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$id = $_POST['id'] ?? $_GET['id'] ?? null;

if ($id) {
    $db = Database::getInstance();
    $db->deleteTicket($id);
    setFlash('success', 'Chamado excluído com sucesso.');
}

$referer = $_SERVER['HTTP_REFERER'] ?? '../lancamentos.php';
header("Location: {$referer}");
exit;
