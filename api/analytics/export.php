<?php
/**
 * Analytics: Export data as CSV
 * GET /analytics/export.php?section=thema
 * GET /analytics/export.php?section=thema&start_date=2025-01-01&end_date=2025-12-31
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

$section = $_GET['section'] ?? '';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    http_response_code(400);
    exit('Valid section parameter required');
}

$db = getDB();

// Build query
$sql = '
    SELECT
        sev.value_text as label,
        COUNT(DISTINCT sev.entry_id) as count
    FROM stats_entry_values sev
    JOIN stats_entries se ON sev.entry_id = se.id
    WHERE sev.section = ?
';

$params = [$section];

if ($startDate) {
    $sql .= ' AND DATE(se.created_at) >= ?';
    $params[] = $startDate;
}

if ($endDate) {
    $sql .= ' AND DATE(se.created_at) <= ?';
    $params[] = $endDate;
}

$sql .= ' GROUP BY sev.value_text ORDER BY count DESC, label';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Generate CSV
$filename = "statistik-{$section}-" . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($output, ['Wert', 'Anzahl'], ';');

// Data rows
foreach ($items as $item) {
    fputcsv($output, [$item['label'], $item['count']], ';');
}

fclose($output);
