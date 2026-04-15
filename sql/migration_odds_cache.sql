-- Run once on existing DBs that already have schema without odds_cache
CREATE TABLE IF NOT EXISTS odds_cache (
  cache_key VARCHAR(128) NOT NULL PRIMARY KEY,
  payload LONGTEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
