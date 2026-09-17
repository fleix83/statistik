<?php
/**
 * Card Background Image Upload
 * POST /colors/upload.php?card=X  - Upload a card background image (admin only)
 *
 * Multipart field "image". Stores the file under api/uploads/card-images/ and
 * returns its path relative to the api root. The path only becomes active once
 * it is persisted as bg_image via PUT /colors/index.php (modal "Speichern").
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$validCards = ['person', 'zeitfenster', 'thema', 'referenz'];
$card = $_GET['card'] ?? null;

if (!$card || !in_array($card, $validCards)) {
    errorResponse('Ungültige Karte. Erlaubt: ' . implode(', ', $validCards), 400);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    errorResponse('Kein Bild hochgeladen', 400);
}

$file = $_FILES['image'];

if ($file['size'] > 4 * 1024 * 1024) {
    errorResponse('Bild zu gross (max. 4 MB)', 400);
}

// Determine the extension from the actual file content, not the client filename
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedTypes = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!isset($allowedTypes[$mime])) {
    errorResponse('Ungültiges Bildformat. Erlaubt: PNG, JPEG, WebP, GIF', 400);
}

$dir = __DIR__ . '/../uploads/card-images';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    errorResponse('Upload-Verzeichnis konnte nicht erstellt werden', 500);
}

$filename = $card . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mime];

if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
    errorResponse('Bild konnte nicht gespeichert werden', 500);
}

jsonResponse(['path' => 'uploads/card-images/' . $filename]);
