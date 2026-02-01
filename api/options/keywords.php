<?php
/**
 * Options: Update keywords for a Thema option
 * PUT /options/keywords.php?id=123
 * Body: { "keywords": ["keyword1", "keyword2"] }
 *
 * Creates or updates a draft entry with the new keywords.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$id = $_GET['id'] ?? null;
$data = getJsonBody();
$keywords = $data['keywords'] ?? [];

// Validate keywords is an array
if (!is_array($keywords)) {
    errorResponse('Keywords must be an array');
}

// Sanitize keywords
$keywords = array_map('trim', $keywords);
$keywords = array_filter($keywords, fn($k) => strlen($k) > 0);
$keywords = array_values($keywords);

$db = getDB();

// Check if this is a new draft option (id starts with 'new_')
if (is_string($id) && strpos($id, 'new_') === 0) {
    $draftId = intval(substr($id, 4));

    // Update the draft directly
    $stmt = $db->prepare('SELECT id, section FROM option_definitions_draft WHERE id = ?');
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch();

    if (!$draft) {
        errorResponse('Draft option not found', 404);
    }

    if ($draft['section'] !== 'thema') {
        errorResponse('Keywords können nur für Thema-Optionen gesetzt werden');
    }

    $stmt = $db->prepare('UPDATE option_definitions_draft SET keywords = ? WHERE id = ?');
    $stmt->execute([json_encode($keywords), $draftId]);

    jsonResponse([
        'success' => true,
        'keywords' => $keywords
    ]);
}

// Regular published option
$id = intval($id);
if ($id <= 0) {
    errorResponse('Invalid ID');
}

// Check if option exists and is a thema option
$stmt = $db->prepare('SELECT id, section, label, sort_order, is_active, keywords FROM option_definitions WHERE id = ?');
$stmt->execute([$id]);
$option = $stmt->fetch();

if (!$option) {
    errorResponse('Option not found', 404);
}

if ($option['section'] !== 'thema') {
    errorResponse('Keywords können nur für Thema-Optionen gesetzt werden');
}

$db->beginTransaction();

try {
    // Check if there's already a draft for this option
    $stmt = $db->prepare('SELECT id FROM option_definitions_draft WHERE original_id = ?');
    $stmt->execute([$id]);
    $existingDraft = $stmt->fetch();

    if ($existingDraft) {
        // Update existing draft
        $stmt = $db->prepare('UPDATE option_definitions_draft SET keywords = ? WHERE id = ?');
        $stmt->execute([json_encode($keywords), $existingDraft['id']]);
    } else {
        // Create new draft with updated keywords
        $stmt = $db->prepare('
            INSERT INTO option_definitions_draft
            (original_id, section, label, sort_order, is_active, keywords, action)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $option['section'],
            $option['label'],
            $option['sort_order'],
            $option['is_active'],
            json_encode($keywords),
            'update'
        ]);
    }

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'success' => true,
        'keywords' => $keywords
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Keyword update failed: ' . $e->getMessage(), 500);
}
