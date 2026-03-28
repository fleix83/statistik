-- ============================================================================
-- REVISED SCHEMA: Helpdesk Statistik (Multi-Select Support)
-- ============================================================================
-- Supports:
-- - Historical multi-select data import
-- - Dynamic option management (add/edit/delete/reorder)
-- - Data integrity (historical values preserved even when options change)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. USERS TABLE (unchanged)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. OPTION DEFINITIONS (expanded sections)
-- ----------------------------------------------------------------------------
CREATE TABLE option_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section ENUM(
        'kontaktart',        -- Besuch, Telefon, Mail, Passant/in
        'person',            -- Mann, Frau, Altersgruppen, Betroffenheit
        'thema',             -- Bildung, Arbeit, Gesundheit, etc.
        'zeitfenster',       -- 11:30-12:00, 12:00-13:00, etc.
        'tageszeit',         -- Vormittag, Nachmittag
        'dauer',             -- < 5min, 5-15min, > 20min, etc.
        'referenz'           -- Flyer, Internet, Empfehlung, etc.
    ) NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_section_active (section, is_active),
    INDEX idx_section_sort (section, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. STATS ENTRIES (simplified - no value columns!)
-- ----------------------------------------------------------------------------
CREATE TABLE stats_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Optional: Freetext fields that don't fit multi-select model
    reference_remarks TEXT NULL COMMENT 'Freitext für "Andere Bem" etc.',
    notes TEXT NULL COMMENT 'Allgemeine Notizen',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. STATS ENTRY VALUES (Junction Table with Text Storage)
-- ----------------------------------------------------------------------------
-- This is the KEY table that allows:
-- - Multiple selections per entry per section
-- - Historical data preservation (text values, not FK references)
-- - No schema changes when adding new options
-- ----------------------------------------------------------------------------
CREATE TABLE stats_entry_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    section ENUM(
        'kontaktart',
        'person',
        'thema',
        'zeitfenster',
        'tageszeit',
        'dauer',
        'referenz'
    ) NOT NULL,
    value_text VARCHAR(100) NOT NULL COMMENT 'Actual text value, e.g. "Mann", "Bildung", "WLAN"',
    
    FOREIGN KEY (entry_id) REFERENCES stats_entries(id) ON DELETE CASCADE,
    INDEX idx_entry_section (entry_id, section),
    INDEX idx_section_value (section, value_text),
    
    -- Prevent duplicate values for same entry+section
    UNIQUE KEY uk_entry_section_value (entry_id, section, value_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- EXAMPLE: How data is stored
-- ============================================================================

-- Entry #499 from CSV (Zeile 2):
-- ID=499, Datum=19.03.25, User=Tosca
-- Besuch=WAHR, Mann=WAHR, Frau=WAHR, unter 55=WAHR, selbst betroffen=WAHR
-- Bildung=WAHR, Migrationshintergrund=WAHR, 12:00-13:00=WAHR

-- Would be stored as:
-- 
-- stats_entries:
-- | id  | user_id | created_at          | reference_remarks | notes |
-- |-----|---------|---------------------|-------------------|-------|
-- | 499 | 5       | 2025-03-19 12:30:00 | NULL              | NULL  |
--
-- stats_entry_values:
-- | id | entry_id | section      | value_text            |
-- |----|----------|--------------|-----------------------|
-- | 1  | 499      | kontaktart   | Besuch                |
-- | 2  | 499      | person       | Mann                  |
-- | 3  | 499      | person       | Frau                  |
-- | 4  | 499      | person       | unter 55              |
-- | 5  | 499      | person       | selbst betroffen      |
-- | 6  | 499      | thema        | Bildung               |
-- | 7  | 499      | thema        | Migrationshintergrund |
-- | 8  | 499      | zeitfenster  | 12:00 - 13:00         |


-- ============================================================================
-- QUERIES: Common Operations
-- ============================================================================

-- Get all values for a specific entry:
SELECT 
    section,
    GROUP_CONCAT(value_text ORDER BY value_text SEPARATOR ', ') as values
FROM stats_entry_values
WHERE entry_id = 499
GROUP BY section;

-- Count entries by topic:
SELECT 
    value_text as topic,
    COUNT(DISTINCT entry_id) as count
FROM stats_entry_values
WHERE section = 'thema'
GROUP BY value_text
ORDER BY count DESC;

-- Find all entries with multiple specific criteria:
SELECT DISTINCT se.id, se.created_at
FROM stats_entries se
JOIN stats_entry_values sev1 ON se.id = sev1.entry_id AND sev1.section = 'person' AND sev1.value_text = 'Mann'
JOIN stats_entry_values sev2 ON se.id = sev2.entry_id AND sev2.section = 'thema' AND sev2.value_text = 'Bildung'
WHERE se.created_at >= '2025-03-01';

-- Get complete entry with all selections:
SELECT 
    se.id,
    se.created_at,
    u.username,
    se.reference_remarks,
    GROUP_CONCAT(
        CASE WHEN sev.section = 'kontaktart' THEN sev.value_text END 
        ORDER BY sev.value_text SEPARATOR ', '
    ) as kontaktart,
    GROUP_CONCAT(
        CASE WHEN sev.section = 'person' THEN sev.value_text END 
        ORDER BY sev.value_text SEPARATOR ', '
    ) as person,
    GROUP_CONCAT(
        CASE WHEN sev.section = 'thema' THEN sev.value_text END 
        ORDER BY sev.value_text SEPARATOR ', '
    ) as themen,
    GROUP_CONCAT(
        CASE WHEN sev.section = 'zeitfenster' THEN sev.value_text END 
        ORDER BY sev.value_text SEPARATOR ', '
    ) as zeitfenster,
    GROUP_CONCAT(
        CASE WHEN sev.section = 'referenz' THEN sev.value_text END 
        ORDER BY sev.value_text SEPARATOR ', '
    ) as referenz
FROM stats_entries se
JOIN users u ON se.user_id = u.id
LEFT JOIN stats_entry_values sev ON se.id = sev.entry_id
WHERE se.id = 499
GROUP BY se.id, se.created_at, u.username, se.reference_remarks;


-- ============================================================================
-- ADMIN OPERATIONS: Add/Edit/Delete Options
-- ============================================================================

-- Add new topic "Reisen":
INSERT INTO option_definitions (section, label, sort_order, is_active)
VALUES ('thema', 'Reisen', 100, TRUE);

-- Reorder "Wohnen" to top:
UPDATE option_definitions 
SET sort_order = 0 
WHERE section = 'thema' AND label = 'Wohnen';

-- Soft-delete old option (keeps historical data intact):
UPDATE option_definitions 
SET is_active = FALSE 
WHERE section = 'thema' AND label = 'Alte Option';

-- Get active options for dropdown (ordered):
SELECT label 
FROM option_definitions 
WHERE section = 'thema' AND is_active = TRUE 
ORDER BY sort_order, label;


-- ============================================================================
-- INDEXES for Performance
-- ============================================================================

-- Already created above, but listing them for reference:
-- - stats_entries: idx_created_at, idx_user_id
-- - stats_entry_values: idx_entry_section, idx_section_value
-- - option_definitions: idx_section_active, idx_section_sort