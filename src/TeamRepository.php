<?php
declare(strict_types=1);

final class TeamRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function allOrdered(): array
    {
        $st = $this->db->query(
            'SELECT id, mlb_id, city, name, abbreviation, league, division
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
}
