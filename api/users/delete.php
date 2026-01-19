<?php
/**
 * Users: Delete user
 * DELETE /users/delete.php?id=123
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

// Check if user exists
$stmt = $db->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    errorResponse('User not found', 404);
}

// Check if user has entries (prevent deletion if they do)
$stmt = $db->prepare('SELECT COUNT(*) FROM stats_entries WHERE user_id = ?');
$stmt->execute([$id]);
$entryCount = $stmt->fetchColumn();

if ($entryCount > 0) {
    errorResponse("Cannot delete user with {$entryCount} entries. Reassign entries first.");
}

// Delete user
$stmt = $db->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);

jsonResponse(['success' => true, 'message' => 'User deleted']);
