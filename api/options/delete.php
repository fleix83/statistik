<?php
/**
 * Options: Delete option (writes to draft table)
 * DELETE /options/delete.php?id=123
 *
 * For new drafts: removes the draft completely
 * For published options: creates a delete draft
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$id = $_GET['id'] ?? null;

$db = getDB();

// Check if this is a new draft option (id starts with 'new_')
if (is_string($id) && strpos($id, 'new_') === 0) {
    $draftId = intval(substr($id, 4));

    // Delete the draft directly
    $stmt = $db->prepare('SELECT id FROM option_definitions_draft WHERE id = ? AND action = ?');
    $stmt->execute([$draftId, 'create']);
    $draft = $stmt->fetch();

    if (!$draft) {
        errorResponse('Draft option not found', 404);
    }

    $stmt = $db->prepare('DELETE FROM option_definitions_draft WHERE id = ?');
    $stmt->execute([$draftId]);

    // Check if there are still pending changes
    $stmt = $db->query('SELECT COUNT(*) as count FROM option_definitions_draft');
    $count = $stmt->fetch()['count'];
    if ($count == 0) {
        $db->exec('UPDATE publish_state SET has_pending_changes = FALSE WHERE id = 1');
    }

    jsonResponse(['success' => true, 'message' => 'Entwurf wurde gelöscht']);
}

// Regular published option
$id = intval($id);
if ($id <= 0) {
    errorResponse('Invalid ID');
}

// Check if option exists
$stmt = $db->prepare('SELECT id, section, label, sort_order, is_active FROM option_definitions WHERE id = ?');
$stmt->execute([$id]);
$option = $stmt->fetch();

if (!$option) {
    errorResponse('Option not found', 404);
}

// Check if option is still active - only allow delete of deactivated options
if ($option['is_active']) {
    errorResponse('Option muss zuerst deaktiviert werden');
}

$db->beginTransaction();

try {
    // Check if there's already a draft for this option
    $stmt = $db->prepare('SELECT id, action FROM option_definitions_draft WHERE original_id = ?');
    $stmt->execute([$id]);
    $existingDraft = $stmt->fetch();

    if ($existingDraft) {
        // Update existing draft to delete action
        $stmt = $db->prepare('UPDATE option_definitions_draft SET action = ? WHERE id = ?');
        $stmt->execute(['delete', $existingDraft['id']]);
    } else {
        // Create new delete draft
        $stmt = $db->prepare('
            INSERT INTO option_definitions_draft
            (original_id, section, label, sort_order, is_active, keywords, action)
            VALUES (?, ?, ?, ?, ?, NULL, ?)
        ');
        $stmt->execute([
            $id,
            $option['section'],
            $option['label'],
            $option['sort_order'],
            0,
            'delete'
        ]);
    }

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Option zum Löschen markiert. Veröffentlichen Sie die Änderungen, um sie anzuwenden.',
        'draft_action' => 'delete'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Löschen fehlgeschlagen: ' . $e->getMessage(), 500);
}
