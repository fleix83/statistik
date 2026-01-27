-- ============================================================================
-- HELPDESK STATISTIK - COMPLETE DATABASE SETUP
-- ============================================================================
-- Run this file to create ALL tables needed for production.
-- Adjust database name if needed (default: statistik)
-- ============================================================================

-- Uncomment and adjust if you need to create the database:
-- CREATE DATABASE IF NOT EXISTS statistik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE statistik;

-- ============================================================================
-- DROP EXISTING TABLES (for clean install - comment out if upgrading)
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stats_entry_values;
DROP TABLE IF EXISTS stats_entries;
DROP TABLE IF EXISTS option_definitions_draft;
DROP TABLE IF EXISTS option_definitions;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS publish_state;
DROP TABLE IF EXISTS saved_periods;
DROP TABLE IF EXISTS chart_markers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- TABLE 1: USERS
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
-- TABLE 2: AUTH_TOKENS (Database-backed authentication)
-- ============================================================================
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 3: OPTION_DEFINITIONS
-- ============================================================================
CREATE TABLE option_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section ENUM('kontaktart','person','thema','zeitfenster','tageszeit','dauer','referenz') NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    keywords JSON DEFAULT NULL COMMENT 'JSON array of keywords for Thema options',
    param_group VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_section_label (section, label),
    INDEX idx_section_active (section, is_active),
    INDEX idx_section_sort (section, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 4: OPTION_DEFINITIONS_DRAFT (Editor staging)
-- ============================================================================
CREATE TABLE option_definitions_draft (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT NULL COMMENT 'NULL for new options, references existing for updates/deletes',
    section ENUM('kontaktart','person','thema','zeitfenster','tageszeit','dauer','referenz') NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    keywords JSON DEFAULT NULL,
    param_group VARCHAR(50) DEFAULT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (original_id) REFERENCES option_definitions(id) ON DELETE CASCADE,
    INDEX idx_draft_section (section),
    INDEX idx_draft_original (original_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 5: PUBLISH_STATE (Draft/Publish workflow)
-- ============================================================================
CREATE TABLE publish_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    has_pending_changes BOOLEAN DEFAULT FALSE,
    last_published_at DATETIME NULL,
    last_published_by INT NULL,
    FOREIGN KEY (last_published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 6: STATS_ENTRIES
-- ============================================================================
CREATE TABLE stats_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reference_remarks TEXT NULL COMMENT 'Freetext for Andere Bem etc.',
    notes TEXT NULL COMMENT 'General notes',
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 7: STATS_ENTRY_VALUES (Junction table for multi-select)
-- ============================================================================
CREATE TABLE stats_entry_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT NOT NULL,
    section ENUM('kontaktart','person','thema','zeitfenster','tageszeit','dauer','referenz') NOT NULL,
    value_text VARCHAR(100) NOT NULL COMMENT 'Actual text value',
    CONSTRAINT fk_entry FOREIGN KEY (entry_id) REFERENCES stats_entries(id) ON DELETE CASCADE,
    UNIQUE KEY uk_entry_section_value (entry_id, section, value_text),
    INDEX idx_entry_section (entry_id, section),
    INDEX idx_section_value (section, value_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 8: SAVED_PERIODS (Analytics dashboard)
-- ============================================================================
CREATE TABLE saved_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    periods JSON NOT NULL COMMENT 'Array of period objects',
    is_active BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 9: CHART_MARKERS (Analytics annotations)
-- ============================================================================
CREATE TABLE chart_markers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    color VARCHAR(20) DEFAULT '#3b82f6',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dates (start_date, end_date),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA: Initialize publish_state
-- ============================================================================
INSERT INTO publish_state (has_pending_changes, last_published_at, last_published_by)
VALUES (FALSE, NULL, NULL);

-- ============================================================================
-- SEED DATA: DEFAULT ADMIN USER
-- ============================================================================
-- Password: 'admin123' (CHANGE IMMEDIATELY after first login!)
INSERT INTO users (username, password_hash, role) VALUES
    ('admin', '$2y$12$Irqjk28s4AkdJEaXcYL2DeKuGORJE6NKoj1eelFwqo/Pbw6pS3sGO', 'admin');

-- ============================================================================
-- SEED DATA: OPTION_DEFINITIONS
-- ============================================================================

-- Kontaktart (Contact Method)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('kontaktart', 'Besuch', 0, 'contact'),
    ('kontaktart', 'Telefon', 1, 'contact'),
    ('kontaktart', 'Mail', 2, 'contact'),
    ('kontaktart', 'Passant/in', 3, 'contact');

-- Person (Demographics)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('person', 'Mann', 0, 'gender'),
    ('person', 'Frau', 1, 'gender'),
    ('person', 'unter 55', 2, 'age'),
    ('person', 'über 55', 3, 'age'),
    ('person', 'über 80', 4, 'age'),
    ('person', 'selbst betroffen', 5, 'affected'),
    ('person', 'Angehörige Nachbarn und andere', 6, 'affected'),
    ('person', 'Institution', 7, 'affected');

-- Tageszeit (Time of Day)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('tageszeit', 'Vormittag', 0, 'day_time'),
    ('tageszeit', 'Nachmittag', 1, 'day_time');

-- Dauer (Duration)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('dauer', 'länger als 20 Minuten', 0, 'duration');

-- Thema (Topics with Keywords)
INSERT INTO option_definitions (section, label, sort_order, param_group, keywords) VALUES
    ('thema', 'Bildung', 0, 'topic', '[\"Information, Beratung\",\"Frühförderung\",\"Schulen\",\"Schulunterstützende Angebote\",\"Brücken - und Integrationsangebote\",\"Lehrstellen, Berufsschulen\",\"Erwachsenenbildung, Elternbildung\",\"Sprachschule\"]'),
    ('thema', 'Arbeit', 1, 'topic', '[\"Information, Beratung\",\"Berufsberatung, Neuorientierung\",\"Arbeitslosigkeit\",\"Soloeinsätze, Au Pair, Ferienjobs\",\"Freiwilligenarbeit\",\"Stellensuche\",\"Firmengründung\",\"Integrationsprogramme, geschützte Arbeitsplätze\"]'),
    ('thema', 'Migrationshintergrund', 2, 'topic', NULL),
    ('thema', 'Finanzen', 3, 'topic', '[\"Information, Budget- und Schuldenberatung\",\"Unterstützung, soziale Beiträge\",\"Fonds, Stiftungen\",\"Steuern, Pensionskasse\",\"Stipenden\",\"Günstig einkaufen, essen\"]'),
    ('thema', 'Frauen Männer jung und alt', 4, 'topic', '[\"Information, Beratung\",\"Gleichstellung\",\"Familie\",\"Familienergänzende Kinderbetreuung\",\"Kinder\",\"Jugendliche\",\"Ältere Menschen\"]'),
    ('thema', 'Gesundheit', 5, 'topic', '[\"Information Gesundheit\",\"Information Behinderung\",\"Information Sucht\",\"Arzt, Therapie\",\"Klinische Angebote\",\"Pflege, Unterstützung zu Hause\",\"Soziale Angebote\",\"Hilfsmittel und Fahrdienste\",\"Sterben und Tod\"]'),
    ('thema', 'Wohnen', 6, 'topic', '[]'),
    ('thema', 'Austausch und Freizeit', 7, 'topic', '[\"Quartierangebote\",\"Treffpunkte\",\"Religionsgemeinschaften\",\"Selbsthilfe\",\"Freiwilligenarbeit\",\"Freizeitaktivitäten\",\"Tiere\"]'),
    ('thema', 'Migration und Integration', 8, 'topic', '[\"Information, Beratung\",\"Zuzug, Aufenthalt, Bewilligung, Auswandern\",\"Sprache, Bildung\",\"Vereine und Organisationen der Migrationsbevölkerung\",\"Flüchtlinge\"]'),
    ('thema', 'Notlagen', 9, 'topic', '[\"Notdienste\",\"Gewalt und Krisen\",\"Finanzielle Nothilfe\",\"Notwohnungen, Notunterkünfte\"]'),
    ('thema', 'Allgemeine Hilfeleistungen', 10, 'topic', '[\"Schreibdienste, Übersetzungen\",\"Allgemeine Rechtsberatung\",\"Ombudsstellen, Mediation\",\"Computer und Administration\",\"Gegenseitige Unterstützung\",\"Begleitung und andere Angebote\",\"Informationsstellen und Behörden\"]'),
    ('thema', 'Recht', 11, 'topic', '[]');

-- Referenz (Referral Source)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('referenz', 'Flyer/Plakat/Presse', 0, 'referral'),
    ('referenz', 'Internet', 1, 'referral'),
    ('referenz', 'empfohlen Freunde Bekannte', 2, 'referral'),
    ('referenz', 'empfohlen Institution', 3, 'referral'),
    ('referenz', 'empfohlen Fachperson', 4, 'referral'),
    ('referenz', 'War schon mal hier', 5, 'referral'),
    ('referenz', 'Andere', 6, 'referral');

-- Zeitfenster (Time Slots)
INSERT INTO option_definitions (section, label, sort_order, param_group) VALUES
    ('zeitfenster', '11:00 - 11:30', 0, 'time_slot'),
    ('zeitfenster', '11:30 - 12:00', 1, 'time_slot'),
    ('zeitfenster', '12:00 - 13:00', 2, 'time_slot'),
    ('zeitfenster', '13:00 - 14:00', 3, 'time_slot'),
    ('zeitfenster', '14:00 - 15:00', 4, 'time_slot'),
    ('zeitfenster', '15:00 - 16:00', 5, 'time_slot'),
    ('zeitfenster', '16:00 - 17:00', 6, 'time_slot'),
    ('zeitfenster', '17:00 - 18:00', 7, 'time_slot');

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 'Setup complete! Tables created:' AS status;
SHOW TABLES;

SELECT section, COUNT(*) AS options FROM option_definitions GROUP BY section ORDER BY section;
