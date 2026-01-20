<?php
/**
 * Options: Reorder options within a section (updates option_definitions directly)
 * POST /options/reorder.php
 * Body: { "section": "thema", "items": [{"id": 1, "sort_order": 0}, {"id": 2, "sort_order": 1}] }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$data = getJsonBody();
$section = $data['section'] ?? '';
$items = $data['items'] ?? [];

$validSections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];

if (empty($section) || !in_array($section, $validSections)) {
    errorResponse('Invalid section');
}

if (empty($items) || !is_array($items)) {
    errorResponse('Items array required');
}

$db = getDB();
$db->beginTransaction();

try {
    foreach ($items as $item) {
        $id = intval($item['id'] ?? 0);
        $sortOrder = intval($item['sort_order'] ?? 0);

        if ($id <= 0) continue;

        // Update sort_order directly in option_definitions
        $stmt = $db->prepare('UPDATE option_definitions SET sort_order = ? WHERE id = ? AND section = ?');
        $stmt->execute([$sortOrder, $id, $section]);
    }

    $db->commit();
    jsonResponse(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Reorder failed: ' . $e->getMessage(), 500);
}
