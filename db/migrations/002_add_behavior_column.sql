-- Migration: Add behavior column to option_definitions
-- Date: 2026-01-29
-- Description: Adds behavior column for subtract_only parameters (Migrationshintergrund, Dauer)

-- Add behavior column
ALTER TABLE option_definitions
ADD COLUMN behavior ENUM('standard', 'subtract_only') DEFAULT 'standard';

-- Mark subtract_only groups
UPDATE option_definitions SET behavior = 'subtract_only'
WHERE param_group IN ('background', 'duration');

-- Verify
SELECT section, label, param_group, behavior
FROM option_definitions
WHERE behavior = 'subtract_only';
