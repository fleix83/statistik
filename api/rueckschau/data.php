<?php
/**
 * Rueckschau Data: Daily counts for configured fields
 * GET /rueckschau/data.php?days=7
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$days = max(1, min(intval($_GET['days'] ?? 7), 90));

$db = getDB();

// Load configured fields
$stmt = $db->query('SELECT section, value_text FROM rueckschau_fields ORDER BY sort_order ASC');
$fields = $stmt->fetchAll();

if (empty($fields)) {
    jsonResponse([
        'fields' => [],
        'dates' => [],
        'counts' => []
    ]);
}

// Build date range
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

// Generate all dates in range
$dates = [];
$current = new DateTime($startDate);
$end = new DateTime($endDate);
while ($current <= $end) {
    $dates[] = $current->format('Y-m-d');
    $current->modify('+1 day');
}

// Build query: count distinct entries per day per (section, value_text)
$conditions = [];
$params = [$startDate, $endDate];

foreach ($fields as $field) {
    $conditions[] = '(sev.section = ? AND sev.value_text = ?)';
    $params[] = $field['section'];
    $params[] = $field['value_text'];
}

$whereFields = implode(' OR ', $conditions);

$sql = "
    SELECT
        DATE(se.created_at) AS entry_date,
        sev.section,
        sev.value_text,
        COUNT(DISTINCT sev.entry_id) AS cnt
    FROM stats_entry_values sev
    JOIN stats_entries se ON sev.entry_id = se.id
    WHERE DATE(se.created_at) BETWEEN ? AND ?
    AND ({$whereFields})
    GROUP BY entry_date, sev.section, sev.value_text
    ORDER BY entry_date ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Index counts by "section|value_text" -> date -> count
$countIndex = [];
foreach ($rows as $row) {
    $key = $row['section'] . '|' . $row['value_text'];
    $countIndex[$key][$row['entry_date']] = intval($row['cnt']);
}

// Build output: for each field, an array of counts matching the dates array
$counts = [];
foreach ($fields as $field) {
    $key = $field['section'] . '|' . $field['value_text'];
    $data = [];
    foreach ($dates as $date) {
        $data[] = $countIndex[$key][$date] ?? 0;
    }
    $counts[] = [
        'section' => $field['section'],
        'value_text' => $field['value_text'],
        'data' => $data
    ];
}

jsonResponse([
    'fields' => $fields,
    'dates' => $dates,
    'counts' => $counts
]);
