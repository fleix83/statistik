<?php
/**
 * CORS and Response Headers Configuration
 */

// CORS: Allow localhost in development, same-origin in production
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://localhost'];

// In production (same-origin), no CORS headers needed
// In development, allow specific origins
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (!empty($origin)) {
    // For production with different subdomain, add your domain here
    // header('Access-Control-Allow-Origin: https://yourdomain.ch');
}
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
 * Extract the Bearer token from the request, trying multiple header sources
 * for Apache/proxy compatibility. Returns null when no token is present.
 */
function getBearerToken(): ?string {
    $authHeader = '';

    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }

    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        return null;
    }

    return $matches[1];
}

/**
 * Try to authenticate the request. Returns the user array on a valid,
 * non-expired token, or null otherwise. Never sends a response — use this
 * for endpoints that are public but expose more data to authenticated users.
 */
function tryAuth(): ?array {
    $token = getBearerToken();
    if ($token === null) {
        return null;
    }

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

    if (!$result || strtotime($result['expires_at']) < time()) {
        return null;
    }

    return [
        'id' => $result['id'],
        'username' => $result['username'],
        'role' => $result['role']
    ];
}

/**
 * Require authentication
 * Validates token against database (not sessions)
 */
function requireAuth(): array {
    $token = getBearerToken();

    if ($token === null) {
        errorResponse('Unauthorized', 401);
    }

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

/**
 * Neutralize CSV formula injection: spreadsheet apps execute cells that begin
 * with =, +, -, @ (or a leading tab/CR). Prefix those with a single quote so
 * they are treated as text when an admin opens an export in Excel/Calc.
 */
function sanitizeCsvCell($value): string {
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}
