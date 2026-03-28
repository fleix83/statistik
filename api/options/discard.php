<?php
/**
 * Options: Discard all draft changes
 * POST /options/discard.php
 *
 * Removes all pending draft changes without publishing.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$db = getDB();

// Check if there are pending changes
$stmt = $db->query('SELECT COUNT(*) as count FROM option_definitions_draft');
$count = $stmt->fetch()['count'];

if ($count == 0) {
    jsonResponse(['success' => true, 'message' => 'Keine ausstehenden Änderungen']);
}

$db->beginTransaction();

try {
    // Clear all drafts
    $db->exec('DELETE FROM option_definitions_draft');

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = FALSE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Alle ausstehenden Änderungen wurden verworfen',
        'discarded_count' => $count
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Verwerfen fehlgeschlagen: ' . $e->getMessage(), 500);
}
