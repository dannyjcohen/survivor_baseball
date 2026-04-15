<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$gameRepo = new GameRepository($pdo);
$api = new MlbApiClient($pdo);
$logRepo = new ApiSyncLogRepository($pdo);
$syncRunner = new DailySyncRunner($weekRepo, $gameRepo, $api, $logRepo);
$metaRepo = new AppMetaRepository($pdo);

$syncResult = null;
$syncError = null;

$shouldRun = (isset($_GET['run']) && $_GET['run'] === '1')
    || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'sync');

if ($shouldRun) {
    if (!daily_sync_allowed()) {
        http_response_code(403);
        $syncError = 'Invalid or missing key. Set DAILY_SYNC_KEY in .env and pass ?key=… or use the form.';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !csrf_verify($_POST['csrf'] ?? null)) {
        $syncError = 'Invalid session token. Try again.';
    } else {
        try {
            $syncResult = $syncRunner->syncYesterdayToday('daily');
        } catch (Throwable $e) {
            $syncError = $e->getMessage();
            $logRepo->log('daily', 'error', $e->getMessage());
        }
    }
}

try {
    $autoDailySyncDate = $metaRepo->get('daily_auto_sync_date');
} catch (Throwable $e) {
    $autoDailySyncDate = null;
}

/** GET link that runs sync when DAILY_SYNC_KEY is empty; otherwise use form or bookmark with key. */
$syncRunUrlOpen = app_url('daily.php?run=1');

$title = 'Daily sync';
$active = 'daily';
$flash_ok = null;
$flash_err = null;
$template_body = dirname(__DIR__) . '/templates/daily_content.php';
include dirname(__DIR__) . '/templates/layout.php';
