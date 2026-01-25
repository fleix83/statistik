<?php
/**
 * Analytics: Total entries time-series (for default dashboard view)
 * GET /analytics/totals.php?start_date=2024-01-01&end_date=2024-12-31&granularity=month
 * GET /analytics/totals.php?...&filters={"person":["Mann","Frau"],"zeitfenster":["13:00-14:00"]}
 * GET /analytics/totals.php?...&filters={"hierarchy":[{"group":"g1","filters":{...}}]}
 *
 * Returns total entry counts per time bucket, optionally filtered.
 * Flat filters: OR within same section, AND across sections.
 * Hierarchy filters: OR within same group, AND across different groups.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/filters.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$granularity = $_GET['granularity'] ?? 'auto';
$filtersJson = $_GET['filters'] ?? '{}';
$parsedFilters = parseFilters($filtersJson);

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
        $start->modify('monday this week');
        $interval = new DateInterval('P1W');
        $format = 'o-\WW';
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

// Build filter JOINs and params
$filterParams = [];
$filterJoins = buildFilterJoins($parsedFilters, $filterParams);

// Query total counts per period (with optional filters)
$sql = "
    SELECT
        DATE_FORMAT(se.created_at, ?) as period,
        COUNT(DISTINCT se.id) as count
    FROM stats_entries se
    {$filterJoins}
    WHERE DATE(se.created_at) >= ?
      AND DATE(se.created_at) <= ?
    GROUP BY period
    ORDER BY period
";

$queryParams = array_merge([$dateFormat], $filterParams, [$startDate, $endDate]);
$stmt = $db->prepare($sql);
$stmt->execute($queryParams);
$results = $stmt->fetchAll();

// Map results to labels
$dataByPeriod = [];
foreach ($results as $row) {
    $dataByPeriod[$row['period']] = intval($row['count']);
}

$data = [];
foreach ($labels as $label) {
    $data[] = $dataByPeriod[$label] ?? 0;
}

// Get total count (with same filters applied)
$totalFilterParams = [];
$totalFilterJoins = buildFilterJoins($parsedFilters, $totalFilterParams);
$totalSql = "SELECT COUNT(DISTINCT se.id) FROM stats_entries se {$totalFilterJoins} WHERE DATE(se.created_at) >= ? AND DATE(se.created_at) <= ?";
$totalParams = array_merge($totalFilterParams, [$startDate, $endDate]);
$stmt = $db->prepare($totalSql);
$stmt->execute($totalParams);
$total = intval($stmt->fetchColumn());

jsonResponse([
    'granularity' => $granularity,
    'labels' => $labels,
    'data' => $data,
    'total' => $total,
    'start_date' => $startDate,
    'end_date' => $endDate
]);
