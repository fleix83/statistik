<?php
/**
 * CORS and Response Headers Configuration
 */

// Start session early for all requests
session_start();

// Allow from localhost during development
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Send JSON response
 */
function jsonResponse($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function errorResponse(string $message, int $statusCode = 400): void {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): array {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return $data ?? [];
}

/**
 * Require authentication
 * Validates token against database (not sessions)
 */
function requireAuth(): array {
    // Try multiple methods to get Authorization header (Apache compatibility)
    $authHeader = '';

    // Method 1: getallheaders()
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }

    // Method 2: $_SERVER (for proxied requests)
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    // Method 3: Apache-specific
    if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        errorResponse('Unauthorized', 401);
    }

    $token = $matches[1];

    // Validate token against database
    require_once __DIR__ . '/database.php';
    $db = getDB();

    $stmt = $db->prepare('
        SELECT u.id, u.username, u.role, t.expires_at
        FROM auth_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ?
    ');
    $stmt->execute([$token]);
    $result = $stmt->fetch();

    if (!$result) {
        errorResponse('Invalid token', 401);
    }

    // Check if token is expired
    if (strtotime($result['expires_at']) < time()) {
        // Clean up expired token
        $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token = ?');
        $stmt->execute([$token]);
        errorResponse('Token expired', 401);
    }

    return [
        'id' => $result['id'],
        'username' => $result['username'],
        'role' => $result['role']
    ];
}

/**
 * Require admin role
 */
function requireAdmin(): array {
    $user = requireAuth();

    if (($user['role'] ?? '') !== 'admin') {
        errorResponse('Admin access required', 403);
    }

    return $user;
}
