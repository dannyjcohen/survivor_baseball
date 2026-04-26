<?php
declare(strict_types=1);

final class TeamRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function allOrdered(): array
    {
        $st = $this->db->query(
            'SELECT id, mlb_id, city, name, abbreviation, league, division, season_wins, season_losses
             FROM teams ORDER BY city, name'
        );
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM teams WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> id => row */
    public function mapById(): array
    {
        $rows = $this->allOrdered();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r;
        }
        return $map;
    }

    /**
     * Merge season W–L from schedule rows (leagueRecord per side). Uses the newest game row per team
     * (by local date + start time) when multiple games appear in one batch.
     *
     * @param list<array<string,mixed>> $rows Rows from MlbApiClient (may include home_season_wins, etc.)
     */
    public function refreshSeasonRecordsFromGameRows(array $rows): void
    {
        $best = [];
        foreach ($rows as $r) {
            $sortKey = (string) ($r['game_date_local'] ?? '') . "\0" . (string) ($r['start_datetime'] ?? '');
            foreach (
                [
                    [(int) ($r['home_team_id'] ?? 0), $r['home_season_wins'] ?? null, $r['home_season_losses'] ?? null],
                    [(int) ($r['away_team_id'] ?? 0), $r['away_season_wins'] ?? null, $r['away_season_losses'] ?? null],
                ] as [$tid, $w, $l]
            ) {
                if ($tid <= 0 || $w === null || $l === null) {
                    continue;
                }
                $prev = $best[$tid] ?? null;
                if ($prev === null || $sortKey > $prev['sort_key']) {
                    $best[$tid] = [
                        'sort_key' => $sortKey,
                        'wins' => (int) $w,
                        'losses' => (int) $l,
                    ];
                }
            }
        }
        if ($best === []) {
            return;
        }
        $st = $this->db->prepare('UPDATE teams SET season_wins = ?, season_losses = ? WHERE id = ?');
        foreach ($best as $tid => $rec) {
            $st->execute([$rec['wins'], $rec['losses'], $tid]);
        }
    }
}
