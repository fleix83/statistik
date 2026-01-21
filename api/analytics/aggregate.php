<?php
/**
 * Analytics: Aggregate data by section
 * GET /analytics/aggregate.php?section=thema
 * GET /analytics/aggregate.php?section=thema&start_date=2025-01-01&end_date=2025-12-31
 * GET /analytics/aggregate.php?...&filters={"person":["Mann","Frau"]}
 *
 * Filters work as: OR within same section, AND across different sections.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$section = $_GET['section'] ?? '';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$filtersJson = $_GET['filters'] ?? '{}';
$filters = json_decode($filtersJson, true) ?: [];

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'dauer', 'referenz'];

/**
 * Build filter JOINs for SQL query
 */
function buildFilterJoins($filters, &$params) {
    $joins = '';
    $i = 0;
    foreach ($filters as $section => $values) {
        if (empty($values)) continue;
        $alias = "f{$i}";
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $joins .= " JOIN stats_entry_values {$alias} ON se.id = {$alias}.entry_id
                    AND {$alias}.section = ? AND {$alias}.value_text IN ({$placeholders})";
        $params[] = $section;
        foreach ($values as $v) {
            $params[] = $v;
        }
        $i++;
    }
    return $joins;
}

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Valid section parameter required');
}

$db = getDB();

// Build filter JOINs and params
$filterParams = [];
$filterJoins = buildFilterJoins($filters, $filterParams);

// Build query with optional filters
$sql = "
    SELECT
        sev.value_text as label,
        COUNT(DISTINCT sev.entry_id) as count
    FROM stats_entry_values sev
    JOIN stats_entries se ON sev.entry_id = se.id
    {$filterJoins}
    WHERE sev.section = ?
";

$params = array_merge($filterParams, [$section]);

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

// Get total entries in date range (with same filters applied)
$totalFilterParams = [];
$totalFilterJoins = buildFilterJoins($filters, $totalFilterParams);
$totalSql = "SELECT COUNT(DISTINCT se.id) FROM stats_entries se {$totalFilterJoins} WHERE 1=1";
$totalParams = $totalFilterParams;

if ($startDate) {
    $totalSql .= ' AND DATE(se.created_at) >= ?';
    $totalParams[] = $startDate;
}

if ($endDate) {
    $totalSql .= ' AND DATE(se.created_at) <= ?';
    $totalParams[] = $endDate;
}

$stmt = $db->prepare($totalSql);
$stmt->execute($totalParams);
$total = $stmt->fetchColumn();

jsonResponse([
    'section' => $section,
    'items' => $items,
    'total' => intval($total),
    'start_date' => $startDate,
    'end_date' => $endDate
]);
