<?php
declare(strict_types=1);

final class PickRepository
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed>|null */
    public function findForEntryWeek(int $entryId, int $poolWeekId): ?array
    {
        $st = $this->db->prepare(
            'SELECT p.*, t.city, t.name AS team_name, t.abbreviation
             FROM picks p
             JOIN teams t ON t.id = p.team_id
             WHERE p.entry_id = ? AND p.pool_week_id = ?'
        );
        $st->execute([$entryId, $poolWeekId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array<int,int> team_id => latest pool_week_id used */
    public function usedTeamIdsForEntry(int $entryId): array
    {
        $st = $this->db->prepare(
            'SELECT team_id, MAX(pool_week_id) AS mw FROM picks WHERE entry_id = ? GROUP BY team_id'
        );
        $st->execute([$entryId]);
        $map = [];
        while ($row = $st->fetch()) {
            $map[(int) $row['team_id']] = (int) $row['mw'];
        }
        return $map;
    }

    /** @return list<int> */
    public function usedTeamIdsList(int $entryId): array
    {
        $st = $this->db->prepare(
            'SELECT DISTINCT team_id FROM picks WHERE entry_id = ? ORDER BY pool_week_id ASC'
        );
        $st->execute([$entryId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Used teams for an entry excluding one week (for pick validation). @return list<int> */
    public function usedTeamIdsExcludingWeek(int $entryId, int $excludePoolWeekId): array
    {
        $st = $this->db->prepare(
            'SELECT DISTINCT team_id FROM picks WHERE entry_id = ? AND pool_week_id <> ? ORDER BY pool_week_id ASC'
        );
        $st->execute([$entryId, $excludePoolWeekId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function savePick(int $entryId, int $poolWeekId, int $teamId): void
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->db->prepare(
            'INSERT INTO picks (entry_id, pool_week_id, team_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), updated_at = VALUES(updated_at)'
        );
        $st->execute([$entryId, $poolWeekId, $teamId, $now, $now]);
    }

    public function deletePick(int $entryId, int $poolWeekId): void
    {
        $st = $this->db->prepare('DELETE FROM picks WHERE entry_id = ? AND pool_week_id = ?');
        $st->execute([$entryId, $poolWeekId]);
    }

    /** @return list<array<string,mixed>> */
    public function allForWeek(int $poolWeekId): array
    {
        $st = $this->db->prepare(
            'SELECT p.*, t.city, t.name AS team_name, t.abbreviation, e.label AS entry_label
             FROM picks p
             JOIN teams t ON t.id = p.team_id
             JOIN entries e ON e.id = p.entry_id
             WHERE p.pool_week_id = ? ORDER BY p.entry_id'
        );
        $st->execute([$poolWeekId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function allHistory(): array
    {
        $st = $this->db->query(
            'SELECT p.*, pw.week_number, pw.week_label, pw.week_start_local, pw.week_end_local, pw.status AS week_status,
                    t.city, t.name AS team_name, t.abbreviation, e.label AS entry_label
             FROM picks p
             JOIN pool_weeks pw ON pw.id = p.pool_week_id
             JOIN teams t ON t.id = p.team_id
             JOIN entries e ON e.id = p.entry_id
             ORDER BY pw.week_start_local, p.entry_id'
        );
        return $st->fetchAll();
    }
}
