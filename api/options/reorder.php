<?php
/**
 * Options: Reorder options within a section (writes to draft table)
 * POST /options/reorder.php
 * Body: { "section": "thema", "items": [{"id": 1, "sort_order": 0}, {"id": 2, "sort_order": 1}] }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$data = getJsonBody();
$section = $data['section'] ?? '';
$items = $data['items'] ?? [];

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Invalid section');
}

if (empty($items) || !is_array($items)) {
    errorResponse('Items array required');
}

$db = getDB();
$db->beginTransaction();

try {
    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        $sortOrder = intval($item['sort_order'] ?? 0);

        if (!$id) continue;

        // Check if this is a new draft option (id starts with 'new_')
        if (is_string($id) && strpos($id, 'new_') === 0) {
            $draftId = intval(substr($id, 4));

            // Update sort_order in draft directly
            $stmt = $db->prepare('UPDATE option_definitions_draft SET sort_order = ? WHERE id = ?');
            $stmt->execute([$sortOrder, $draftId]);
            continue;
        }

        $id = intval($id);
        if ($id <= 0) continue;

        // Check if there's already a draft for this option
        $stmt = $db->prepare('SELECT id FROM option_definitions_draft WHERE original_id = ?');
        $stmt->execute([$id]);
        $existingDraft = $stmt->fetch();

        if ($existingDraft) {
            // Update existing draft
            $stmt = $db->prepare('UPDATE option_definitions_draft SET sort_order = ? WHERE id = ?');
            $stmt->execute([$sortOrder, $existingDraft['id']]);
        } else {
            // Get current option data
            $stmt = $db->prepare('SELECT id, section, label, sort_order, is_active, keywords FROM option_definitions WHERE id = ? AND section = ?');
            $stmt->execute([$id, $section]);
            $option = $stmt->fetch();

            if ($option && $option['sort_order'] != $sortOrder) {
                // Create new draft with updated sort_order
                $stmt = $db->prepare('
                    INSERT INTO option_definitions_draft
                    (original_id, section, label, sort_order, is_active, keywords, action)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $id,
                    $option['section'],
                    $option['label'],
                    $sortOrder,
                    $option['is_active'],
                    $option['keywords'],
                    'update'
                ]);
            }
        }
    }

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();
    jsonResponse(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Reorder failed: ' . $e->getMessage(), 500);
}
