<?php
/**
 * Options: List all options or by section
 * GET /options/list.php
 * GET /options/list.php?section=thema
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$db = getDB();
$section = $_GET['section'] ?? null;
$activeOnly = isset($_GET['active']) ? filter_var($_GET['active'], FILTER_VALIDATE_BOOLEAN) : true;

$sql = 'SELECT id, section, label, sort_order, is_active, keywords, created_at FROM option_definitions';
$params = [];
$conditions = [];

if ($section) {
    $conditions[] = 'section = ?';
    $params[] = $section;
}

if ($activeOnly) {
    $conditions[] = 'is_active = 1';
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY section, sort_order, label';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$options = $stmt->fetchAll();

// Parse keywords JSON
foreach ($options as &$opt) {
    $opt['keywords'] = $opt['keywords'] ? json_decode($opt['keywords'], true) : [];
}

jsonResponse($options);
