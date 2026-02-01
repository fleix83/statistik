<?php
/**
 * Options: Create new option (writes directly to option_definitions)
 * POST /options/create.php
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$data = getJsonBody();
$section = trim($data['section'] ?? '');
$label = trim($data['label'] ?? '');
$sortOrder = intval($data['sort_order'] ?? 0);
$keywords = $data['keywords'] ?? [];

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Invalid section');
}

if (empty($label)) {
    errorResponse('Label is required');
}

// Validate and sanitize keywords
if (!is_array($keywords)) {
    $keywords = [];
}
$keywords = array_map('trim', $keywords);
$keywords = array_filter($keywords, fn($k) => strlen($k) > 0);
$keywords = array_values($keywords);

$db = getDB();

// Check for duplicate in option_definitions
$stmt = $db->prepare('SELECT id FROM option_definitions WHERE section = ? AND label = ?');
$stmt->execute([$section, $label]);
if ($stmt->fetch()) {
    errorResponse('Eine Option mit diesem Namen existiert bereits in dieser Kategorie');
}

try {
    // Insert directly into option_definitions table
    $keywordsJson = !empty($keywords) ? json_encode($keywords) : null;

    $stmt = $db->prepare('
        INSERT INTO option_definitions (section, label, sort_order, is_active, keywords)
        VALUES (?, ?, ?, 1, ?)
    ');
    $result = $stmt->execute([$section, $label, $sortOrder, $keywordsJson]);

    if (!$result) {
        errorResponse('Insert failed: ' . implode(', ', $stmt->errorInfo()), 500);
    }

    $newId = $db->lastInsertId();

    if (!$newId) {
        errorResponse('No ID returned after insert', 500);
    }

    jsonResponse([
        'id' => (int)$newId,
        'section' => $section,
        'label' => $label,
        'sort_order' => $sortOrder,
        'is_active' => true,
        'keywords' => $keywords
    ], 201);

} catch (Exception $e) {
    errorResponse('Erstellen fehlgeschlagen: ' . $e->getMessage(), 500);
}
