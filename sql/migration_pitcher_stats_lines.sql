-- Add cached pitcher season lines (W–L, ERA) populated on schedule/probables sync
ALTER TABLE games
  ADD COLUMN home_pitcher_stats_line VARCHAR(96) NULL COMMENT 'Season W-L, ERA from MLB Stats API' AFTER away_probable_pitcher,
  ADD COLUMN away_pitcher_stats_line VARCHAR(96) NULL AFTER home_pitcher_stats_line;
