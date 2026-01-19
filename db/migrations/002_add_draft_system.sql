-- ============================================================================
-- Migration 002: Add Draft/Publish System for Editor
-- ============================================================================
-- Adds:
-- 1. keywords JSON column to option_definitions
-- 2. option_definitions_draft table for staging changes
-- 3. publish_state table for tracking publish status
-- ============================================================================

-- Add keywords column to option_definitions
ALTER TABLE option_definitions
ADD COLUMN keywords JSON DEFAULT NULL
COMMENT 'JSON array of keywords for Thema options';

-- Create draft table for staging changes before publish
CREATE TABLE option_definitions_draft (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT NULL COMMENT 'NULL for new options, references existing option for updates/deletes',
    section ENUM('kontaktart','person','thema','zeitfenster','tageszeit','dauer','referenz') NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    keywords JSON DEFAULT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (original_id) REFERENCES option_definitions(id) ON DELETE CASCADE,
    INDEX idx_draft_section (section),
    INDEX idx_draft_original (original_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create publish state tracking table
CREATE TABLE publish_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    has_pending_changes BOOLEAN DEFAULT FALSE,
    last_published_at DATETIME NULL,
    last_published_by INT NULL,

    FOREIGN KEY (last_published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize publish_state with a single row
INSERT INTO publish_state (has_pending_changes, last_published_at, last_published_by)
VALUES (FALSE, NULL, NULL);
