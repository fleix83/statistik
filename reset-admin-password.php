<?php
/**
 * Reset Admin Password
 *
 * Run this script ONCE to reset the admin password to: admin123
 * DELETE THIS FILE immediately after running!
 */

require_once __DIR__ . '/api/config/database.php';

$db = getDB();

// New password: admin123
$newPassword = 'admin123';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
    $stmt->execute([$passwordHash, 'admin']);

    if ($stmt->rowCount() > 0) {
        echo "✅ Admin password reset successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n\n";
        echo "⚠️ DELETE THIS FILE NOW!\n";
    } else {
        echo "❌ No admin user found. Creating one...\n";

        $stmt = $db->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute(['admin', $passwordHash, 'admin']);

        echo "✅ Admin user created!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n\n";
        echo "⚠️ DELETE THIS FILE NOW!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
