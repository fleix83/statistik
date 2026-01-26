<?php
/**
 * Analytics: Export full database as CSV (raw entries)
 * GET /analytics/export-full.php
 *
 * Exports all entries with their values for backup/migration purposes.
 */

require_once __DIR__ . '/../config/database.php';

// Don't use cors.php headers for file download
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$db = getDB();

// Get all entries with their values
$sql = '
    SELECT
        se.id,
        se.created_at,
        u.username as user_name,
        sev.section,
        sev.value_text
    FROM stats_entries se
    JOIN users u ON se.user_id = u.id
    LEFT JOIN stats_entry_values sev ON se.id = sev.entry_id
    ORDER BY se.created_at DESC, se.id, sev.section
';

$stmt = $db->query($sql);
$results = $stmt->fetchAll();

// Build entry map: group values by entry
$entries = [];
$sections = ['kontaktart', 'person', 'dauer', 'thema', 'zeitfenster', 'tageszeit', 'referenz'];

foreach ($results as $row) {
    $entryId = $row['id'];

    if (!isset($entries[$entryId])) {
        $entries[$entryId] = [
            'id' => $row['id'],
            'created_at' => $row['created_at'],
            'user_name' => $row['user_name']
        ];
        // Initialize all sections as empty arrays
        foreach ($sections as $section) {
            $entries[$entryId][$section] = [];
        }
    }

    // Add value to appropriate section
    if ($row['section'] && $row['value_text']) {
        $entries[$entryId][$row['section']][] = $row['value_text'];
    }
}

// Generate CSV
$filename = "statistik-vollstaendig-" . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
$header = ['ID', 'Datum', 'Uhrzeit', 'Benutzer'];
foreach ($sections as $section) {
    $header[] = ucfirst($section);
}
fputcsv($output, $header, ';');

// Data rows
foreach ($entries as $entry) {
    // Format date and time separately
    $datetime = new DateTime($entry['created_at']);
    $date = $datetime->format('d.m.Y');
    $time = $datetime->format('H:i');

    $row = [
        $entry['id'],
        $date,
        $time,
        $entry['user_name']
    ];

    // Add section values (joined with comma for multi-select)
    foreach ($sections as $section) {
        $row[] = implode(', ', $entry[$section]);
    }

    fputcsv($output, $row, ';');
}

fclose($output);
