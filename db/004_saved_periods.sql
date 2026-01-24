-- ============================================================================
-- Migration: Saved Periods
-- ============================================================================
-- Allows users to save, load, and manage time period configurations
-- in the Analytics dashboard.
-- ============================================================================

CREATE TABLE saved_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    periods JSON NOT NULL COMMENT 'Array of period objects: [{start, end, label, isComparison}, ...]',
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Currently loaded configuration (only one can be active)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
