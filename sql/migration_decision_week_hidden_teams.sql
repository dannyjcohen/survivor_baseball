-- Shared “hidden from decision list” teams per pool week (Decision Helper).
-- Run once on existing databases.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS decision_week_hidden_teams (
  pool_week_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (pool_week_id, team_id),
  KEY idx_week (pool_week_id),
  CONSTRAINT fk_dwh_week FOREIGN KEY (pool_week_id) REFERENCES pool_weeks(id) ON DELETE CASCADE,
  CONSTRAINT fk_dwh_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
