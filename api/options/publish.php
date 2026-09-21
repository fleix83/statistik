<?php
/**
 * Options: Publish all draft changes
 * POST /options/publish.php
 *
 * Applies all pending draft changes to the published table in a transaction.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$user = requireAdmin();

$db = getDB();

// Check if there are pending changes
$stmt = $db->query('SELECT COUNT(*) as count FROM option_definitions_draft');
$count = $stmt->fetch()['count'];

if ($count == 0) {
    jsonResponse(['success' => true, 'message' => 'Keine ausstehenden Änderungen']);
}

$db->beginTransaction();

try {
    // Get all drafts
    $stmt = $db->query('SELECT id, original_id, section, label, sort_order, is_active, keywords, action FROM option_definitions_draft');
    $drafts = $stmt->fetchAll();

    $created = 0;
    $updated = 0;
    $deleted = 0;

    foreach ($drafts as $draft) {
        switch ($draft['action']) {
            case 'create':
                // Insert new option
                $stmt = $db->prepare('
                    INSERT INTO option_definitions (section, label, sort_order, is_active, keywords)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $draft['section'],
                    $draft['label'],
                    $draft['sort_order'],
                    $draft['is_active'],
                    $draft['keywords']
                ]);
                $created++;
                break;

            case 'update':
                // Update existing option
                $stmt = $db->prepare('
                    UPDATE option_definitions
                    SET label = ?, sort_order = ?, is_active = ?, keywords = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $draft['label'],
                    $draft['sort_order'],
                    $draft['is_active'],
                    $draft['keywords'],
                    $draft['original_id']
                ]);
                $updated++;
                break;

            case 'delete':
                // Soft delete (set is_active = 0)
                $stmt = $db->prepare('UPDATE option_definitions SET is_active = 0 WHERE id = ?');
                $stmt->execute([$draft['original_id']]);
                $deleted++;
                break;
        }
    }

    // Clear all drafts
    $db->exec('DELETE FROM option_definitions_draft');

    // Update publish state
    $stmt = $db->prepare('
        UPDATE publish_state
        SET has_pending_changes = FALSE, last_published_at = NOW(), last_published_by = ?
        WHERE id = 1
    ');
    $stmt->execute([$user['id'] ?? null]);

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Änderungen wurden veröffentlicht',
        'stats' => [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted
        ]
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Veröffentlichung fehlgeschlagen: ' . $e->getMessage(), 500);
}
