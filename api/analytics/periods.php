<?php
/**
 * Saved Periods API
 * GET    /analytics/periods.php - List all saved period configurations
 * POST   /analytics/periods.php - Create a new configuration
 * PUT    /analytics/periods.php?id=X - Update a configuration
 * DELETE /analytics/periods.php?id=X - Delete a configuration
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List all saved period configurations
        $stmt = $db->query('
            SELECT id, name, periods, is_active, created_at
            FROM saved_periods
            ORDER BY is_active DESC, created_at DESC
        ');
        $configs = $stmt->fetchAll();

        // Parse JSON periods and convert is_active to boolean
        foreach ($configs as &$config) {
            $config['periods'] = json_decode($config['periods'], true);
            $config['is_active'] = (bool)$config['is_active'];
        }

        jsonResponse($configs);
        break;

    case 'POST':
        // Create a new configuration
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['name']) || empty($data['periods'])) {
            errorResponse('Name und Perioden sind erforderlich', 400);
        }

        $name = trim($data['name']);
        $periods = json_encode($data['periods']);
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 0;

        // Multiple periods can be active at once (for comparison)

        $stmt = $db->prepare('
            INSERT INTO saved_periods (name, periods, is_active)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$name, $periods, $isActive]);

        $id = $db->lastInsertId();

        jsonResponse([
            'id' => $id,
            'name' => $name,
            'periods' => $data['periods'],
            'is_active' => (bool)$isActive
        ], 201);
        break;

    case 'PUT':
        // Update a configuration
        $id = $_GET['id'] ?? null;

        if (!$id) {
            errorResponse('ID erforderlich', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Build dynamic update query
        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['periods'])) {
            $updates[] = 'periods = ?';
            $params[] = json_encode($data['periods']);
        }
        if (isset($data['is_active'])) {
            // Multiple periods can be active at once (for comparison)
            $updates[] = 'is_active = ?';
            $params[] = (int)$data['is_active'];
        }

        if (empty($updates)) {
            errorResponse('Keine Änderungen angegeben', 400);
        }

        $params[] = $id;
        $sql = 'UPDATE saved_periods SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            // Check if config exists
            $check = $db->prepare('SELECT id FROM saved_periods WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                errorResponse('Konfiguration nicht gefunden', 404);
            }
        }

        // Fetch updated config
        $stmt = $db->prepare('SELECT id, name, periods, is_active, created_at FROM saved_periods WHERE id = ?');
        $stmt->execute([$id]);
        $config = $stmt->fetch();
        $config['periods'] = json_decode($config['periods'], true);
        $config['is_active'] = (bool)$config['is_active'];

        jsonResponse($config);
        break;

    case 'DELETE':
        // Delete a configuration
        $id = $_GET['id'] ?? null;

        if (!$id) {
            errorResponse('ID erforderlich', 400);
        }

        $stmt = $db->prepare('DELETE FROM saved_periods WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            errorResponse('Konfiguration nicht gefunden', 404);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        errorResponse('Method not allowed', 405);
}
