<?php
/**
 * Options: Get merged view of published + draft options for Editor
 * GET /options/draft.php
 * GET /options/draft.php?section=thema
 *
 * Returns options with draft status indicators:
 * - draft_action: null (no changes), 'create', 'update', 'delete'
 * - draft_id: ID in draft table (for pending changes)
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

requireAdmin();

$db = getDB();
$section = $_GET['section'] ?? null;

// Get all published options
$publishedSql = 'SELECT id, section, label, sort_order, is_active, keywords, created_at FROM option_definitions';
$publishedParams = [];

if ($section) {
    $publishedSql .= ' WHERE section = ?';
    $publishedParams[] = $section;
}

$publishedSql .= ' ORDER BY section, sort_order, label';

$stmt = $db->prepare($publishedSql);
$stmt->execute($publishedParams);
$published = $stmt->fetchAll();

// Get all draft changes
$draftSql = 'SELECT id, original_id, section, label, sort_order, is_active, keywords, action, created_at FROM option_definitions_draft';
$draftParams = [];

if ($section) {
    $draftSql .= ' WHERE section = ?';
    $draftParams[] = $section;
}

$stmt = $db->prepare($draftSql);
$stmt->execute($draftParams);
$drafts = $stmt->fetchAll();

// Index drafts by original_id for updates/deletes
$draftsByOriginalId = [];
$newDrafts = [];
foreach ($drafts as $draft) {
    if ($draft['original_id'] === null) {
        $newDrafts[] = $draft;
    } else {
        $draftsByOriginalId[$draft['original_id']] = $draft;
    }
}

// Build merged result
$result = [];

// Process published options
foreach ($published as $opt) {
    $item = [
        'id' => $opt['id'],
        'section' => $opt['section'],
        'label' => $opt['label'],
        'sort_order' => $opt['sort_order'],
        'is_active' => (bool)$opt['is_active'],
        'keywords' => $opt['keywords'] ? json_decode($opt['keywords'], true) : [],
        'created_at' => $opt['created_at'],
        'draft_action' => null,
        'draft_id' => null
    ];

    // Check if there's a draft change for this option
    if (isset($draftsByOriginalId[$opt['id']])) {
        $draft = $draftsByOriginalId[$opt['id']];
        $item['draft_action'] = $draft['action'];
        $item['draft_id'] = $draft['id'];

        // For updates, show the draft values
        if ($draft['action'] === 'update') {
            $item['label'] = $draft['label'];
            $item['sort_order'] = $draft['sort_order'];
            $item['is_active'] = (bool)$draft['is_active'];
            $item['keywords'] = $draft['keywords'] ? json_decode($draft['keywords'], true) : [];
        }
    }

    $result[] = $item;
}

// Add new drafts (created but not published)
foreach ($newDrafts as $draft) {
    $result[] = [
        'id' => 'new_' . $draft['id'], // Temporary ID for frontend
        'section' => $draft['section'],
        'label' => $draft['label'],
        'sort_order' => $draft['sort_order'],
        'is_active' => (bool)$draft['is_active'],
        'keywords' => $draft['keywords'] ? json_decode($draft['keywords'], true) : [],
        'created_at' => $draft['created_at'],
        'draft_action' => 'create',
        'draft_id' => $draft['id']
    ];
}

// Sort by section, sort_order, label
usort($result, function($a, $b) {
    $sectionCmp = strcmp($a['section'], $b['section']);
    if ($sectionCmp !== 0) return $sectionCmp;

    $sortCmp = $a['sort_order'] - $b['sort_order'];
    if ($sortCmp !== 0) return $sortCmp;

    return strcmp($a['label'], $b['label']);
});

// Get publish state
$stmt = $db->query('SELECT has_pending_changes, last_published_at, last_published_by FROM publish_state LIMIT 1');
$publishState = $stmt->fetch();

jsonResponse([
    'options' => $result,
    'publish_state' => [
        'has_pending_changes' => (bool)($publishState['has_pending_changes'] ?? false),
        'last_published_at' => $publishState['last_published_at'] ?? null,
        'last_published_by' => $publishState['last_published_by'] ?? null
    ]
]);
