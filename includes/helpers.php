<?php
/**
 * Funções Auxiliares (Helpers)
 */

if (!function_exists('formatMoney')) {
    /**
     * Formata um valor numérico para a moeda brasileira (R$ 1.234,56)
     */
    function formatMoney($value): string {
        $num = (float)$value;
        return 'R$ ' . number_format($num, 2, ',', '.');
    }
}

if (!function_exists('formatPhone')) {
    /**
     * Formata um número de telefone com máscara (ex: (67) 99999-9999)
     */
    function formatPhone(string $phone): string {
        $cleaned = preg_replace('/\D/', '', $phone);
        // Remove 55 se vier com DDI
        if (str_starts_with($cleaned, '55') && strlen($cleaned) >= 12) {
            $cleaned = substr($cleaned, 2);
        }
        
        if (strlen($cleaned) === 11) {
            return sprintf('(%s) %s-%s', substr($cleaned, 0, 2), substr($cleaned, 2, 5), substr($cleaned, 7));
        } elseif (strlen($cleaned) === 10) {
            return sprintf('(%s) %s-%s', substr($cleaned, 0, 2), substr($cleaned, 2, 4), substr($cleaned, 6));
        }
        return $phone;
    }
}

if (!function_exists('sanitizeWhatsApp')) {
    /**
     * Limpa o número para o padrão internacional do WhatsApp (55 + DDD + Número)
     */
    function sanitizeWhatsApp(string $phone): string {
        $cleaned = preg_replace('/\D/', '', $phone);
        if (empty($cleaned)) {
            return '';
        }
        // Se já começa com 55 e tem pelo menos 12 dígitos (55 + 2 DDD + 8/9 número)
        if (str_starts_with($cleaned, '55') && strlen($cleaned) >= 12) {
            return $cleaned;
        }
        // Se tem 10 ou 11 dígitos (DDD + número), adiciona 55 do Brasil
        if (strlen($cleaned) >= 10 && strlen($cleaned) <= 11) {
            return '55' . $cleaned;
        }
        return $cleaned;
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Formata data/hora para padrão brasileiro (DD/MM/AAAA HH:mm)
     */
    function formatDateTime(?string $dateStr): string {
        if (empty($dateStr)) return '-';
        try {
            $dt = new DateTime($dateStr);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return $dateStr;
        }
    }
}

if (!function_exists('formatDateOnly')) {
    /**
     * Formata apenas a data para padrão brasileiro (DD/MM/AAAA)
     */
    function formatDateOnly(?string $dateStr): string {
        if (empty($dateStr)) return '-';
        try {
            $dt = new DateTime($dateStr);
            return $dt->format('d/m/Y');
        } catch (Exception $e) {
            return $dateStr;
        }
    }
}

if (!function_exists('jsonResponse')) {
    /**
     * Envia resposta JSON estruturada
     */
    function jsonResponse($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitiza strings para exibição segura em HTML
     */
    function sanitize(?string $text): string {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('setFlashMessage')) {
    /**
     * Define mensagem de alerta de sessão
     */
    function setFlashMessage(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_message'] = [
            'type' => $type, // success, danger, warning, info
            'text' => $message
        ];
    }
}

if (!function_exists('getFlashMessage')) {
    /**
     * Recupera e consome a mensagem de alerta
     */
    function getFlashMessage(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (isset($_SESSION['flash_message'])) {
            $msg = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $msg;
        }
        return null;
    }
}

