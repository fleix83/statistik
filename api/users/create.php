<?php
/**
 * Users: Create new user
 * POST /users/create.php
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$data = getJsonBody();
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';

if (empty($username)) {
    errorResponse('Username required');
}

if (empty($password) || strlen($password) < 4) {
    errorResponse('Password must be at least 4 characters');
}

if (!in_array($role, ['user', 'admin'])) {
    $role = 'user';
}

$db = getDB();

// Check if username exists
$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    errorResponse('Username already exists');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
$stmt->execute([$username, $passwordHash, $role]);

$id = $db->lastInsertId();

jsonResponse([
    'id' => $id,
    'username' => $username,
    'role' => $role
], 201);
