<?php
/**
 * Entries: Update existing stats entry
 * PUT /entries/update.php?id=123
 * Body: { "user_id": 1, "created_at": "2024-01-15T10:00:00", "values": { "kontaktart": ["Besuch"], ... } }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    errorResponse('Method not allowed', 405);
}

$entryId = intval($_GET['id'] ?? 0);
if ($entryId <= 0) {
    errorResponse('Entry ID required');
}

$data = getJsonBody();
$userId = intval($data['user_id'] ?? 0);
$createdAt = $data['created_at'] ?? null;
$values = $data['values'] ?? [];

if ($userId <= 0) {
    errorResponse('User ID required');
}

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

$db = getDB();

// Verify entry exists
$stmt = $db->prepare('SELECT id FROM stats_entries WHERE id = ?');
$stmt->execute([$entryId]);
if (!$stmt->fetch()) {
    errorResponse('Entry not found', 404);
}

// Verify user exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    errorResponse('User not found', 404);
}

$db->beginTransaction();

try {
    // Update main entry
    if ($createdAt) {
        $stmt = $db->prepare('UPDATE stats_entries SET user_id = ?, created_at = ? WHERE id = ?');
        $stmt->execute([$userId, date('Y-m-d H:i:s', strtotime($createdAt)), $entryId]);
    } else {
        $stmt = $db->prepare('UPDATE stats_entries SET user_id = ? WHERE id = ?');
        $stmt->execute([$userId, $entryId]);
    }

    // Delete existing values
    $stmt = $db->prepare('DELETE FROM stats_entry_values WHERE entry_id = ?');
    $stmt->execute([$entryId]);

    // Insert new values for each section
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
        'created_at' => $createdAt ?? date('Y-m-d H:i:s'),
        'values' => $values
    ]);
} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Failed to update entry', 500);
}
