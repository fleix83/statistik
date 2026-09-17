<?php
/**
 * Users: Update existing user
 * PUT /users/update.php?id=123
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    errorResponse('Invalid ID');
}

$data = getJsonBody();
$db = getDB();

// Check if user exists
$stmt = $db->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    errorResponse('User not found', 404);
}

// Build update query
$updates = [];
$params = [];

if (isset($data['username'])) {
    $username = trim($data['username']);
    if (!empty($username)) {
        // Check if username is taken by another user
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            errorResponse('Username already exists');
        }
        $updates[] = 'username = ?';
        $params[] = $username;
    }
}

if (!empty($data['password'])) {
    if (strlen($data['password']) < 4) {
        errorResponse('Password must be at least 4 characters');
    }
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
}

if (isset($data['role']) && in_array($data['role'], ['user', 'admin'])) {
    $updates[] = 'role = ?';
    $params[] = $data['role'];
}

if (empty($updates)) {
    errorResponse('No fields to update');
}

$params[] = $id;

$stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

// Return updated user
$stmt = $db->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

jsonResponse($user);
