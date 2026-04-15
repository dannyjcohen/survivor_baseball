<?php
declare(strict_types=1);

final class ApiSyncLogRepository
{
    public function __construct(private PDO $db) {}

    public function log(string $syncType, string $status, ?string $message = null): void
    {
        $st = $this->db->prepare(
            'INSERT INTO api_sync_log (sync_type, status, message, created_at) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$syncType, $status, $message, date('Y-m-d H:i:s')]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 30): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM api_sync_log ORDER BY id DESC LIMIT ?'
        );
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
