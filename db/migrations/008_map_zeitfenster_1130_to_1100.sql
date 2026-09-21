-- ============================================================================
-- Migration: map legacy Zeitfenster value "11:30 - 12:00" to "11:00 - 12.00"
-- Date: 2026-09-14
-- ============================================================================
--
-- The option was renamed in the Editor, but entry values are stored as text
-- and were never touched, so 1018 entries carried the old label and could no
-- longer be selected in the analytics filters. Idempotent: safe to run twice.
-- Run on production via phpMyAdmin (no migration runner).

-- 1) An entry that already has the new value must not end up with a duplicate
--    row (unique key entry_id + section + value_text): drop its old row.
DELETE old FROM stats_entry_values old
JOIN stats_entry_values new
  ON new.entry_id = old.entry_id
 AND new.section = 'zeitfenster'
 AND new.value_text = '11:00 - 12.00'
WHERE old.section = 'zeitfenster'
  AND old.value_text = '11:30 - 12:00';

-- 2) Rename the remaining rows.
UPDATE stats_entry_values
SET value_text = '11:00 - 12.00'
WHERE section = 'zeitfenster'
  AND value_text = '11:30 - 12:00';
