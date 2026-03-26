<?php
/**
 * Rueckschau Fields: CRUD for configured lookback fields
 * GET  /rueckschau/fields.php — Returns all configured fields (no auth)
 * POST /rueckschau/fields.php — Bulk-replace all fields (admin only)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT id, section, value_text, sort_order FROM rueckschau_fields ORDER BY sort_order ASC');
    $fields = $stmt->fetchAll();
    jsonResponse($fields);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();

    $body = getJsonBody();
    $fields = $body['fields'] ?? [];

    if (!is_array($fields)) {
        errorResponse('fields must be an array');
    }

    $db->beginTransaction();
    try {
        // Delete all existing fields
        $db->exec('DELETE FROM rueckschau_fields');

        // Insert new fields
        if (!empty($fields)) {
            $stmt = $db->prepare('
                INSERT INTO rueckschau_fields (section, value_text, sort_order)
                VALUES (?, ?, ?)
            ');
            foreach ($fields as $i => $field) {
                $section = $field['section'] ?? '';
                $valueText = $field['value_text'] ?? '';
                if (empty($section) || empty($valueText)) continue;
                $stmt->execute([$section, $valueText, $i]);
            }
        }

        $db->commit();
        jsonResponse(['success' => true, 'count' => count($fields)]);
    } catch (Exception $e) {
        $db->rollBack();
        errorResponse('Failed to save fields: ' . $e->getMessage(), 500);
    }
}

errorResponse('Method not allowed', 405);
