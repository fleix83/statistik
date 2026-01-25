-- ============================================================================
-- Migration: Add param_group column and populate values
-- Date: 2026-01-25
-- ============================================================================
--
-- The param_group column enables hierarchical filtering:
-- - Parameters in the SAME group use OR logic (add up counts)
-- - Parameters in DIFFERENT groups use AND logic (filter results)
--
-- Groups:
-- - contact: Contact methods (kontaktart section)
-- - gender: Gender (person section: Mann, Frau)
-- - age: Age groups (person section: unter 55, über 55, über 80)
-- - background: Background (person section: Migrationshintergrund)
-- - affected: Affected status (person section: selbst betroffen, etc.)
-- - topic: Topics (thema section)
-- - time_slot: Time slots (zeitfenster section)
-- - day_time: Time of day (tageszeit section)
-- - duration: Duration (dauer section)
-- - referral: Referral sources (referenz section)
-- ============================================================================

-- Add param_group column to main table (if not exists)
ALTER TABLE option_definitions ADD COLUMN IF NOT EXISTS param_group VARCHAR(50);

-- Add param_group column to draft table (if not exists)
ALTER TABLE option_definitions_draft ADD COLUMN IF NOT EXISTS param_group VARCHAR(50);

-- Populate param_group values in option_definitions
UPDATE option_definitions SET param_group = 'contact' WHERE section = 'kontaktart';
UPDATE option_definitions SET param_group = 'gender' WHERE section = 'person' AND label IN ('Mann', 'Frau');
UPDATE option_definitions SET param_group = 'age' WHERE section = 'person' AND label IN ('unter 55', 'über 55', 'über 80');
UPDATE option_definitions SET param_group = 'background' WHERE section = 'person' AND label = 'Migrationshintergrund';
UPDATE option_definitions SET param_group = 'affected' WHERE section = 'person' AND label IN ('selbst betroffen', 'Angehörige Nachbarn und andere', 'Institution');
UPDATE option_definitions SET param_group = 'topic' WHERE section = 'thema';
UPDATE option_definitions SET param_group = 'time_slot' WHERE section = 'zeitfenster';
UPDATE option_definitions SET param_group = 'day_time' WHERE section = 'tageszeit';
UPDATE option_definitions SET param_group = 'duration' WHERE section = 'dauer';
UPDATE option_definitions SET param_group = 'referral' WHERE section = 'referenz';

-- Populate param_group values in option_definitions_draft
UPDATE option_definitions_draft SET param_group = 'contact' WHERE section = 'kontaktart';
UPDATE option_definitions_draft SET param_group = 'gender' WHERE section = 'person' AND label IN ('Mann', 'Frau');
UPDATE option_definitions_draft SET param_group = 'age' WHERE section = 'person' AND label IN ('unter 55', 'über 55', 'über 80');
UPDATE option_definitions_draft SET param_group = 'background' WHERE section = 'person' AND label = 'Migrationshintergrund';
UPDATE option_definitions_draft SET param_group = 'affected' WHERE section = 'person' AND label IN ('selbst betroffen', 'Angehörige Nachbarn und andere', 'Institution');
UPDATE option_definitions_draft SET param_group = 'topic' WHERE section = 'thema';
UPDATE option_definitions_draft SET param_group = 'time_slot' WHERE section = 'zeitfenster';
UPDATE option_definitions_draft SET param_group = 'day_time' WHERE section = 'tageszeit';
UPDATE option_definitions_draft SET param_group = 'duration' WHERE section = 'dauer';
UPDATE option_definitions_draft SET param_group = 'referral' WHERE section = 'referenz';
