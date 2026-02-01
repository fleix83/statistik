<?php
/**
 * Entries: Create new stats entry
 * POST /entries/create.php
 * Body: { "user_id": 1, "values": { "kontaktart": ["Besuch"], "person": ["Mann", "unter 55"], ... } }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$data = getJsonBody();
$userId = intval($data['user_id'] ?? 0);
$values = $data['values'] ?? [];

if ($userId <= 0) {
    errorResponse('User ID required');
}

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

$db = getDB();

// Verify user exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    errorResponse('User not found', 404);
}

$db->beginTransaction();

try {
    // Create main entry
    $stmt = $db->prepare('INSERT INTO stats_entries (user_id, created_at) VALUES (?, NOW())');
    $stmt->execute([$userId]);
    $entryId = $db->lastInsertId();

    // Insert values for each section
    $stmt = $db->prepare('INSERT INTO stats_entry_values (entry_id, section, value_text) VALUES (?, ?, ?)');

    foreach ($values as $section => $sectionValues) {
        if (!in_array($section, $validSections)) {
            continue;
        }

        if (!is_array($sectionValues)) {
            $sectionValues = [$sectionValues];
        }

        foreach ($sectionValues as $value) {
            $value = trim($value);
            if (!empty($value)) {
                $stmt->execute([$entryId, $section, $value]);
            }
        }
    }

    $db->commit();

    jsonResponse([
        'id' => $entryId,
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
        'values' => $values
    ], 201);
} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Failed to create entry', 500);
}
