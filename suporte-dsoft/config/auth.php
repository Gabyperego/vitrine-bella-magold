<?php
/**
 * Autenticação e Sessão — dsoft Suporte
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function isAuthenticated(): bool {
    return !empty($_SESSION['dsoft_user_id']);
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array {
    if (!isAuthenticated()) return null;
    return $_SESSION['dsoft_user'] ?? null;
}

function login(string $email, string $senha): bool {
    $db = Database::getInstance();
    $user = $db->findAnalistaByEmail($email);
    
    if (!$user) {
        return false;
    }

    if (password_verify($senha, $user['senha_hash'])) {
        $_SESSION['dsoft_user_id'] = $user['id'];
        $_SESSION['dsoft_user'] = [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'cargo' => $user['cargo'] ?? 'Analista',
            'avatar' => $user['avatar'] ?? strtoupper(substr($user['nome'], 0, 2))
        ];
        return true;
    }

    return false;
}

function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['dsoft_user_id']);
    unset($_SESSION['dsoft_user']);
    session_destroy();
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}
