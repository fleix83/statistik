<?php
/**
 * Authentication: Login
 * POST /auth/login.php
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$data = getJsonBody();
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    errorResponse('Username and password required');
}

$db = getDB();

$stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    errorResponse('Invalid credentials', 401);
}

// Generate token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Clean up old tokens for this user
$stmt = $db->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
$stmt->execute([$user['id']]);

// Store token in database
$stmt = $db->prepare('INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$user['id'], $token, $expiresAt]);

jsonResponse([
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ]
]);
