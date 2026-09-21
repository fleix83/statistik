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

// Optional explicit entry date (local wall-clock string). Falls back to now.
$createdAt = $data['created_at'] ?? null;
$createdAtSql = null;
if ($createdAt !== null && $createdAt !== '') {
    $ts = strtotime($createdAt);
    if ($ts === false) {
        errorResponse('Invalid created_at');
    }
    $createdAtSql = date('Y-m-d H:i:s', $ts);
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
    if ($createdAtSql !== null) {
        $stmt = $db->prepare('INSERT INTO stats_entries (user_id, created_at) VALUES (?, ?)');
        $stmt->execute([$userId, $createdAtSql]);
    } else {
        $stmt = $db->prepare('INSERT INTO stats_entries (user_id, created_at) VALUES (?, NOW())');
        $stmt->execute([$userId]);
    }
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
    ], 201);
} catch (Throwable $e) {
    $db->rollBack();
    error_log('create.php failed: ' . $e->getMessage());
    errorResponse('Failed to create entry', 500);
}
