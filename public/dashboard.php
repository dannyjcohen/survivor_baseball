<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$pickRepo = new PickRepository($pdo);
$gameRepo = new GameRepository($pdo);
$entryRepo = new EntryRepository($pdo);
$survivor = new SurvivorService($gameRepo, $pickRepo, $weekRepo);

$week = $weekRepo->resolveWeekForUi();
$entries = $entryRepo->all();
$cards = [];

foreach ($entries as $e) {
    $eid = (int) $e['id'];
    $pick = $week ? $pickRepo->findForEntryWeek($eid, (int) $week['id']) : null;
    $analysis = $week ? $survivor->analyzePick($eid, $week, $pick) : null;
    $locked = $week ? !$survivor->canEditPick($eid, $week) : true;
    $elimBefore = $week ? $survivor->isEntryEliminatedBeforeWeek($eid, (int) $week['id']) : false;
    $upcoming = [];
    if ($pick !== null && $week !== null) {
        $games = $gameRepo->gamesForTeamInWeek((int) $pick['team_id'], (int) $week['id']);
        foreach ($games as $g) {
            if (($g['status'] ?? '') !== 'final') {
                $upcoming[] = $g;
            }
        }
    }
    $cards[] = [
        'entry' => $e,
        'pick' => $pick,
        'analysis' => $analysis,
        'locked' => $locked,
        'elim_before' => $elimBefore,
        'upcoming' => $upcoming,
    ];
}

$staleGamesCount = $gameRepo->countPastGamesNotFinal();

$title = 'Dashboard';
$active = 'dashboard';
$flash_ok = flash('ok');
$flash_err = flash('err');
$template_body = dirname(__DIR__) . '/templates/dashboard_content.php';
include dirname(__DIR__) . '/templates/layout.php';
