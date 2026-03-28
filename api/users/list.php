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

$stmt = $db->query('SELECT id, username, role, created_at FROM users ORDER BY username');
$users = $stmt->fetchAll();

jsonResponse($users);
