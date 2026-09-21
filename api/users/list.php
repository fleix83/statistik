<?php
/**
 * Users: List all users
 * GET /users/list.php
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$db = getDB();

// The public data-entry form needs the name list (id + username). Only expose
// role/created_at to authenticated admins, so anonymous callers cannot identify
// which account is the administrator.
$viewer = tryAuth();
$isAdmin = ($viewer['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $db->query('SELECT id, username, role, created_at FROM users ORDER BY username');
} else {
    $stmt = $db->query('SELECT id, username FROM users ORDER BY username');
}
$users = $stmt->fetchAll();

jsonResponse($users);
