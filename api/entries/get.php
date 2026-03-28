<?php
/**
 * Entries: Get single entry by ID
 * GET /entries/get.php?id=123
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$id = $_GET['id'] ?? null;

if (!$id) {
    errorResponse('Entry ID is required', 400);
}

$db = getDB();

// Get entry
$stmt = $db->prepare('
    SELECT
        se.id,
        se.user_id,
        u.username,
        se.created_at,
        se.reference_remarks,
        se.notes
    FROM stats_entries se
    JOIN users u ON se.user_id = u.id
    WHERE se.id = ?
');
$stmt->execute([$id]);
$entry = $stmt->fetch();

if (!$entry) {
    errorResponse('Entry not found', 404);
}

// Get values for this entry
$stmt = $db->prepare('
    SELECT section, value_text
    FROM stats_entry_values
    WHERE entry_id = ?
    ORDER BY section, value_text
');
$stmt->execute([$id]);
$values = $stmt->fetchAll();

// Group values by section
$valuesBySection = [];
foreach ($values as $v) {
    $valuesBySection[$v['section']][] = $v['value_text'];
}

$entry['values'] = $valuesBySection;

jsonResponse($entry);
