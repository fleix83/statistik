-- ============================================================================
-- Add missing Zeitfenster option: 11:00 - 11:30
-- Run this script on production database
-- ============================================================================

INSERT INTO option_definitions (section, label, sort_order, param_group)
VALUES ('zeitfenster', '11:00 - 11:30', 0, 'time_slot');
