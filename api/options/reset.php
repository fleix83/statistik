<?php
/**
 * Options: Reset draft to default configuration
 * POST /options/reset.php
 *
 * Loads defaults from config/default_options.json into draft table.
 * After reset, user needs to publish to apply changes.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

// Load default options from JSON
$defaultsFile = __DIR__ . '/../config/default_options.json';
if (!file_exists($defaultsFile)) {
    errorResponse('Default configuration file not found', 500);
}

$defaults = json_decode(file_get_contents($defaultsFile), true);
if (!$defaults || !isset($defaults['sections'])) {
    errorResponse('Invalid default configuration file', 500);
}

$db = getDB();
$db->beginTransaction();

try {
    // Clear all existing drafts
    $db->exec('DELETE FROM option_definitions_draft');

    // Get all current published options
    $stmt = $db->query('SELECT id, section, label FROM option_definitions');
    $existing = $stmt->fetchAll();

    // Index existing by section+label
    $existingMap = [];
    foreach ($existing as $opt) {
        $key = $opt['section'] . '::' . $opt['label'];
        $existingMap[$key] = $opt['id'];
    }

    $prepCreate = $db->prepare('
        INSERT INTO option_definitions_draft
        (original_id, section, label, sort_order, is_active, keywords, action)
        VALUES (?, ?, ?, ?, 1, ?, ?)
    ');

    $toCreate = [];
    $toUpdate = [];
    $processedKeys = [];

    // Process defaults from JSON
    foreach ($defaults['sections'] as $section => $options) {
        foreach ($options as $opt) {
            $label = $opt['label'];
            $sortOrder = $opt['sort_order'] ?? 0;
            $keywords = isset($opt['keywords']) ? json_encode($opt['keywords']) : null;
            $key = $section . '::' . $label;
            $processedKeys[$key] = true;

            if (isset($existingMap[$key])) {
                // Option exists - create update draft
                $originalId = $existingMap[$key];
                $prepCreate->execute([
                    $originalId,
                    $section,
                    $label,
                    $sortOrder,
                    $keywords,
                    'update'
                ]);
                $toUpdate[] = $label;
            } else {
                // New option - create create draft
                $prepCreate->execute([
                    null,
                    $section,
                    $label,
                    $sortOrder,
                    $keywords,
                    'create'
                ]);
                $toCreate[] = $label;
            }
        }
    }

    // Mark options that exist but aren't in defaults as delete
    $toDelete = [];
    foreach ($existing as $opt) {
        $key = $opt['section'] . '::' . $opt['label'];
        if (!isset($processedKeys[$key])) {
            $prepCreate->execute([
                $opt['id'],
                $opt['section'],
                $opt['label'],
                0,
                null,
                'delete'
            ]);
            $toDelete[] = $opt['label'];
        }
    }

    // Update publish state
    $db->exec('UPDATE publish_state SET has_pending_changes = TRUE WHERE id = 1');

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Standardkonfiguration wurde geladen. Veröffentlichen Sie die Änderungen, um sie anzuwenden.',
        'stats' => [
            'to_create' => count($toCreate),
            'to_update' => count($toUpdate),
            'to_delete' => count($toDelete)
        ]
    ]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Reset fehlgeschlagen: ' . $e->getMessage(), 500);
}
