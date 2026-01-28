<?php
/**
 * Filter Building Helper
 * Supports both flat filters and hierarchical group-based filtering
 *
 * Flat filters: { section: [values] } - OR within section, AND across sections
 * Hierarchy:    { hierarchy: [{ group, filters: { section: [values] } }] }
 *               - OR within same group (even across sections)
 *               - AND across different groups
 */

/**
 * Parse filters from JSON string, handling flat, hierarchy, and intersection formats
 */
function parseFilters($filtersJson) {
    $filters = json_decode($filtersJson, true) ?: [];

    // Check if this is a hierarchy format
    if (isset($filters['hierarchy'])) {
        return [
            'type' => 'hierarchy',
            'data' => $filters['hierarchy']
        ];
    }

    // Check if this is an intersection format (AND within section)
    if (isset($filters['intersection'])) {
        return [
            'type' => 'intersection',
            'data' => $filters['intersection']
        ];
    }

    // Flat format (backward compatibility)
    return [
        'type' => 'flat',
        'data' => $filters
    ];
}

/**
 * Build filter JOINs for flat filters (original behavior)
 * OR within same section, AND across sections
 * @param int $startIndex Starting index for alias naming (to avoid conflicts)
 */
function buildFlatFilterJoins($filters, &$params, $startIndex = 0) {
    $joins = '';
    $i = $startIndex;
    foreach ($filters as $section => $values) {
        if (empty($values)) continue;
        $alias = "f{$i}";
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $joins .= " JOIN stats_entry_values {$alias} ON se.id = {$alias}.entry_id
                    AND {$alias}.section = ? AND {$alias}.value_text IN ({$placeholders})";
        $params[] = $section;
        foreach ($values as $v) {
            $params[] = $v;
        }
        $i++;
    }
    return $joins;
}

/**
 * Build filter JOINs for hierarchical filters
 * OR within same group (even across sections), AND across different groups
 *
 * Hierarchy format:
 * [
 *   { group: 'kontakt_methode', filters: { kontaktart: ['Telefon'], person: ['Mann'] } },
 *   { group: 'thema_allgemein', filters: { thema: ['Arbeit', 'Bildung'] } }
 * ]
 *
 * This becomes:
 * - Group 1 (OR): entries that have (kontaktart=Telefon OR person=Mann)
 * - Group 2 (AND with Group 1, OR within): entries that ALSO have (thema=Arbeit OR thema=Bildung)
 *
 * @param int $startIndex Starting index for alias naming (to avoid conflicts)
 */
function buildHierarchyFilterJoins($hierarchy, &$params, $startIndex = 0) {
    $joins = '';
    $groupIndex = $startIndex;

    foreach ($hierarchy as $level) {
        if (empty($level['filters'])) continue;

        // Build OR conditions for all sections within this group
        $orConditions = [];
        $groupParams = [];

        foreach ($level['filters'] as $section => $values) {
            if (empty($values)) continue;

            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $orConditions[] = "(section = ? AND value_text IN ({$placeholders}))";
            $groupParams[] = $section;
            foreach ($values as $v) {
                $groupParams[] = $v;
            }
        }

        if (empty($orConditions)) continue;

        // Create a subquery that finds entries matching ANY of the OR conditions
        $alias = "hg{$groupIndex}";
        $orClause = implode(' OR ', $orConditions);
        $joins .= " JOIN (
            SELECT DISTINCT entry_id
            FROM stats_entry_values
            WHERE {$orClause}
        ) AS {$alias} ON se.id = {$alias}.entry_id";

        // Add params for this group
        foreach ($groupParams as $p) {
            $params[] = $p;
        }

        $groupIndex++;
    }

    return $joins;
}

/**
 * Build filter JOINs for intersection filters (AND within same section)
 * Used for subset drilling where multiple values must ALL be present
 *
 * Intersection format:
 * { section: [value1, value2, value3] }
 *
 * This requires ALL values to be present (value1 AND value2 AND value3)
 *
 * @param int $startIndex Starting index for alias naming
 */
function buildIntersectionFilterJoins($intersection, &$params, $startIndex = 0) {
    $joins = '';
    $i = $startIndex;

    foreach ($intersection as $section => $values) {
        if (empty($values)) continue;

        // Each value gets its own JOIN - entry must have ALL values
        foreach ($values as $value) {
            $alias = "int{$i}";
            $joins .= " JOIN stats_entry_values {$alias} ON se.id = {$alias}.entry_id
                        AND {$alias}.section = ? AND {$alias}.value_text = ?";
            $params[] = $section;
            $params[] = $value;
            $i++;
        }
    }

    return $joins;
}

/**
 * Build filter JOINs based on parsed filter structure
 * @param int $startIndex Starting index for alias naming (to avoid conflicts)
 */
function buildFilterJoins($parsedFilters, &$params, $startIndex = 0) {
    if ($parsedFilters['type'] === 'hierarchy') {
        return buildHierarchyFilterJoins($parsedFilters['data'], $params, $startIndex);
    }

    if ($parsedFilters['type'] === 'intersection') {
        return buildIntersectionFilterJoins($parsedFilters['data'], $params, $startIndex);
    }

    return buildFlatFilterJoins($parsedFilters['data'], $params, $startIndex);
}
