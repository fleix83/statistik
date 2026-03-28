<?php
/**
 * Analytics: Multi-period comparison
 * GET /analytics/compare.php?periods=[{"start":"2023-01-01","end":"2023-12-31","label":"2023"},{"start":"2024-01-01","end":"2024-12-31","label":"2024"}]&section=thema&values=Bildung,Arbeit
 *
 * Returns aggregated totals for each period to enable comparison
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$periodsJson = $_GET['periods'] ?? '';
$section = $_GET['section'] ?? '';
$values = isset($_GET['values']) ? explode(',', $_GET['values']) : [];

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Valid section parameter required');
}

$periods = json_decode($periodsJson, true);
if (!$periods || !is_array($periods) || count($periods) < 1) {
    errorResponse('At least one period is required (JSON array)');
}

// Validate periods
foreach ($periods as $period) {
    if (!isset($period['start']) || !isset($period['end']) || !isset($period['label'])) {
        errorResponse('Each period must have start, end, and label properties');
    }
}

$db = getDB();

$periodLabels = array_column($periods, 'label');
$datasets = [];

// If values are specified, get data for each value
if (!empty($values)) {
    foreach ($values as $value) {
        $value = trim($value);
        $data = [];

        foreach ($periods as $period) {
            $sql = "
                SELECT COUNT(DISTINCT sev.entry_id) as count
                FROM stats_entry_values sev
                JOIN stats_entries se ON sev.entry_id = se.id
                WHERE sev.section = ?
                  AND sev.value_text = ?
                  AND DATE(se.created_at) >= ?
                  AND DATE(se.created_at) <= ?
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$section, $value, $period['start'], $period['end']]);
            $result = $stmt->fetch();
            $data[] = intval($result['count']);
        }

        $datasets[] = [
            'label' => $value,
            'data' => $data
        ];
    }
} else {
    // If no values specified, get all values for the section
    // First, get all unique values in any of the periods
    $allValues = [];
    foreach ($periods as $period) {
        $sql = "
            SELECT DISTINCT sev.value_text
            FROM stats_entry_values sev
            JOIN stats_entries se ON sev.entry_id = se.id
            WHERE sev.section = ?
              AND DATE(se.created_at) >= ?
              AND DATE(se.created_at) <= ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$section, $period['start'], $period['end']]);
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $allValues = array_unique(array_merge($allValues, $values));
    }

    sort($allValues);

    foreach ($allValues as $value) {
        $data = [];

        foreach ($periods as $period) {
            $sql = "
                SELECT COUNT(DISTINCT sev.entry_id) as count
                FROM stats_entry_values sev
                JOIN stats_entries se ON sev.entry_id = se.id
                WHERE sev.section = ?
                  AND sev.value_text = ?
                  AND DATE(se.created_at) >= ?
                  AND DATE(se.created_at) <= ?
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$section, $value, $period['start'], $period['end']]);
            $result = $stmt->fetch();
            $data[] = intval($result['count']);
        }

        $datasets[] = [
            'label' => $value,
            'data' => $data
        ];
    }
}

// Get total entries per period
$totals = [];
foreach ($periods as $period) {
    $sql = "
        SELECT COUNT(*) as count
        FROM stats_entries
        WHERE DATE(created_at) >= ?
          AND DATE(created_at) <= ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$period['start'], $period['end']]);
    $result = $stmt->fetch();
    $totals[] = intval($result['count']);
}

jsonResponse([
    'periods' => $periodLabels,
    'datasets' => $datasets,
    'totals' => $totals,
    'section' => $section
]);
