<?php
/**
 * Options: Create new option (writes to draft table)
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

// Check for duplicate in published options
$stmt = $db->prepare('SELECT id FROM option_definitions WHERE section = ? AND label = ?');
$stmt->execute([$section, $label]);
if ($stmt->fetch()) {
    errorResponse('Eine Option mit diesem Namen existiert bereits in dieser Kategorie');
}

// Check for duplicate in draft creates
$stmt = $db->prepare('SELECT id FROM option_definitions_draft WHERE section = ? AND label = ? AND action = ?');
$stmt->execute([$section, $label, 'create']);
if ($stmt->fetch()) {
    errorResponse('Diese Option wurde bereits als Entwurf erstellt');
}

$db->beginTransaction();

try {
    // Insert into draft table
    $stmt = $db->prepare('
        INSERT INTO option_definitions_draft (original_id, section, label, sort_order, is_active, keywords, action)
        VALUES (NULL, ?, ?, ?, 1, ?, ?)
    ');
    $keywordsJson = !empty($keywords) ? json_encode($keywords) : null;
    $stmt->execute([$section, $label, $sortOrder, $keywordsJson, 'create']);

    $draftId = $db->lastInsertId();

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'id' => 'new_' . $draftId,
        'section' => $section,
        'label' => $label,
        'sort_order' => $sortOrder,
        'is_active' => true,
        'keywords' => $keywords,
        'draft_action' => 'create',
        'draft_id' => $draftId
    ], 201);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Erstellen fehlgeschlagen: ' . $e->getMessage(), 500);
}
