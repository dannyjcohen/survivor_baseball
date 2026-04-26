<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$shouldRun = isset($_GET['run']) && $_GET['run'] === '1';
if (!$shouldRun) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Pass run=1 to execute the sync.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!daily_sync_allowed()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing key. Set DAILY_SYNC_KEY in .env and pass ?key=…'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$gameRepo = new GameRepository($pdo);
$teamRepo = new TeamRepository($pdo);
$api = new MlbApiClient($pdo);
$logRepo = new ApiSyncLogRepository($pdo);
$syncRunner = new DailySyncRunner($weekRepo, $gameRepo, $api, $logRepo, $teamRepo);

try {
    $syncResult = $syncRunner->syncYesterdayToday('daily');
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'message' => 'Daily sync completed.',
        'range' => $syncResult['range'],
        'lines' => $syncResult['lines'],
        'total_rows_api' => $syncResult['total_rows_api'],
        'total_upserts' => $syncResult['total_upserts'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logRepo->log('daily', 'error', $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
