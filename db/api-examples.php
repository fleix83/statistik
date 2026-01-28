<?php
// ============================================================================
// API IMPLEMENTATION EXAMPLES for Multi-Select Schema
// ============================================================================
// This file shows the core PHP endpoints you'll need
// ============================================================================

// ----------------------------------------------------------------------------
// 1. GET /api/options.php - Load all active options for dropdowns
// ----------------------------------------------------------------------------
// Frontend calls this on page load to populate all dropdowns

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once 'db.php'; // Your PDO connection
    
    try {
        $stmt = $pdo->prepare("
            SELECT section, label, sort_order 
            FROM option_definitions 
            WHERE is_active = TRUE 
            ORDER BY section, sort_order, label
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by section for easier frontend consumption
        $grouped = [];
        foreach ($results as $row) {
            $section = $row['section'];
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $row['label'];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'options' => $grouped
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/*
RESPONSE EXAMPLE:
{
  "success": true,
  "options": {
    "kontaktart": ["Besuch", "Telefon", "Mail", "Passant/in"],
    "person": ["Mann", "Frau", "unter 55", "über 55", "selbst betroffen", ...],
    "thema": ["Bildung", "Arbeit", "Gesundheit", "Wohnen", "Reisen", ...],
    "zeitfenster": ["11:30 - 12:00", "12:00 - 13:00", ...],
    "referenz": ["Internet", "Flyer", "Empfehlung", ...]
  }
}
*/


// ----------------------------------------------------------------------------
// 2. POST /api/submit.php - Submit new entry with multi-select values
// ----------------------------------------------------------------------------
// Frontend sends selected values per section

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    
    // Verify user is logged in
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    /*
    EXPECTED INPUT:
    {
      "kontaktart": ["Besuch"],
      "person": ["Mann", "unter 55", "selbst betroffen"],
      "thema": ["Bildung", "Arbeit"],
      "zeitfenster": ["14:00 - 15:00"],
      "referenz": ["Internet"],
      "reference_remarks": "Optional freetext"
    }
    */
    
    try {
        $pdo->beginTransaction();
        
        // 1. Insert main entry
        $stmt = $pdo->prepare("
            INSERT INTO stats_entries (user_id, created_at, reference_remarks)
            VALUES (:user_id, NOW(), :remarks)
        ");
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'remarks' => $data['reference_remarks'] ?? null
        ]);
        
        $entry_id = $pdo->lastInsertId();
        
        // 2. Insert all selected values
        $stmt = $pdo->prepare("
            INSERT INTO stats_entry_values (entry_id, section, value_text)
            VALUES (:entry_id, :section, :value)
        ");
        
        $sections = ['kontaktart', 'person', 'thema', 'zeitfenster', 'tageszeit', 'dauer', 'referenz'];
        
        foreach ($sections as $section) {
            if (isset($data[$section]) && is_array($data[$section])) {
                foreach ($data[$section] as $value) {
                    $stmt->execute([
                        'entry_id' => $entry_id,
                        'section' => $section,
                        'value' => $value
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'entry_id' => $entry_id
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}


// ----------------------------------------------------------------------------
// 3. POST /api/admin/options.php - Admin: Add new option
// ----------------------------------------------------------------------------
// Admin adds "Reisen" to topics

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    
    // Verify admin role
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    /*
    EXPECTED INPUT:
    {
      "action": "add",
      "section": "thema",
      "label": "Reisen",
      "sort_order": 100
    }
    */
    
    try {
        if ($data['action'] === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO option_definitions (section, label, sort_order, is_active)
                VALUES (:section, :label, :sort_order, TRUE)
            ");
            $stmt->execute([
                'section' => $data['section'],
                'label' => $data['label'],
                'sort_order' => $data['sort_order'] ?? 0
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Option added successfully'
            ]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}


// ----------------------------------------------------------------------------
// 4. PUT /api/admin/options.php - Admin: Reorder options
// ----------------------------------------------------------------------------
// Admin drags "Wohnen" to top position

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_once 'db.php';
    
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    /*
    EXPECTED INPUT:
    {
      "section": "thema",
      "order": [
        {"label": "Wohnen", "sort_order": 0},
        {"label": "Bildung", "sort_order": 1},
        {"label": "Arbeit", "sort_order": 2},
        ...
      ]
    }
    */
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE option_definitions 
            SET sort_order = :sort_order 
            WHERE section = :section AND label = :label
        ");
        
        foreach ($data['order'] as $item) {
            $stmt->execute([
                'section' => $data['section'],
                'label' => $item['label'],
                'sort_order' => $item['sort_order']
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}


// ----------------------------------------------------------------------------
// 5. GET /api/export.php - Export to CSV
// ----------------------------------------------------------------------------
// Generate CSV with multi-select values concatenated

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once 'db.php';
    
    try {
        $stmt = $pdo->query("
            SELECT 
                se.id,
                se.created_at,
                u.username,
                GROUP_CONCAT(
                    CASE WHEN sev.section = 'kontaktart' THEN sev.value_text END 
                    ORDER BY sev.value_text SEPARATOR '; '
                ) as kontaktart,
                GROUP_CONCAT(
                    CASE WHEN sev.section = 'person' THEN sev.value_text END 
                    ORDER BY sev.value_text SEPARATOR '; '
                ) as person,
                GROUP_CONCAT(
                    CASE WHEN sev.section = 'thema' THEN sev.value_text END 
                    ORDER BY sev.value_text SEPARATOR '; '
                ) as thema,
                GROUP_CONCAT(
                    CASE WHEN sev.section = 'zeitfenster' THEN sev.value_text END 
                    ORDER BY sev.value_text SEPARATOR '; '
                ) as zeitfenster,
                GROUP_CONCAT(
                    CASE WHEN sev.section = 'referenz' THEN sev.value_text END 
                    ORDER BY sev.value_text SEPARATOR '; '
                ) as referenz,
                se.reference_remarks
            FROM stats_entries se
            JOIN users u ON se.user_id = u.id
            LEFT JOIN stats_entry_values sev ON se.id = sev.entry_id
            GROUP BY se.id, se.created_at, u.username, se.reference_remarks
            ORDER BY se.created_at DESC
        ");
        
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="statistik_export.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'Datum', 'Benutzer', 'Kontaktart', 'Person', 'Thema', 'Zeitfenster', 'Referenz', 'Bemerkungen']);
        
        // Data rows
        foreach ($entries as $entry) {
            fputcsv($output, $entry);
        }
        
        fclose($output);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}


// ----------------------------------------------------------------------------
// 6. GET /api/stats.php - Statistics aggregation
// ----------------------------------------------------------------------------
// Get counts per topic, person type, etc.

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once 'db.php';
    
    $section = $_GET['section'] ?? 'thema'; // Default to topics
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    try {
        $sql = "
            SELECT 
                sev.value_text as label,
                COUNT(DISTINCT sev.entry_id) as count
            FROM stats_entry_values sev
            JOIN stats_entries se ON sev.entry_id = se.id
            WHERE sev.section = :section
        ";
        
        if ($start_date) {
            $sql .= " AND se.created_at >= :start_date";
        }
        if ($end_date) {
            $sql .= " AND se.created_at <= :end_date";
        }
        
        $sql .= " GROUP BY sev.value_text ORDER BY count DESC";
        
        $stmt = $pdo->prepare($sql);
        $params = ['section' => $section];
        if ($start_date) $params['start_date'] = $start_date;
        if ($end_date) $params['end_date'] = $end_date;
        
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'section' => $section,
            'stats' => $stats
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/*
RESPONSE EXAMPLE for /api/stats.php?section=thema:
{
  "success": true,
  "section": "thema",
  "stats": [
    {"label": "Bildung", "count": 342},
    {"label": "Arbeit", "count": 289},
    {"label": "Gesundheit", "count": 256},
    {"label": "Wohnen", "count": 198},
    {"label": "Reisen", "count": 12}
  ]
}
*/
?>