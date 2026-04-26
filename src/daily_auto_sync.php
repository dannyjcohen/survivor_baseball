<?php
declare(strict_types=1);

/**
 * Runs MLB yesterday+today sync at most once per calendar day (APP_TIMEZONE), on normal web requests.
 * Skipped when AUTO_DAILY_SYNC=0. Uses app_meta.daily_auto_sync_date + MySQL GET_LOCK.
 */
function survivor_auto_daily_sync_try(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!defined('AUTO_DAILY_SYNC_ENABLED') || !AUTO_DAILY_SYNC_ENABLED) {
        return;
    }

    try {
        $pdo = Database::pdo();
        $meta = new AppMetaRepository($pdo);
        $tz = new DateTimeZone(APP_TIMEZONE);
        $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        if ($meta->get('daily_auto_sync_date') === $today) {
            return;
        }

        $lockStmt = $pdo->query("SELECT GET_LOCK('survivor_daily_auto_sync', 30)");
        $got = $lockStmt ? $lockStmt->fetchColumn() : null;
        if ((int) $got !== 1) {
            return;
        }

        try {
            if ($meta->get('daily_auto_sync_date') === $today) {
                return;
            }

            $weekRepo = new WeekRepository($pdo);
            $gameRepo = new GameRepository($pdo);
            $teamRepo = new TeamRepository($pdo);
            $api = new MlbApiClient($pdo);
            $logRepo = new ApiSyncLogRepository($pdo);
            $runner = new DailySyncRunner($weekRepo, $gameRepo, $api, $logRepo, $teamRepo);
            $runner->syncYesterdayToday('daily_auto');
            $meta->set('daily_auto_sync_date', $today);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('survivor_daily_auto_sync')");
        }
    } catch (Throwable $e) {
        try {
            $pdo = Database::pdo();
            (new ApiSyncLogRepository($pdo))->log('daily_auto', 'error', $e->getMessage());
        } catch (Throwable $e2) {
        }
    }
}
