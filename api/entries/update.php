<?php
/**
 * Entries: Update existing stats entry
 * PUT /entries/update.php?id=123
 * Body: { "user_id": 1, "created_at": "2024-01-15T10:00:00", "values": { "kontaktart": ["Besuch"], ... } }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

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

// Validate optional entry date up front: strtotime() returns false on garbage,
// which date() would silently turn into 1970-01-01 and hide the entry from all
// date-filtered views.
$createdAtSql = null;
if ($createdAt !== null && $createdAt !== '') {
    $ts = strtotime($createdAt);
    if ($ts === false) {
        errorResponse('Invalid created_at');
    }
    $createdAtSql = date('Y-m-d H:i:s', $ts);
}

$db->beginTransaction();

try {
    // Update main entry
    if ($createdAtSql !== null) {
        $stmt = $db->prepare('UPDATE stats_entries SET user_id = ?, created_at = ? WHERE id = ?');
        $stmt->execute([$userId, $createdAtSql, $entryId]);
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
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $stmt->execute([$entryId, $section, $value]);
            }
        }
    }

    $db->commit();

    jsonResponse([
        'id' => $entryId,
        'user_id' => $userId,
        'created_at' => $createdAtSql ?? date('Y-m-d H:i:s'),
        'values' => $values
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log('update.php failed: ' . $e->getMessage());
    errorResponse('Failed to update entry', 500);
}
