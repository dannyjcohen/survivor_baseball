<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TeamRepository.php';
require_once __DIR__ . '/GameRepository.php';
require_once __DIR__ . '/PickRepository.php';
require_once __DIR__ . '/WeekRepository.php';
require_once __DIR__ . '/EntryRepository.php';
require_once __DIR__ . '/ApiSyncLogRepository.php';
require_once __DIR__ . '/AppMetaRepository.php';
require_once __DIR__ . '/OddsCacheRepository.php';
require_once __DIR__ . '/services/MlbApiClient.php';
require_once __DIR__ . '/services/DailySyncRunner.php';
require_once __DIR__ . '/daily_auto_sync.php';
require_once __DIR__ . '/services/OddsService.php';
require_once __DIR__ . '/services/ScheduleService.php';
require_once __DIR__ . '/services/SurvivorService.php';

survivor_auto_daily_sync_try();
