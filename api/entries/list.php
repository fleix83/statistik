<?php
/**
 * Entries: List stats entries
 * GET /entries/list.php
 * GET /entries/list.php?start_date=2025-01-01&end_date=2025-12-31&limit=50&offset=0
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$limit = min(intval($_GET['limit'] ?? 50), 100);
$offset = max(intval($_GET['offset'] ?? 0), 0);

$db = getDB();

// Build query
$sql = '
    SELECT
        se.id,
        se.user_id,
        u.username,
        se.created_at,
        se.reference_remarks,
        se.notes
    FROM stats_entries se
    JOIN users u ON se.user_id = u.id
';

$params = [];
$conditions = [];

if ($startDate) {
    $conditions[] = 'DATE(se.created_at) >= ?';
    $params[] = $startDate;
}

if ($endDate) {
    $conditions[] = 'DATE(se.created_at) <= ?';
    $params[] = $endDate;
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY se.created_at DESC LIMIT ? OFFSET ?';
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Get values for each entry
$entryIds = array_column($entries, 'id');

if (!empty($entryIds)) {
    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
    $stmt = $db->prepare("
        SELECT entry_id, section, value_text
        FROM stats_entry_values
        WHERE entry_id IN ($placeholders)
        ORDER BY entry_id, section, value_text
    ");
    $stmt->execute($entryIds);
    $values = $stmt->fetchAll();

    // Group values by entry_id
    $valuesByEntry = [];
    foreach ($values as $v) {
        $valuesByEntry[$v['entry_id']][$v['section']][] = $v['value_text'];
    }

    // Merge values into entries
    foreach ($entries as &$entry) {
        $entry['values'] = $valuesByEntry[$entry['id']] ?? [];
    }
}

// Get total count
$countSql = 'SELECT COUNT(*) FROM stats_entries se';
if (!empty($conditions)) {
    $countSql .= ' WHERE ' . implode(' AND ', $conditions);
}
$stmt = $db->prepare($countSql);
$stmt->execute(array_slice($params, 0, -2)); // Remove limit/offset params
$total = $stmt->fetchColumn();

jsonResponse([
    'items' => $entries,
    'total' => intval($total),
    'limit' => $limit,
    'offset' => $offset
]);
