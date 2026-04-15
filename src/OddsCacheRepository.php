<?php
declare(strict_types=1);

final class OddsCacheRepository
{
    public function __construct(private PDO $db) {}

    /** @return array{payload:string,expires_at:string,updated_at:string}|null */
    public function get(string $cacheKey): ?array
    {
        $st = $this->db->prepare(
            'SELECT payload, expires_at, updated_at FROM odds_cache WHERE cache_key = ? LIMIT 1'
        );
        $st->execute([$cacheKey]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function set(string $cacheKey, string $jsonPayload, int $ttlSeconds): void
    {
        $now = new DateTimeImmutable('now');
        $exp = $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');
        $ts = $now->format('Y-m-d H:i:s');
        $st = $this->db->prepare(
            'INSERT INTO odds_cache (cache_key, payload, expires_at, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at), updated_at = VALUES(updated_at)'
        );
        $st->execute([$cacheKey, $jsonPayload, $exp, $ts]);
    }

    public function delete(string $cacheKey): void
    {
        $st = $this->db->prepare('DELETE FROM odds_cache WHERE cache_key = ?');
        $st->execute([$cacheKey]);
    }
}
