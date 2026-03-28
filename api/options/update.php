<?php
/**
 * Options: Update existing option (writes to draft table)
 * PUT /options/update.php?id=123
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$id = $_GET['id'] ?? null;
$data = getJsonBody();

$db = getDB();

// Check if this is a new draft option (id starts with 'new_')
if (is_string($id) && strpos($id, 'new_') === 0) {
    $draftId = intval(substr($id, 4));

    // Update the draft directly
    $stmt = $db->prepare('SELECT id, section, label, sort_order, is_active, keywords FROM option_definitions_draft WHERE id = ?');
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch();

    if (!$draft) {
        errorResponse('Draft option not found', 404);
    }

    // Build update query for draft
    $updates = [];
    $params = [];

    if (isset($data['label'])) {
        $updates[] = 'label = ?';
        $params[] = trim($data['label']);
    }

    if (isset($data['sort_order'])) {
        $updates[] = 'sort_order = ?';
        $params[] = intval($data['sort_order']);
    }

    if (isset($data['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = $data['is_active'] ? 1 : 0;
    }

    if (isset($data['keywords'])) {
        $keywords = $data['keywords'];
        if (!is_array($keywords)) $keywords = [];
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords, fn($k) => strlen($k) > 0);
        $updates[] = 'keywords = ?';
        $params[] = !empty($keywords) ? json_encode(array_values($keywords)) : null;
    }

    if (empty($updates)) {
        errorResponse('No fields to update');
    }

    $params[] = $draftId;
    $stmt = $db->prepare('UPDATE option_definitions_draft SET ' . implode(', ', $updates) . ' WHERE id = ?');
    $stmt->execute($params);

    // Return updated draft
    $stmt = $db->prepare('SELECT id, section, label, sort_order, is_active, keywords FROM option_definitions_draft WHERE id = ?');
    $stmt->execute([$draftId]);
    $updated = $stmt->fetch();

    jsonResponse([
        'id' => 'new_' . $updated['id'],
        'section' => $updated['section'],
        'label' => $updated['label'],
        'sort_order' => $updated['sort_order'],
        'is_active' => (bool)$updated['is_active'],
        'keywords' => $updated['keywords'] ? json_decode($updated['keywords'], true) : [],
        'draft_action' => 'create',
        'draft_id' => $updated['id']
    ]);
}

// Regular published option
$id = intval($id);
if ($id <= 0) {
    errorResponse('Invalid ID');
}

// Check if option exists
$stmt = $db->prepare('SELECT id, section, label, sort_order, is_active, keywords FROM option_definitions WHERE id = ?');
$stmt->execute([$id]);
$option = $stmt->fetch();

if (!$option) {
    errorResponse('Option not found', 404);
}

// Prepare updated values (merge with existing)
$newLabel = isset($data['label']) ? trim($data['label']) : $option['label'];
$newSortOrder = isset($data['sort_order']) ? intval($data['sort_order']) : $option['sort_order'];
$newIsActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : $option['is_active'];

$newKeywords = $option['keywords'];
if (isset($data['keywords'])) {
    $keywords = $data['keywords'];
    if (!is_array($keywords)) $keywords = [];
    $keywords = array_map('trim', $keywords);
    $keywords = array_filter($keywords, fn($k) => strlen($k) > 0);
    $newKeywords = !empty($keywords) ? json_encode(array_values($keywords)) : null;
}

$db->beginTransaction();

try {
    // Check if there's already a draft for this option
    $stmt = $db->prepare('SELECT id FROM option_definitions_draft WHERE original_id = ?');
    $stmt->execute([$id]);
    $existingDraft = $stmt->fetch();

    if ($existingDraft) {
        // Update existing draft
        $stmt = $db->prepare('
            UPDATE option_definitions_draft
            SET label = ?, sort_order = ?, is_active = ?, keywords = ?
            WHERE id = ?
        ');
        $stmt->execute([$newLabel, $newSortOrder, $newIsActive, $newKeywords, $existingDraft['id']]);
        $draftId = $existingDraft['id'];
    } else {
        // Create new draft
        $stmt = $db->prepare('
            INSERT INTO option_definitions_draft
            (original_id, section, label, sort_order, is_active, keywords, action)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $option['section'],
            $newLabel,
            $newSortOrder,
            $newIsActive,
            $newKeywords,
            'update'
        ]);
        $draftId = $db->lastInsertId();
    }

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'id' => $id,
        'section' => $option['section'],
        'label' => $newLabel,
        'sort_order' => $newSortOrder,
        'is_active' => (bool)$newIsActive,
        'keywords' => $newKeywords ? json_decode($newKeywords, true) : [],
        'draft_action' => 'update',
        'draft_id' => $draftId
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Update fehlgeschlagen: ' . $e->getMessage(), 500);
}
