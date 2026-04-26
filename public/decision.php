<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$pickRepo = new PickRepository($pdo);
$gameRepo = new GameRepository($pdo);
$teamRepo = new TeamRepository($pdo);
$entryRepo = new EntryRepository($pdo);
$decisionHiddenRepo = new DecisionHiddenTeamRepository($pdo);
$schedule = new ScheduleService($gameRepo, $teamRepo);
$survivor = new SurvivorService($gameRepo, $pickRepo, $weekRepo);
$entries = $entryRepo->all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sortPost = $_POST['sort'] ?? 'alpha';
    if (!in_array($sortPost, ['alpha', 'games_desc', 'home_desc', 'ease_desc'], true)) {
        $sortPost = 'alpha';
    }
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('err', 'Invalid session token. Try again.');
        $wid = (int) ($_POST['pool_week_id'] ?? 0);
        redirect('decision.php' . ($wid > 0 ? '?week=' . $wid . '&sort=' . urlencode($sortPost) : ''));
    }
    $wid = (int) ($_POST['pool_week_id'] ?? 0);
    $weekPost = $weekRepo->findById($wid);
    if ($weekPost === null) {
        flash('err', 'Invalid week.');
        redirect('decision.php');
    }
    $decisionAction = (string) ($_POST['decision_action'] ?? '');
    if ($decisionAction !== '') {
        if (!in_array($decisionAction, ['hide_team', 'show_team'], true)) {
            flash('err', 'Invalid action.');
            redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
        }
        $teamAct = (int) ($_POST['team_id'] ?? 0);
        if ($teamAct <= 0) {
            flash('err', 'Invalid team.');
            redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
        }
        if ($teamRepo->findById($teamAct) === null) {
            flash('err', 'Invalid team.');
            redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
        }
        if ($decisionAction === 'hide_team') {
            foreach ($entries as $e) {
                $p = $pickRepo->findForEntryWeek((int) $e['id'], $wid);
                if ($p !== null && (int) $p['team_id'] === $teamAct) {
                    flash('err', 'Cannot hide a team you already picked this week. Clear the pick first.');
                    redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
                }
            }
            $decisionHiddenRepo->add($wid, $teamAct);
            flash('ok', 'Team hidden for this week.');
        } else {
            $decisionHiddenRepo->remove($wid, $teamAct);
            flash('ok', 'Team restored to the list.');
        }
        redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
    }
    $pickAction = $_POST['pick_action'] ?? 'save';
    $rowEntry = (int) ($_POST['row_pick_entry'] ?? 0);
    if ($pickAction === 'clear') {
        if ($rowEntry <= 0) {
            flash('err', 'Invalid request.');
            redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
        }
        $validEntry = false;
        foreach ($entries as $e) {
            if ((int) $e['id'] === $rowEntry) {
                $validEntry = true;
                break;
            }
        }
        if (!$validEntry) {
            flash('err', 'Invalid entry.');
            redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
        }
        if (!$survivor->canEditPick($rowEntry, $weekPost)) {
            flash('err', 'That entry cannot be changed for this week.');
        } else {
            $pickRepo->deletePick($rowEntry, $wid);
            flash('ok', 'Pick cleared.');
        }
        redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
    }
    $rowTeam = (int) ($_POST['row_pick_team'] ?? 0);
    if ($rowEntry <= 0 || $rowTeam <= 0) {
        flash('err', 'Invalid pick request.');
        redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
    }
    $validEntry = false;
    foreach ($entries as $e) {
        if ((int) $e['id'] === $rowEntry) {
            $validEntry = true;
            break;
        }
    }
    if (!$validEntry) {
        flash('err', 'Invalid entry.');
        redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
    }
    if (!$survivor->canEditPick($rowEntry, $weekPost)) {
        flash('err', 'That entry cannot be changed for this week.');
    } else {
        $teamRow = $teamRepo->findById($rowTeam);
        if ($teamRow === null) {
            flash('err', 'Invalid team.');
        } else {
            $forbidden = $pickRepo->usedTeamIdsExcludingWeek($rowEntry, $wid);
            $cur = $pickRepo->findForEntryWeek($rowEntry, $wid);
            $isCurrent = $cur !== null && (int) $cur['team_id'] === $rowTeam;
            if (in_array($rowTeam, $forbidden, true) && !$isCurrent) {
                flash('err', 'That team cannot be used for this entry (already used in a prior week).');
            } else {
                $pickRepo->savePick($rowEntry, $wid, $rowTeam);
                flash('ok', 'Pick saved.');
            }
        }
    }
    redirect('decision.php?week=' . $wid . '&sort=' . urlencode($sortPost));
}

