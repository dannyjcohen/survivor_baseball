<?php
declare(strict_types=1);

final class WeekRepository
{
    public function __construct(private PDO $db) {}

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM pool_weeks WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Pool week containing local date (Y-m-d), or null */
    public function findByLocalDate(string $ymd): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM pool_weeks WHERE week_start_local <= ? AND week_end_local >= ? ORDER BY week_start_local LIMIT 1'
        );
        $st->execute([$ymd, $ymd]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function allOrdered(): array
    {
        $st = $this->db->query('SELECT * FROM pool_weeks ORDER BY week_start_local ASC');
        return $st->fetchAll();
    }

    /** Current pool week by “today” in app timezone */
    public function currentWeek(): ?array
    {
        $today = (new DateTimeImmutable('now'))->format('Y-m-d');
        return $this->findByLocalDate($today);
    }

    /** Week to show in UI when “today” is outside seeded range: next upcoming, else latest. */
    public function resolveWeekForUi(): ?array
    {
        $cur = $this->currentWeek();
        if ($cur !== null) {
            return $cur;
        }
        $all = $this->allOrdered();
        if ($all === []) {
            return null;
        }
        $today = (new DateTimeImmutable('now'))->format('Y-m-d');
        foreach ($all as $w) {
            if ($w['week_end_local'] >= $today) {
                return $w;
            }
        }
        return $all[count($all) - 1];
    }

    /** @return list<array<string,mixed>> */
    public function weeksBefore(int $weekId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM pool_weeks WHERE id < ? ORDER BY week_start_local ASC'
        );
        $st->execute([$weekId]);
        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function allBeforeId(int $weekId): array
    {
        return $this->weeksBefore($weekId);
    }

    public function updateStatus(int $weekId, string $status): void
    {
        $st = $this->db->prepare('UPDATE pool_weeks SET status = ? WHERE id = ?');
        $st->execute([$status, $weekId]);
    }
}
