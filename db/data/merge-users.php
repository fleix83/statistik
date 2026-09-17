<?php
// ============================================================================
// USER MERGE SCRIPT - Merge duplicate users
// ============================================================================
// Run once, then delete for security
// ============================================================================

require_once __DIR__ . '/../../api/config/database.php';
$pdo = getDB();

// Define merges: 'wrong_username' => 'correct_username'
$merges = [
    'Angelikia' => 'Angelika',
    'Barbara L' => 'Barbara L.',
    'Felix W.' => 'Felix',
    'Pia Frey' => 'Pia',
    'Renata O.' => 'Renata',
    'soilvie' => 'Silvie',
];

echo "User Merge Script\n";
echo "==================\n\n";

foreach ($merges as $wrongName => $correctName) {
    echo "Processing: '$wrongName' -> '$correctName'\n";

    // Find both users
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");

    $stmt->execute([$wrongName]);
    $wrongUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->execute([$correctName]);
    $correctUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wrongUser) {
        echo "  ⚠️  User '$wrongName' not found - skipping\n\n";
        continue;
    }

    if (!$correctUser) {
        echo "  ⚠️  User '$correctName' not found - skipping\n\n";
        continue;
    }

    $wrongId = $wrongUser['id'];
    $correctId = $correctUser['id'];

    echo "  Found: '$wrongName' (ID: $wrongId) -> '$correctName' (ID: $correctId)\n";

    try {
        $pdo->beginTransaction();

        // Count entries to reassign
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stats_entries WHERE user_id = ?");
        $stmt->execute([$wrongId]);
        $entryCount = $stmt->fetchColumn();

        echo "  Entries to reassign: $entryCount\n";

        // Reassign entries
        $stmt = $pdo->prepare("UPDATE stats_entries SET user_id = ? WHERE user_id = ?");
        $stmt->execute([$correctId, $wrongId]);

        // Delete the wrong user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$wrongId]);

        $pdo->commit();

        echo "  ✓ Merged successfully - reassigned $entryCount entries, deleted user '$wrongName'\n\n";

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "==================\n";
echo "Merge complete!\n";
echo "⚠️  Remember to delete this script for security!\n";
?>
