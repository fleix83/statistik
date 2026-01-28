<?php
// ============================================================================
// CSV IMPORT SCRIPT - Historical Data Migration
// ============================================================================
// This script imports statistik_2025.csv into the new multi-select schema
// Run once, then delete for security
// ============================================================================

require_once __DIR__ . '/../api/config/database.php';
$pdo = getDB();

// Configuration
$csv_file = 'statistik_2025.csv';
$default_user_id = 1; // Fallback if user mapping fails

// ----------------------------------------------------------------------------
// STEP 1: Create user mapping from "Bearbeitet von" names to user_id
// ----------------------------------------------------------------------------

function getUserId($pdo, $username) {
    static $user_cache = [];
    
    if (isset($user_cache[$username])) {
        return $user_cache[$username];
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $user_cache[$username] = $result['id'];
        return $result['id'];
    }
    
    // User not found - create new user with default password
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, role) 
        VALUES (:username, :hash, 'user')
    ");
    $stmt->execute([
        'username' => $username,
        'hash' => password_hash('changeme123', PASSWORD_DEFAULT)
    ]);
    
    $user_id = $pdo->lastInsertId();
    $user_cache[$username] = $user_id;
    
    echo "Created new user: $username (ID: $user_id)\n";
    return $user_id;
}


// ----------------------------------------------------------------------------
// STEP 2: Define column mapping from CSV to sections
// ----------------------------------------------------------------------------

$column_mapping = [
    'kontaktart' => [
        'Besuch', 'Telefon', 'Mail', 'Passant/in'
    ],
    'person' => [
        'Mann', 'Frau', 'unter 55', 'über 55', 'über 80',
        'selbst betroffen', 'Angehörige Nachbarn und andere', 'Institution',
        'Migrationshintergrund'
    ],
    'tageszeit' => [
        'Vormittag', 'Nachmittag'
    ],
    'dauer' => [
        'länger als 20 Minuten'
    ],
    'thema' => [
        'Bildung', 'Arbeit', 'Finanzen',
        'Frauen Männer jung und alt', 'Gesundheit', 'Wohnen',
        'Austausch und Freizeit', 'Migration und Integration',
        'Notlagen', 'Allgemeine Hilfeleistungen', 'Recht'
    ],
    'referenz' => [
        'Flyer/Plakat/Presse', 'Internet', 'empfohlen Freunde Bekannte',
        'empfohlen Institution', 'empfohlen Fachperson', 'War schon mal hier',
        'Andere'
    ],
    'zeitfenster' => [
        '11:30 - 12:00', '12:00 - 13:00', '13:00 - 14:00', '14:00 - 15:00',
        '15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'
    ]
];


// ----------------------------------------------------------------------------
// STEP 3: Populate option_definitions from mapping
// ----------------------------------------------------------------------------

echo "Populating option_definitions...\n";

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO option_definitions (section, label, sort_order, is_active)
        VALUES (:section, :label, :sort_order, TRUE)
        ON DUPLICATE KEY UPDATE label = label
    ");
    
    foreach ($column_mapping as $section => $labels) {
        foreach ($labels as $index => $label) {
            $stmt->execute([
                'section' => $section,
                'label' => $label,
                'sort_order' => $index
            ]);
        }
    }
    
    $pdo->commit();
    echo "✓ option_definitions populated\n\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error populating options: " . $e->getMessage() . "\n");
}


// ----------------------------------------------------------------------------
// STEP 4: Import CSV data
// ----------------------------------------------------------------------------

echo "Starting CSV import...\n";

if (!file_exists($csv_file)) {
    die("Error: CSV file not found: $csv_file\n");
}

$handle = fopen($csv_file, 'r');
if (!$handle) {
    die("Error: Cannot open CSV file\n");
}

// Read header row
$header = fgetcsv($handle);
if (!$header) {
    die("Error: CSV header row not found\n");
}

// Create header index for fast lookup
$header_index = array_flip($header);

