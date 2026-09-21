-- Migration 006: Background image opacity for card colors
-- Per-card control over the card background texture opacity (NULL = CSS default 0.07)

ALTER TABLE card_colors
    ADD COLUMN bg_image_opacity DECIMAL(4,3) DEFAULT NULL AFTER swatch_checked;
