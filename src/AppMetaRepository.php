<?php
declare(strict_types=1);

final class AppMetaRepository
{
    public function __construct(private PDO $db) {}

    public function get(string $key): ?string
    {
        $st = $this->db->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function set(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->db->prepare(
            'INSERT INTO app_meta (meta_key, meta_value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)'
        );
        $st->execute([$key, $value, $now]);
    }
}
