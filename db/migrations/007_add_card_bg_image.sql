-- Migration 007: Custom background image per card
-- Path (relative to api/) of an uploaded image replacing the default card texture

ALTER TABLE card_colors
    ADD COLUMN bg_image VARCHAR(255) DEFAULT NULL AFTER swatch_checked;
