-- Rueckschau (lookback) configured fields.
-- Backs api/rueckschau/fields.php and api/rueckschau/data.php.
CREATE TABLE IF NOT EXISTS rueckschau_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(32) NOT NULL,
    value_text VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sort_order (sort_order)
);
