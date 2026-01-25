<?php
/**
 * Analytics: Get available filter options
 * GET /analytics/filters.php
 *
 * Returns all active option values grouped by section,
 * with Kontakt section grouping kontaktart + person + dauer
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$db = getDB();

// Get all active options with their param_group
$stmt = $db->query('
    SELECT section, label, param_group
    FROM option_definitions
    WHERE is_active = 1
    ORDER BY section, sort_order, label
');
$options = $stmt->fetchAll();

// Group by section (for UI display)
$grouped = [];
foreach ($options as $opt) {
    $section = $opt['section'];
    if (!isset($grouped[$section])) {
        $grouped[$section] = [];
    }
    $grouped[$section][] = [
        'label' => $opt['label'],
        'group' => $opt['param_group']
    ];
}

// Build response with Kontakt grouping
$response = [
    'kontakt' => [
        'kontaktart' => $grouped['kontaktart'] ?? [],
        'person' => $grouped['person'] ?? [],
        'dauer' => $grouped['dauer'] ?? []
    ],
    'thema' => $grouped['thema'] ?? [],
    'zeitfenster' => $grouped['zeitfenster'] ?? [],
    'referenz' => $grouped['referenz'] ?? []
];

jsonResponse($response);
