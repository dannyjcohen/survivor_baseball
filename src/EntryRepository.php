<?php
declare(strict_types=1);

final class EntryRepository
{
    public function __construct(private PDO $db) {}

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $st = $this->db->query('SELECT id, label FROM entries ORDER BY id');
        return $st->fetchAll();
    }
}
