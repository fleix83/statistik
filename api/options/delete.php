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
$stmt = $db->prepare('SELECT id, section, label FROM option_definitions WHERE id = ?');
$stmt->execute([$id]);
$option = $stmt->fetch();

if (!$option) {
    errorResponse('Option not found', 404);
}

$db->beginTransaction();

try {
    // Remove any existing draft entries for this option
    $stmt = $db->prepare('DELETE FROM option_definitions_draft WHERE original_id = ?');
    $stmt->execute([$id]);

    // Hard delete from the main table
    $stmt = $db->prepare('DELETE FROM option_definitions WHERE id = ?');
    $stmt->execute([$id]);

    // Check if there are still pending changes
    $stmt = $db->query('SELECT COUNT(*) as count FROM option_definitions_draft');
    $count = $stmt->fetch()['count'];
    if ($count == 0) {
        $db->exec('UPDATE publish_state SET has_pending_changes = FALSE WHERE id = 1');
    }

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Option wurde gelöscht'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Löschen fehlgeschlagen: ' . $e->getMessage(), 500);
}
