-- ============================================================================
-- HELPDESK STATISTIK - MYSQL SCHEMA
-- ============================================================================
-- Verified production-ready schema for multi-select support
-- Run this file to create the complete database structure
-- ============================================================================

-- ----------------------------------------------------------------------------
-- DATABASE CREATION
-- ----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS helpdesk_stats
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helpdesk_stats;

-- ----------------------------------------------------------------------------
-- DROP EXISTING TABLES (for clean install)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS stats_entry_values;
DROP TABLE IF EXISTS stats_entries;
DROP TABLE IF EXISTS option_definitions;
DROP TABLE IF EXISTS users;

-- ============================================================================
-- TABLE 1: USERS
-- ============================================================================
-- User authentication and role management
-- Roles: 'admin' (can manage options), 'user' (can create entries)
-- ============================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 2: OPTION_DEFINITIONS
-- ============================================================================
-- Configurable dropdown options per section
-- Sections:
--   kontaktart  = Contact method (Besuch, Telefon, Mail, Passant/in)
--   person      = Demographics (Mann, Frau, age groups, affected status)
--   thema       = Topics (Bildung, Arbeit, Gesundheit, etc.)
--   zeitfenster = Time slots (11:30-12:00, 12:00-13:00, etc.)
--   tageszeit   = Time of day (Vormittag, Nachmittag)
--   dauer       = Duration (< 5min, 5-15min, > 20min)
--   referenz    = Referral source (Internet, Flyer, Empfehlung, etc.)
-- ============================================================================
CREATE TABLE option_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section ENUM(
        'kontaktart',
        'person',
        'thema',
        'zeitfenster',
        'tageszeit',
        'dauer',
        'referenz'
    ) NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Prevent duplicate labels within same section
    UNIQUE KEY uk_section_label (section, label),

    -- Performance indexes
    INDEX idx_section_active (section, is_active),
    INDEX idx_section_sort (section, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 3: STATS_ENTRIES
-- ============================================================================
-- Main entry table - one row per recorded consultation/visit
-- Values are stored separately in stats_entry_values (many-to-many)
-- ============================================================================
CREATE TABLE stats_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reference_remarks TEXT NULL COMMENT 'Freetext for "Andere Bem" etc.',
    notes TEXT NULL COMMENT 'General notes',

    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 4: STATS_ENTRY_VALUES (Junction Table)
-- ============================================================================
-- Stores selected values for each entry
-- KEY DESIGN: Text values (not FK) to preserve historical data
-- One entry can have multiple rows (multi-select support)
-- ============================================================================
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
    value_text VARCHAR(100) NOT NULL COMMENT 'Actual text value, e.g. "Mann", "Bildung"',

    CONSTRAINT fk_entry FOREIGN KEY (entry_id) REFERENCES stats_entries(id) ON DELETE CASCADE,

    -- Prevent duplicate values for same entry+section
    UNIQUE KEY uk_entry_section_value (entry_id, section, value_text),

    -- Performance indexes
    INDEX idx_entry_section (entry_id, section),
    INDEX idx_section_value (section, value_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA: OPTION_DEFINITIONS
-- ============================================================================
-- Pre-populate dropdown options based on CSV column headers
-- ============================================================================

-- Kontaktart (Contact Method)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('kontaktart', 'Besuch', 0),
    ('kontaktart', 'Telefon', 1),
    ('kontaktart', 'Mail', 2),
    ('kontaktart', 'Passant/in', 3);

-- Person (Demographics)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('person', 'Mann', 0),
    ('person', 'Frau', 1),
    ('person', 'unter 55', 2),
    ('person', 'über 55', 3),
    ('person', 'über 80', 4),
    ('person', 'selbst betroffen', 5),
    ('person', 'Angehörige Nachbarn und andere', 6),
    ('person', 'Institution', 7);

-- Tageszeit (Time of Day)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('tageszeit', 'Vormittag', 0),
    ('tageszeit', 'Nachmittag', 1);

-- Dauer (Duration)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('dauer', 'länger als 20 Minuten', 0);

-- Thema (Topics)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('thema', 'Bildung', 0),
    ('thema', 'Arbeit', 1),
    ('thema', 'Migrationshintergrund', 2),
    ('thema', 'Finanzen', 3),
    ('thema', 'Frauen Männer jung und alt', 4),
    ('thema', 'Gesundheit', 5),
    ('thema', 'Wohnen', 6),
    ('thema', 'Austausch und Freizeit', 7),
    ('thema', 'Migration und Integration', 8),
    ('thema', 'Notlagen', 9),
    ('thema', 'Allgemeine Hilfeleistungen', 10),
    ('thema', 'Recht', 11);

-- Referenz (Referral Source)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('referenz', 'Flyer/Plakat/Presse', 0),
    ('referenz', 'Internet', 1),
    ('referenz', 'empfohlen Freunde Bekannte', 2),
    ('referenz', 'empfohlen Institution', 3),
    ('referenz', 'empfohlen Fachperson', 4),
    ('referenz', 'War schon mal hier', 5),
    ('referenz', 'Andere', 6);

-- Zeitfenster (Time Slots)
INSERT INTO option_definitions (section, label, sort_order) VALUES
    ('zeitfenster', '11:30 - 12:00', 0),
    ('zeitfenster', '12:00 - 13:00', 1),
    ('zeitfenster', '13:00 - 14:00', 2),
    ('zeitfenster', '14:00 - 15:00', 3),
    ('zeitfenster', '15:00 - 16:00', 4),
    ('zeitfenster', '16:00 - 17:00', 5),
    ('zeitfenster', '17:00 - 18:00', 6);

-- ============================================================================
-- SEED DATA: DEFAULT ADMIN USER
-- ============================================================================
-- Password: 'admin123' (change immediately after first login!)
-- Hash generated with: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- ============================================================================
INSERT INTO users (username, password_hash, role) VALUES
    ('admin', '$2y$12$Irqjk28s4AkdJEaXcYL2DeKuGORJE6NKoj1eelFwqo/Pbw6pS3sGO', 'admin');

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check table creation
SELECT 'Tables created:' AS status;
SHOW TABLES;

-- Check option counts per section
SELECT
    section,
    COUNT(*) AS option_count
FROM option_definitions
GROUP BY section
ORDER BY section;

-- Total options
SELECT COUNT(*) AS total_options FROM option_definitions;

-- ============================================================================
-- HELPFUL QUERIES FOR DAILY USE
-- ============================================================================

/*
-- Get all active options for a section (for dropdowns):
SELECT label
FROM option_definitions
WHERE section = 'thema' AND is_active = TRUE
ORDER BY sort_order, label;

-- Count entries by topic:
SELECT
    value_text AS topic,
    COUNT(DISTINCT entry_id) AS count
FROM stats_entry_values
WHERE section = 'thema'
GROUP BY value_text
ORDER BY count DESC;

-- Get complete entry with all selections:
SELECT
    se.id,
    se.created_at,
    u.username,
    GROUP_CONCAT(CASE WHEN sev.section = 'kontaktart' THEN sev.value_text END SEPARATOR '; ') AS kontaktart,
    GROUP_CONCAT(CASE WHEN sev.section = 'person' THEN sev.value_text END SEPARATOR '; ') AS person,
    GROUP_CONCAT(CASE WHEN sev.section = 'thema' THEN sev.value_text END SEPARATOR '; ') AS themen,
    GROUP_CONCAT(CASE WHEN sev.section = 'zeitfenster' THEN sev.value_text END SEPARATOR '; ') AS zeitfenster,
    GROUP_CONCAT(CASE WHEN sev.section = 'referenz' THEN sev.value_text END SEPARATOR '; ') AS referenz,
    se.reference_remarks
FROM stats_entries se
JOIN users u ON se.user_id = u.id
LEFT JOIN stats_entry_values sev ON se.id = sev.entry_id
GROUP BY se.id, se.created_at, u.username, se.reference_remarks
ORDER BY se.created_at DESC;

-- Admin: Add new option:
INSERT INTO option_definitions (section, label, sort_order, is_active)
VALUES ('thema', 'Reisen', 100, TRUE);

-- Admin: Soft-delete option (preserves historical data):
UPDATE option_definitions
SET is_active = FALSE
WHERE section = 'thema' AND label = 'Alte Option';
*/

SELECT 'Schema creation complete!' AS status;
