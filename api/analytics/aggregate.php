<?php
/**
 * Analytics: Aggregate data by section
 * GET /analytics/aggregate.php?section=thema
 * GET /analytics/aggregate.php?section=thema&start_date=2025-01-01&end_date=2025-12-31
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$section = $_GET['section'] ?? '';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Valid section parameter required');
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

// Get total entries in date range
$totalSql = 'SELECT COUNT(*) FROM stats_entries se WHERE 1=1';
$totalParams = [];

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
