-- Run once on existing databases (adds automatic daily sync state table).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_meta (
  meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
  meta_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
