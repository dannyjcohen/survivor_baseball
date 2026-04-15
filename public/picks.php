<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$pickRepo = new PickRepository($pdo);
$gameRepo = new GameRepository($pdo);
$entryRepo = new EntryRepository($pdo);
$teamRepo = new TeamRepository($pdo);
$survivor = new SurvivorService($gameRepo, $pickRepo, $weekRepo);

$weeks = $weekRepo->allOrdered();
$requestedWeekId = isset($_GET['week']) ? (int) $_GET['week'] : null;
$week = null;
if ($requestedWeekId !== null) {
    $week = $weekRepo->findById($requestedWeekId);
}
if ($week === null) {
    $week = $weekRepo->resolveWeekForUi();
}

$entries = $entryRepo->all();
$teams = $teamRepo->allOrdered();

$forbiddenByEntry = [];
if ($week !== null) {
    foreach ($entries as $e) {
        $forbiddenByEntry[(string) (int) $e['id']] = $pickRepo->usedTeamIdsExcludingWeek(
            (int) $e['id'],
            (int) $week['id']
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('err', 'Invalid session token. Try again.');
        redirect('picks.php' . ($week ? '?week=' . (int) $week['id'] : ''));
    }
    $wid = (int) ($_POST['pool_week_id'] ?? 0);
    $weekPost = $weekRepo->findById($wid);
    if ($weekPost === null) {
        flash('err', 'Invalid week.');
        redirect('picks.php');
    }
    $ok = true;
    foreach ($entries as $e) {
        $eid = (int) $e['id'];
        if (!$survivor->canEditPick($eid, $weekPost)) {
            continue;
        }
        $field = 'team_' . $eid;
        $tid = isset($_POST[$field]) ? (int) $_POST[$field] : 0;
        if ($tid <= 0) {
            flash('err', 'Select a team for ' . $e['label'] . '.');
            $ok = false;
            break;
        }
        $teamRow = $teamRepo->findById($tid);
        if ($teamRow === null) {
            flash('err', 'Invalid team for ' . $e['label'] . '.');
            $ok = false;
            break;
        }
        $forbidden = $pickRepo->usedTeamIdsExcludingWeek($eid, $wid);
        if (in_array($tid, $forbidden, true)) {
            flash('err', $e['label'] . ' cannot reuse a team already picked in a prior week.');
            $ok = false;
            break;
        }
    }
    if ($ok) {
        foreach ($entries as $e) {
            $eid = (int) $e['id'];
            if (!$survivor->canEditPick($eid, $weekPost)) {
                continue;
            }
            $tid = (int) ($_POST['team_' . $eid] ?? 0);
            if ($tid > 0) {
                $pickRepo->savePick($eid, $wid, $tid);
            }
        }
        flash('ok', 'Picks saved.');
    }
    redirect('picks.php?week=' . $wid);
}

$picksData = [];
$usedByEntry = [];
foreach ($entries as $e) {
    $eid = (int) $e['id'];
    $usedByEntry[$eid] = $pickRepo->usedTeamIdsList($eid);
    $picksData[$eid] = $week ? $pickRepo->findForEntryWeek($eid, (int) $week['id']) : null;
}

$title = 'Picks';
$active = 'picks';
$flash_ok = flash('ok');
$flash_err = flash('err');
$template_body = dirname(__DIR__) . '/templates/picks_content.php';
include dirname(__DIR__) . '/templates/layout.php';