// Track statistics
$stats = [
    'total_rows' => 0,
    'imported' => 0,
    'errors' => 0,
    'skipped' => 0
];

// Process data rows
while (($row = fgetcsv($handle)) !== false) {
    $stats['total_rows']++;

    // Skip rows with empty or invalid ID
    $entry_id = trim($row[$header_index['ID']] ?? '');
    if (empty($entry_id) || !is_numeric($entry_id)) {
        $stats['skipped']++;
        continue;
    }

    try {
        $pdo->beginTransaction();

        // Parse date from DD.MM.YY format
        $date_str = $row[$header_index['Erfassungsdatum']];
        $date_parts = explode('.', $date_str);
        if (count($date_parts) === 3) {
            // Convert DD.MM.YY to YYYY-MM-DD
            $day = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
            $month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
            $year = '20' . $date_parts[2]; // Assuming 20xx
            $created_at = "$year-$month-$day 12:00:00";
        } else {
            $created_at = date('Y-m-d H:i:s');
        }
        
        // Get user_id
        $username = $row[$header_index['Bearbeitet von']];
        $user_id = getUserId($pdo, $username);
        
        // Get remarks if exists
        $remarks = isset($header_index['Andere Bem']) && !empty($row[$header_index['Andere Bem']]) 
            ? $row[$header_index['Andere Bem']] 
            : null;
        
        // Insert main entry
        $stmt = $pdo->prepare("
            INSERT INTO stats_entries (id, user_id, created_at, reference_remarks)
            VALUES (:id, :user_id, :created_at, :remarks)
        ");
        $stmt->execute([
            'id' => $entry_id,
            'user_id' => $user_id,
            'created_at' => $created_at,
            'remarks' => $remarks
        ]);
        
        // Insert values for each checked field
        $stmt_value = $pdo->prepare("
            INSERT INTO stats_entry_values (entry_id, section, value_text)
            VALUES (:entry_id, :section, :value)
        ");
        
        $values_inserted = 0;
        
        foreach ($column_mapping as $section => $labels) {
            foreach ($labels as $label) {
                if (isset($header_index[$label])) {
                    $csv_value = $row[$header_index[$label]];
                    
                    // Check if field is marked TRUE/WAHR
                    if (strtoupper($csv_value) === 'WAHR' || strtoupper($csv_value) === 'TRUE') {
                        $stmt_value->execute([
                            'entry_id' => $entry_id,
                            'section' => $section,
                            'value' => $label
                        ]);
                        $values_inserted++;
                    }
                }
            }
        }
        
        $pdo->commit();
        $stats['imported']++;
        
        if ($stats['imported'] % 100 === 0) {
            echo "Imported {$stats['imported']} entries...\n";
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $stats['errors']++;
        echo "Error on row {$stats['total_rows']}: " . $e->getMessage() . "\n";
    }
}

fclose($handle);


// ----------------------------------------------------------------------------
// STEP 5: Report results
// ----------------------------------------------------------------------------

echo "\n";
echo "============================================\n";
echo "IMPORT COMPLETE\n";
echo "============================================\n";
echo "Total rows processed: {$stats['total_rows']}\n";
echo "Successfully imported: {$stats['imported']}\n";
echo "Errors: {$stats['errors']}\n";
echo "Skipped: {$stats['skipped']}\n";
echo "============================================\n\n";

// Verify data
echo "Data verification:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stats_entries");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total entries in database: {$result['count']}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stats_entry_values");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total values in database: {$result['count']}\n";
    
    $stmt = $pdo->query("
        SELECT section, COUNT(*) as count 
        FROM stats_entry_values 
        GROUP BY section
    ");
    echo "\nValues per section:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['section']}: {$row['count']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error during verification: " . $e->getMessage() . "\n";
}

echo "\n✓ Import script completed successfully\n";
echo "⚠️  Remember to delete this script for security!\n";
?>
