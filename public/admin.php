<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$gameRepo = new GameRepository($pdo);
$teamRepo = new TeamRepository($pdo);
$api = new MlbApiClient($pdo);
$logRepo = new ApiSyncLogRepository($pdo);

$weeks = $weekRepo->allOrdered();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('err', 'Invalid session token.');
        redirect('admin.php');
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'odds_refresh') {
            if (ODDS_API_KEY === '') {
                flash('err', 'Set ODDS_API_KEY in .env first.');
                redirect('admin.php');
            }
            $cacheRepo = new OddsCacheRepository($pdo);
            $oddsSvc = new OddsService($cacheRepo, $teamRepo);
            $oddsSvc->refreshFromApi();
            $logRepo->log('odds', 'ok', 'The Odds API cache refreshed (manual).');
            $h = max(1, (int) (ODDS_CACHE_TTL_SECONDS / 3600));
            flash('ok', "Odds cache refreshed (cached {$h}h).");
            redirect('admin.php');
        }
        if ($action === 'schedule_full') {
            $allWeeks = $weekRepo->allOrdered();
            $totalGames = 0;
            $weekCount = 0;
            $details = [];
            foreach ($allWeeks as $w) {
                $wid = (int) $w['id'];
                $deleted = $gameRepo->deleteGamesForWeek($wid);
                $rows = $api->fetchWeekSchedule($wid, $weekRepo);
                $n = $gameRepo->upsertGames($wid, $rows);
                $teamRepo->refreshSeasonRecordsFromGameRows($rows);
                $totalGames += $n;
                $weekCount++;
                $details[] = $w['week_label'] . ": {$n} games (removed {$deleted} old)";
            }
            $msg = implode('; ', $details);
            $logRepo->log('schedule_full', 'ok', $msg);
            flash('ok', "Full schedule import: {$totalGames} games across {$weekCount} pool week(s).");
            redirect('admin.php');
        }
        $wid = (int) ($_POST['pool_week_id'] ?? 0);
        $week = $weekRepo->findById($wid);
        if ($week === null) {
            flash('err', 'Invalid week.');
            redirect('admin.php');
        }
        if ($action === 'schedule') {
            $deleted = $gameRepo->deleteGamesForWeek($wid);
            $rows = $api->fetchWeekSchedule($wid, $weekRepo);
            $n = $gameRepo->upsertGames($wid, $rows);
            $teamRepo->refreshSeasonRecordsFromGameRows($rows);
            $logRepo->log('schedule', 'ok', "Removed $deleted prior rows; upserted $n games for week $wid (MLB Stats API).");
            flash('ok', "Schedule refreshed ($n games).");
        } elseif ($action === 'results') {
            $rows = $api->fetchWeekResults($wid, $weekRepo);
            $n = $gameRepo->upsertGames($wid, $rows);
            $teamRepo->refreshSeasonRecordsFromGameRows($rows);
            $logRepo->log('results', 'ok', "Upserted $n games with scores for week $wid (MLB Stats API).");
            flash('ok', "Scores/results refreshed ($n rows).");
        } elseif ($action === 'probables') {
            $rows = $api->fetchWeekProbables($wid, $weekRepo);
            $n = $gameRepo->upsertGames($wid, $rows);
            $teamRepo->refreshSeasonRecordsFromGameRows($rows);
            $logRepo->log('probables', 'ok', "Upserted $n games with probables for week $wid.");
            flash('ok', "Probable pitchers refreshed ($n rows).");
        } else {
            flash('err', 'Unknown action.');
        }
    } catch (Throwable $e) {
        $logRepo->log($action ?: 'unknown', 'error', $e->getMessage());
        flash('err', 'Error: ' . $e->getMessage());
    }
    redirect('admin.php');
}

$logs = $logRepo->recent(25);

$oddsMeta = ['expires_at' => null, 'updated_at' => null, 'valid' => false];
if (ODDS_API_KEY !== '') {
    $cacheRepo = new OddsCacheRepository($pdo);
    $oddsSvc = new OddsService($cacheRepo, $teamRepo);
    $oddsMeta = $oddsSvc->getCacheMeta();
}

$title = 'Admin / Data';
$active = 'admin';
$flash_ok = flash('ok');
$flash_err = flash('err');
$template_body = dirname(__DIR__) . '/templates/admin_content.php';
include dirname(__DIR__) . '/templates/layout.php';
