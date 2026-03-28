<?php
/**
 * Entries: Delete stats entry
 * DELETE /entries/delete.php?id=123
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    errorResponse('Invalid ID');
}

$db = getDB();

// Check if entry exists
$stmt = $db->prepare('SELECT id FROM stats_entries WHERE id = ?');
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    errorResponse('Entry not found', 404);
}

// Delete entry (cascade will delete values)
$stmt = $db->prepare('DELETE FROM stats_entries WHERE id = ?');
$stmt->execute([$id]);

jsonResponse(['success' => true, 'message' => 'Entry deleted']);
