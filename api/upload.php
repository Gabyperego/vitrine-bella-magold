<?php
/**
 * API REST: Upload de Imagens para Supabase Storage
 */

require_once dirname(__DIR__) . '/config/supabase.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas administradores logados podem fazer upload
if (!isset($_SESSION['admin_user'])) {
    jsonResponse(['success' => false, 'error' => 'Acesso não autorizado.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método inválido.'], 405);
}

if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'Nenhuma imagem foi enviada ou ocorreu um erro no upload.'], 400);
}

$file = $_FILES['imagem'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($file['type'], $allowedTypes)) {
    jsonResponse(['success' => false, 'error' => 'Formato de imagem inválido. Aceitos: JPG, PNG, WEBP e GIF.'], 400);
}

// Tamanho máximo: 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(['success' => false, 'error' => 'A imagem excede o tamanho máximo de 5MB.'], 400);
}

try {
    $supabase = Supabase::getInstance();
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    $content = file_get_contents($file['tmp_name']);

    $publicUrl = $supabase->uploadFile(SUPABASE_STORAGE_BUCKET, $filename, $content, $file['type']);

    if (!$publicUrl) {
        throw new Exception('Não foi possível gravar a imagem no Supabase Storage.');
    }

    jsonResponse([
        'success' => true,
        'url' => $publicUrl,
        'filename' => $filename
    ]);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Erro ao enviar imagem: ' . $e->getMessage()
    ], 500);
}
