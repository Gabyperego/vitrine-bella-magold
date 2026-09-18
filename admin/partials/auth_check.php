<?php
/**
 * Verificação de Autenticação para Rotas Administrativas
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['admin_user'];
$userRole = $currentUser['role'] ?? 'Administrador';
$userName = $currentUser['user_metadata']['nome'] ?? $currentUser['email'] ?? 'Administrador';

