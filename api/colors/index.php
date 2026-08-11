<?php
/**
 * Card Colors API
 * GET  /colors/index.php         - Get all card colors (public)
 * PUT  /colors/index.php?card=X  - Update colors for a card (admin only)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$validCards = ['person', 'zeitfenster', 'thema', 'referenz'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $db->query('SELECT card_key, bg_color, border_color, swatch_default, swatch_hover, swatch_checked, bg_image, bg_image_opacity FROM card_colors');
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['card_key'];
            unset($row['card_key']);
            $result[$key] = $row;
        }

        jsonResponse($result);
        break;

    case 'PUT':
        requireAdmin();

        $card = $_GET['card'] ?? null;

        if (!$card || !in_array($card, $validCards)) {
            errorResponse('Ungültige Karte. Erlaubt: ' . implode(', ', $validCards), 400);
        }

        $data = getJsonBody();

        // Validate color format: rgba(...) string or null
        $colorFields = ['bg_color', 'border_color', 'swatch_default', 'swatch_hover', 'swatch_checked'];
        $values = [];

        foreach ($colorFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if ($val !== null && !preg_match('/^rgba?\([\d\s,.\/%]+\)$/', $val) && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
                    errorResponse("Ungültiges Farbformat für $field", 400);
                }
                $values[$field] = $val;
            }
        }

        // Background image: path returned by upload.php, or null for the default texture
        if (array_key_exists('bg_image', $data)) {
            $val = $data['bg_image'];
            if ($val !== null && !preg_match('#^uploads/card-images/[A-Za-z0-9._-]+$#', $val)) {
                errorResponse('Ungültiger Wert für bg_image', 400);
            }
            $values['bg_image'] = $val;
        }

        // Background image opacity: number between 0 and 1, or null for CSS default
        if (array_key_exists('bg_image_opacity', $data)) {
            $val = $data['bg_image_opacity'];
            if ($val !== null && (!is_numeric($val) || $val < 0 || $val > 1)) {
                errorResponse('Ungültiger Wert für bg_image_opacity (0-1)', 400);
            }
            $values['bg_image_opacity'] = $val;
        }

        if (empty($values)) {
            errorResponse('Keine Farbwerte angegeben', 400);
        }

        // Build upsert query
        $setClauses = [];
        $params = [$card];

        $insertFields = ['card_key'];
        $insertPlaceholders = ['?'];
        $updateClauses = [];

        foreach ($values as $field => $val) {
            $insertFields[] = $field;
            $insertPlaceholders[] = '?';
            $params[] = $val;
            $updateClauses[] = "$field = VALUES($field)";
        }

        $sql = 'INSERT INTO card_colors (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertPlaceholders) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Return updated row
        $stmt = $db->prepare('SELECT card_key, bg_color, border_color, swatch_default, swatch_hover, swatch_checked, bg_image, bg_image_opacity FROM card_colors WHERE card_key = ?');
        $stmt->execute([$card]);
        $row = $stmt->fetch();

        if ($row) {
            $key = $row['card_key'];
            unset($row['card_key']);
            jsonResponse([$key => $row]);
        } else {
            jsonResponse([]);
        }
        break;

    default:
        errorResponse('Method not allowed', 405);
}
