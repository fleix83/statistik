<?php
/**
 * Chart Markers API
 * GET    /analytics/markers.php - List all markers
 * POST   /analytics/markers.php - Create a marker
 * PUT    /analytics/markers.php?id=X - Update a marker
 * DELETE /analytics/markers.php?id=X - Delete a marker
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List all markers
        // Check if is_active column exists
        $columns = $db->query("SHOW COLUMNS FROM chart_markers LIKE 'is_active'")->fetch();
        $hasIsActive = (bool)$columns;

        if ($hasIsActive) {
            $stmt = $db->query('
                SELECT id, name, start_date, end_date, color, is_active, created_at
                FROM chart_markers
                ORDER BY start_date DESC
            ');
        } else {
            $stmt = $db->query('
                SELECT id, name, start_date, end_date, color, created_at
                FROM chart_markers
                ORDER BY start_date DESC
            ');
        }
        $markers = $stmt->fetchAll();

        // Convert is_active to boolean (default to true if column doesn't exist)
        foreach ($markers as &$marker) {
            $marker['is_active'] = isset($marker['is_active']) ? (bool)$marker['is_active'] : true;
        }

        jsonResponse($markers);
        break;

    case 'POST':
        // Create a new marker
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['name']) || empty($data['start_date'])) {
            errorResponse('Name und Startdatum sind erforderlich', 400);
        }

        $name = trim($data['name']);
        $startDate = $data['start_date'];
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $color = !empty($data['color']) ? $data['color'] : '#f59e0b';

        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            errorResponse('Ungültiges Startdatum', 400);
        }
        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            errorResponse('Ungültiges Enddatum', 400);
        }

        // Check if is_active column exists
        $columns = $db->query("SHOW COLUMNS FROM chart_markers LIKE 'is_active'")->fetch();
        $hasIsActive = (bool)$columns;

        if ($hasIsActive) {
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            $stmt = $db->prepare('
                INSERT INTO chart_markers (name, start_date, end_date, color, is_active)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$name, $startDate, $endDate, $color, $isActive]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO chart_markers (name, start_date, end_date, color)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$name, $startDate, $endDate, $color]);
            $isActive = 1;
        }

        $id = $db->lastInsertId();

        jsonResponse([
            'id' => $id,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'color' => $color,
            'is_active' => (bool)$isActive
        ], 201);
        break;

    case 'PUT':
        // Update a marker
        $id = $_GET['id'] ?? null;

        if (!$id) {
            errorResponse('ID erforderlich', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Check if is_active column exists
        $columns = $db->query("SHOW COLUMNS FROM chart_markers LIKE 'is_active'")->fetch();
        $hasIsActive = (bool)$columns;

        // Build dynamic update query
        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['start_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) {
                errorResponse('Ungültiges Startdatum', 400);
            }
            $updates[] = 'start_date = ?';
            $params[] = $data['start_date'];
        }
        if (array_key_exists('end_date', $data)) {
            if ($data['end_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['end_date'])) {
                errorResponse('Ungültiges Enddatum', 400);
            }
            $updates[] = 'end_date = ?';
            $params[] = $data['end_date'] ?: null;
        }
        if (isset($data['color'])) {
            $updates[] = 'color = ?';
            $params[] = $data['color'];
        }
        if (isset($data['is_active']) && $hasIsActive) {
            $updates[] = 'is_active = ?';
            $params[] = (int)$data['is_active'];
        }

        if (empty($updates)) {
            // If only is_active was sent but column doesn't exist, just return current marker
            if (isset($data['is_active']) && !$hasIsActive) {
                $stmt = $db->prepare('SELECT id, name, start_date, end_date, color, created_at FROM chart_markers WHERE id = ?');
                $stmt->execute([$id]);
                $marker = $stmt->fetch();
                if (!$marker) {
                    errorResponse('Markierung nicht gefunden', 404);
                }
                $marker['is_active'] = true;
                jsonResponse($marker);
                break;
            }
            errorResponse('Keine Änderungen angegeben', 400);
        }

        $params[] = $id;
        $sql = 'UPDATE chart_markers SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            // Check if marker exists
            $check = $db->prepare('SELECT id FROM chart_markers WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                errorResponse('Markierung nicht gefunden', 404);
            }
        }

        // Fetch updated marker
        if ($hasIsActive) {
            $stmt = $db->prepare('SELECT id, name, start_date, end_date, color, is_active, created_at FROM chart_markers WHERE id = ?');
        } else {
            $stmt = $db->prepare('SELECT id, name, start_date, end_date, color, created_at FROM chart_markers WHERE id = ?');
        }
        $stmt->execute([$id]);
        $marker = $stmt->fetch();
        $marker['is_active'] = isset($marker['is_active']) ? (bool)$marker['is_active'] : true;

        jsonResponse($marker);
        break;

    case 'DELETE':
        // Delete a marker
        $id = $_GET['id'] ?? null;

        if (!$id) {
            errorResponse('ID erforderlich', 400);
        }

        $stmt = $db->prepare('DELETE FROM chart_markers WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            errorResponse('Markierung nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        errorResponse('Method not allowed', 405);
}
