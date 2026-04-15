-- MLB Survivor Pool — schema
-- MySQL 8+ compatible

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS odds_cache;
DROP TABLE IF EXISTS app_meta;
DROP TABLE IF EXISTS api_sync_log;
DROP TABLE IF EXISTS picks;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS pool_weeks;
DROP TABLE IF EXISTS entries;
DROP TABLE IF EXISTS teams;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mlb_id VARCHAR(32) NOT NULL,
  city VARCHAR(64) NOT NULL,
  name VARCHAR(64) NOT NULL,
  abbreviation VARCHAR(5) NOT NULL,
  league ENUM('AL','NL') NOT NULL,
  division VARCHAR(10) NOT NULL,
  UNIQUE KEY uq_mlb_id (mlb_id),
  UNIQUE KEY uq_abbr (abbreviation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pool_weeks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_number INT UNSIGNED NOT NULL,
  week_start_local DATE NOT NULL,
  week_end_local DATE NOT NULL,
  week_label VARCHAR(64) NOT NULL,
  deadline_datetime DATETIME NOT NULL COMMENT 'Reference time (e.g. first game); app does not enforce pick lock by deadline',
  status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
  UNIQUE KEY uq_week_start (week_start_local),
  KEY idx_week_number (week_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE picks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_id INT UNSIGNED NOT NULL,
  pool_week_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_entry_week (entry_id, pool_week_id),
  KEY idx_week (pool_week_id),
  KEY idx_team (team_id),
  CONSTRAINT fk_picks_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_picks_week FOREIGN KEY (pool_week_id) REFERENCES pool_weeks(id) ON DELETE CASCADE,
  CONSTRAINT fk_picks_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_game_id VARCHAR(64) NOT NULL,
  pool_week_id INT UNSIGNED NOT NULL,
  game_date_local DATE NOT NULL,
  start_datetime DATETIME NOT NULL,
  home_team_id INT UNSIGNED NOT NULL,
  away_team_id INT UNSIGNED NOT NULL,
  home_score SMALLINT NULL,
  away_score SMALLINT NULL,
  status ENUM('scheduled','in_progress','final','postponed','cancelled') NOT NULL DEFAULT 'scheduled',
  home_probable_pitcher VARCHAR(128) NULL,
  away_probable_pitcher VARCHAR(128) NULL,
  home_pitcher_stats_line VARCHAR(96) NULL COMMENT 'Season W-L, ERA from MLB Stats API',
  away_pitcher_stats_line VARCHAR(96) NULL,
  UNIQUE KEY uq_external_game (external_game_id),
  KEY idx_week (pool_week_id),
  KEY idx_home (home_team_id),
  KEY idx_away (away_team_id),
  KEY idx_game_date (game_date_local),
  CONSTRAINT fk_games_week FOREIGN KEY (pool_week_id) REFERENCES pool_weeks(id) ON DELETE CASCADE,
  CONSTRAINT fk_games_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE RESTRICT,
  CONSTRAINT fk_games_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_sync_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sync_type VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL,
  message TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_meta (
  meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
  meta_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE odds_cache (
  cache_key VARCHAR(128) NOT NULL PRIMARY KEY,
  payload LONGTEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
