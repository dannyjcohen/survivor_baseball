<?php
declare(strict_types=1);

final class DecisionHiddenTeamRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<int> */
    public function teamIdsForWeek(int $poolWeekId): array
    {
        $st = $this->db->prepare(
            'SELECT team_id FROM decision_week_hidden_teams WHERE pool_week_id = ? ORDER BY team_id ASC'
        );
        $st->execute([$poolWeekId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function add(int $poolWeekId, int $teamId): void
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->db->prepare(
            'INSERT INTO decision_week_hidden_teams (pool_week_id, team_id, created_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)'
        );
        $st->execute([$poolWeekId, $teamId, $now]);
    }

    public function remove(int $poolWeekId, int $teamId): void
    {
        $st = $this->db->prepare(
            'DELETE FROM decision_week_hidden_teams WHERE pool_week_id = ? AND team_id = ?'
        );
        $st->execute([$poolWeekId, $teamId]);
    }
}
