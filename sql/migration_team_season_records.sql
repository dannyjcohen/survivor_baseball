-- Season W–L per team (from MLB schedule leagueRecord on each sync)
ALTER TABLE teams
  ADD COLUMN season_wins INT UNSIGNED NULL COMMENT 'MLB regular-season wins snapshot' AFTER division,
  ADD COLUMN season_losses INT UNSIGNED NULL COMMENT 'MLB regular-season losses snapshot' AFTER season_wins;