$requestedWeekId = isset($_GET['week']) ? (int) $_GET['week'] : null;
$week = null;
if ($requestedWeekId !== null) {
    $week = $weekRepo->findById($requestedWeekId);
}
if ($week === null) {
    $week = $weekRepo->resolveWeekForUi();
}

$sort = $_GET['sort'] ?? 'alpha';
if (!in_array($sort, ['alpha', 'games_desc', 'home_desc', 'ease_desc'], true)) {
    $sort = 'alpha';
}

$used1 = isset($entries[0]) ? $pickRepo->usedTeamIdsList((int) $entries[0]['id']) : [];
$used2 = isset($entries[1]) ? $pickRepo->usedTeamIdsList((int) $entries[1]['id']) : [];

$forbiddenByEntry = [];
$picksData = [];
if ($week !== null) {
    foreach ($entries as $e) {
        $eid = (int) $e['id'];
        $forbiddenByEntry[$eid] = $pickRepo->usedTeamIdsExcludingWeek($eid, (int) $week['id']);
        $picksData[$eid] = $pickRepo->findForEntryWeek($eid, (int) $week['id']);
    }
}

$grid = [];
$cols = [];
$oddsLookup = [];
$oddsSvc = null;
$oddsErr = null;
$oddsCacheNote = null;
/** @var list<array{team_id:int,label:string}> */
$hiddenTeamsPanel = [];
if ($week !== null) {
    $grid = $schedule->buildTeamWeekGrid((int) $week['id'], $week, $used1, $used2);
    $grid = $schedule->sortGrid($grid, $sort);
    foreach ($grid as &$gRow) {
        $gRow['scenario'] = $survivor->analyzePick(0, $week, ['team_id' => (int) $gRow['team_id']], true);
    }
    unset($gRow);
    $grid = decision_grid_pinned_picks_first($grid, $entries, $picksData);
    $hiddenTeamIds = $decisionHiddenRepo->teamIdsForWeek((int) $week['id']);
    $pickTeamIds = [];
    foreach ($picksData as $p) {
        if ($p !== null) {
            $pickTeamIds[] = (int) $p['team_id'];
        }
    }
    $grid = array_values(array_filter($grid, static function (array $row) use ($hiddenTeamIds, $pickTeamIds): bool {
        $tid = (int) $row['team_id'];
        if (in_array($tid, $pickTeamIds, true)) {
            return true;
        }
        return !in_array($tid, $hiddenTeamIds, true);
    }));
    foreach ($hiddenTeamIds as $hid) {
        $tr = $teamRepo->findById($hid);
        if ($tr !== null) {
            $hiddenTeamsPanel[] = [
                'team_id' => $hid,
                'label' => (string) $tr['abbreviation'] . ' · ' . (string) $tr['city'],
            ];
        }
    }
    usort($hiddenTeamsPanel, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
    $cols = $schedule->weekDayColumns($week);
    if (ODDS_API_KEY !== '') {
        try {
            $cacheRepo = new OddsCacheRepository($pdo);
            $oddsSvc = new OddsService($cacheRepo, $teamRepo);
            $meta = $oddsSvc->getCacheMeta();
            $oddsCacheNote = $meta;
            $oddsLookup = $oddsSvc->buildLookupForWeek($week, false);
        } catch (Throwable $e) {
            $oddsErr = $e->getMessage();
        }
    }
}

$weeks = $weekRepo->allOrdered();
/** Pool week containing “today” — vivid row colors only when the viewed week matches this. */
$weekUiCurrent = $weekRepo->resolveWeekForUi();

$staleGamesCount = $gameRepo->countPastGamesNotFinal();

$title = 'Decision Helper';
$active = 'decision';
$flash_ok = flash('ok');
$flash_err = flash('err');
$extra_scripts = '<script src="' . h(app_url('js/app.js')) . '"></script>';
$template_body = dirname(__DIR__) . '/templates/decision_content.php';
include dirname(__DIR__) . '/templates/layout.php';
