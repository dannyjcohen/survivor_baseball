<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = Database::pdo();
$weekRepo = new WeekRepository($pdo);
$pickRepo = new PickRepository($pdo);
$gameRepo = new GameRepository($pdo);
$entryRepo = new EntryRepository($pdo);
$survivor = new SurvivorService($gameRepo, $pickRepo, $weekRepo);

$weeks = $weekRepo->allOrdered();
$entries = $entryRepo->all();
$histRows = [];
foreach ($weeks as $w) {
    $hasAny = false;
    $cells = [];
    foreach ($entries as $e) {
        $eid = (int) $e['id'];
        $pick = $pickRepo->findForEntryWeek($eid, (int) $w['id']);
        if ($pick !== null) {
            $hasAny = true;
        }
        $cells[$eid] = [
            'pick' => $pick,
            'analysis' => $survivor->analyzePick($eid, $w, $pick),
        ];
    }
    if ($hasAny) {
        $histRows[] = ['week' => $w, 'cells' => $cells];
    }
}

$title = 'History';
$active = 'history';
$flash_ok = flash('ok');
$flash_err = flash('err');
$template_body = dirname(__DIR__) . '/templates/history_content.php';
include dirname(__DIR__) . '/templates/layout.php';
