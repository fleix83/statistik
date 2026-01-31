<?php
/**
 * Database Configuration
 *
 * For production: create database.prod.php with your credentials
 * This file will be auto-loaded if it exists
 */

// Load production config if it exists (not committed to git)
if (file_exists(__DIR__ . '/database.prod.php')) {
    require_once __DIR__ . '/database.prod.php';
} else {
    // Development defaults
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'helpdesk_stats');
    define('DB_USER', 'wegstat');
    define('DB_PASS', 'wegstat2026');
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Get PDO database connection
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }

    return $pdo;
}
