<?php
declare(strict_types=1);

final class GameRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function gamesForTeamInWeek(int $teamId, int $poolWeekId): array
    {
        $st = $this->db->prepare(
            'SELECT g.*, th.city AS home_city, th.abbreviation AS home_abbr,
                    ta.city AS away_city, ta.abbreviation AS away_abbr
             FROM games g
             JOIN teams th ON th.id = g.home_team_id
             JOIN teams ta ON ta.id = g.away_team_id
             WHERE g.pool_week_id = ? AND (g.home_team_id = ? OR g.away_team_id = ?)
             AND g.status <> "cancelled"
             ORDER BY g.start_datetime ASC'
        );
        $st->execute([$poolWeekId, $teamId, $teamId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function gamesForWeek(int $poolWeekId): array
    {
        $st = $this->db->prepare(
            'SELECT g.*, th.city AS home_city, th.abbreviation AS home_abbr, th.id AS home_id,
                    ta.city AS away_city, ta.abbreviation AS away_abbr, ta.id AS away_id
             FROM games g
             JOIN teams th ON th.id = g.home_team_id
             JOIN teams ta ON ta.id = g.away_team_id
             WHERE g.pool_week_id = ? AND g.status <> "cancelled"
             ORDER BY g.start_datetime ASC'
        );
        $st->execute([$poolWeekId]);
        return $st->fetchAll();
    }

    public function deleteGamesForWeek(int $poolWeekId): int
    {
        $st = $this->db->prepare('DELETE FROM games WHERE pool_week_id = ?');
        $st->execute([$poolWeekId]);
        return $st->rowCount();
    }

    /**
     * Upsert games from API/mock rows.
     * Each row: external_game_id, game_date_local, start_datetime, home_team_id, away_team_id,
     * home_score?, away_score?, status, home_probable_pitcher?, away_probable_pitcher?,
     * home_pitcher_stats_line?, away_pitcher_stats_line?
     */
    public function upsertGames(int $poolWeekId, array $rows): int
    {
        $n = 0;
        $sql = 'INSERT INTO games (
            external_game_id, pool_week_id, game_date_local, start_datetime,
            home_team_id, away_team_id, home_score, away_score, status,
            home_probable_pitcher, away_probable_pitcher,
            home_pitcher_stats_line, away_pitcher_stats_line
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            game_date_local = VALUES(game_date_local),
            start_datetime = VALUES(start_datetime),
            home_team_id = VALUES(home_team_id),
            away_team_id = VALUES(away_team_id),
            home_score = VALUES(home_score),
            away_score = VALUES(away_score),
            status = VALUES(status),
            home_probable_pitcher = VALUES(home_probable_pitcher),
            away_probable_pitcher = VALUES(away_probable_pitcher),
            home_pitcher_stats_line = VALUES(home_pitcher_stats_line),
            away_pitcher_stats_line = VALUES(away_pitcher_stats_line)';
        $st = $this->db->prepare($sql);
        foreach ($rows as $r) {
            $st->execute([
                $r['external_game_id'],
                $poolWeekId,
                $r['game_date_local'],
                $r['start_datetime'],
                $r['home_team_id'],
                $r['away_team_id'],
                $r['home_score'] ?? null,
                $r['away_score'] ?? null,
                $r['status'],
                $r['home_probable_pitcher'] ?? null,
                $r['away_probable_pitcher'] ?? null,
                $r['home_pitcher_stats_line'] ?? null,
                $r['away_pitcher_stats_line'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * Games whose local calendar date is before today (APP_TIMEZONE) but status is not yet final
     * (or still in progress). After games finish, run Daily sync to pull scores from MLB.
     */
    public function countPastGamesNotFinal(): int
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM games
             WHERE game_date_local < ?
             AND status NOT IN (\'final\', \'cancelled\', \'postponed\')'
        );
        $st->execute([$today]);
        return (int) $st->fetchColumn();
    }
}
