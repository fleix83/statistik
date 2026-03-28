<?php
/**
 * Analytics: Time-series data for line charts
 * GET /analytics/timeseries.php?section=thema&values=Bildung,Arbeit&start_date=2024-01-01&end_date=2024-12-31&granularity=month
 * GET /analytics/timeseries.php?...&filters={"person":["Mann","Frau"]}
 * GET /analytics/timeseries.php?...&filters={"hierarchy":[{"group":"g1","filters":{...}}]}
 *
 * Returns time-bucketed counts for each selected value, optionally filtered.
 * Flat filters: OR within same section, AND across sections.
 * Hierarchy filters: OR within same group, AND across different groups.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/filters.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$section = $_GET['section'] ?? '';
$values = isset($_GET['values']) ? explode(',', $_GET['values']) : [];
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$granularity = $_GET['granularity'] ?? 'auto';
$filtersJson = $_GET['filters'] ?? '{}';
$parsedFilters = parseFilters($filtersJson);

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Valid section parameter required');
}

if (empty($values)) {
    errorResponse('At least one value is required');
}

if (!$startDate || !$endDate) {
    errorResponse('start_date and end_date are required');
}

// Auto-detect granularity based on date range
if ($granularity === 'auto') {
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
    if ($daysDiff <= 90) {
        $granularity = 'day';
    } elseif ($daysDiff <= 365) {
        $granularity = 'week';
    } else {
        $granularity = 'month';
    }
}

// MySQL date format based on granularity
$dateFormats = [
    'day' => '%Y-%m-%d',
    'week' => '%x-W%v',  // ISO week
    'month' => '%Y-%m'
];
$dateFormat = $dateFormats[$granularity] ?? $dateFormats['month'];

$db = getDB();

// Generate all date buckets in range
$labels = [];
$start = new DateTime($startDate);
$end = new DateTime($endDate);

switch ($granularity) {
    case 'day':
        $interval = new DateInterval('P1D');
        $format = 'Y-m-d';
        break;
    case 'week':
        // Align to start of week (Monday)
        $start->modify('monday this week');
        $interval = new DateInterval('P1W');
        $format = 'o-\WW';  // ISO week format
        break;
    case 'month':
    default:
        $start->modify('first day of this month');
        $interval = new DateInterval('P1M');
        $format = 'Y-m';
        break;
}

$period = new DatePeriod($start, $interval, $end->modify('+1 day'));
foreach ($period as $date) {
    $labels[] = $date->format($format);
}

// Query data for each value
$datasets = [];
foreach ($values as $value) {
    $value = trim($value);

    // Build filter JOINs (start index at 1 since sev is effectively filter 0)
    $filterParams = [];
    $filterJoins = buildFilterJoins($parsedFilters, $filterParams, 1);

    $sql = "
        SELECT
            DATE_FORMAT(se.created_at, ?) as period,
            COUNT(DISTINCT sev.entry_id) as count
        FROM stats_entry_values sev
        JOIN stats_entries se ON sev.entry_id = se.id
        {$filterJoins}
        WHERE sev.section = ?
          AND sev.value_text = ?
          AND DATE(se.created_at) >= ?
          AND DATE(se.created_at) <= ?
        GROUP BY period
        ORDER BY period
    ";

    $queryParams = array_merge([$dateFormat], $filterParams, [$section, $value, $startDate, $endDate]);
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $results = $stmt->fetchAll();

    // Map results to labels (fill in zeros for missing periods)
    $dataByPeriod = [];
    foreach ($results as $row) {
        $dataByPeriod[$row['period']] = intval($row['count']);
    }

    $data = [];
    foreach ($labels as $label) {
        $data[] = $dataByPeriod[$label] ?? 0;
    }

    $datasets[] = [
        'label' => $value,
        'data' => $data
    ];
}

jsonResponse([
    'granularity' => $granularity,
    'labels' => $labels,
    'datasets' => $datasets,
    'start_date' => $startDate,
    'end_date' => $endDate
]);
