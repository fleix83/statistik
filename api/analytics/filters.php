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

// Check if behavior column exists
$behaviorColumnExists = false;
try {
    $checkStmt = $db->query("SHOW COLUMNS FROM option_definitions LIKE 'behavior'");
    $behaviorColumnExists = $checkStmt->rowCount() > 0;
} catch (PDOException $e) {
    // Column check failed, assume it doesn't exist
}

// Get all active options with their param_group (and behavior if it exists)
$sql = $behaviorColumnExists
    ? 'SELECT section, label, param_group, behavior FROM option_definitions WHERE is_active = 1 ORDER BY section, sort_order, label'
    : 'SELECT section, label, param_group FROM option_definitions WHERE is_active = 1 ORDER BY section, sort_order, label';

$stmt = $db->query($sql);
$options = $stmt->fetchAll();

// Define subtract_only groups as fallback if column doesn't exist
$subtractOnlyGroups = ['background', 'duration'];

// Group by section (for UI display)
$grouped = [];
foreach ($options as $opt) {
    $section = $opt['section'];
    if (!isset($grouped[$section])) {
        $grouped[$section] = [];
    }

    // Determine behavior: from column if exists, otherwise from fallback
    $behavior = 'standard';
    if ($behaviorColumnExists && isset($opt['behavior'])) {
        $behavior = $opt['behavior'];
    } elseif (in_array($opt['param_group'], $subtractOnlyGroups)) {
        $behavior = 'subtract_only';
    }

    $grouped[$section][] = [
        'label' => $opt['label'],
        'group' => $opt['param_group'],
        'behavior' => $behavior
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
