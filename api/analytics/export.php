<?php
/**
 * Analytics: Export data as CSV
 * GET /analytics/export.php?section=thema
 * GET /analytics/export.php?section=thema&start_date=2025-01-01&end_date=2025-12-31
 */

require_once __DIR__ . '/../config/database.php';

// CORS for file download
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://localhost'];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
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
$valuesParam = $_GET['values'] ?? null;
$periodsParam = $_GET['periods'] ?? null;

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    http_response_code(400);
    exit('Valid section parameter required');
}

// Parse selected values
$selectedValues = [];
if ($valuesParam) {
    $selectedValues = array_map('trim', explode(',', $valuesParam));
}

// Parse periods if provided
$periods = [];
if ($periodsParam) {
    $periods = json_decode($periodsParam, true) ?: [];
}

$db = getDB();

// If no values selected, get all values for the section
if (empty($selectedValues)) {
    $sql = 'SELECT DISTINCT value_text FROM stats_entry_values WHERE section = ? ORDER BY value_text';
    $stmt = $db->prepare($sql);
    $stmt->execute([$section]);
    $selectedValues = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Generate all dates in the range
$dates = [];
if ($startDate && $endDate) {
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
}

// Query counts per day per value
$sql = '
    SELECT
        DATE(se.created_at) as date,
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

if (!empty($selectedValues)) {
    $placeholders = implode(',', array_fill(0, count($selectedValues), '?'));
    $sql .= " AND sev.value_text IN ($placeholders)";
    $params = array_merge($params, $selectedValues);
}

$sql .= ' GROUP BY DATE(se.created_at), sev.value_text';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Build data matrix: date -> value -> count
$dataMatrix = [];
foreach ($results as $row) {
    $dataMatrix[$row['date']][$row['label']] = $row['count'];
}

// Generate CSV
$filename = "statistik-{$section}-" . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row: Zeitraum + each selected value
$header = array_merge(['Zeitraum'], $selectedValues);
fputcsv($output, $header, ';');

// Data rows: one per day
foreach ($dates as $date) {
    // Format date as DD.MM.YYYY
    $formattedDate = date('d.m.Y', strtotime($date));
    $row = [$formattedDate];

    foreach ($selectedValues as $value) {
        $count = $dataMatrix[$date][$value] ?? '';
        $row[] = $count;
    }

    fputcsv($output, $row, ';');
}

fclose($output);
