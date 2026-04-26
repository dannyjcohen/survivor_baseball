<?php
declare(strict_types=1);

final class DailySyncRunner
{
    public function __construct(
        private WeekRepository $weekRepo,
        private GameRepository $gameRepo,
        private MlbApiClient $api,
        private ApiSyncLogRepository $logRepo,
        private TeamRepository $teamRepo,
    ) {}

    /**
     * @return array{range: string, lines: list<string>, total_rows_api: int, total_upserts: int}
     */
    public function syncYesterdayToday(string $syncLogType = 'daily'): array
    {
        $tz = new DateTimeZone(APP_TIMEZONE);
        $now = new DateTimeImmutable('now', $tz);
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $today = $now->format('Y-m-d');
        $rows = $this->api->fetchScheduleRowsForDateRange($yesterday, $today, $this->weekRepo);
        $this->teamRepo->refreshSeasonRecordsFromGameRows($rows);
        $byWeek = [];
        foreach ($rows as $r) {
            $wid = (int) $r['pool_week_id'];
            unset($r['pool_week_id']);
            $byWeek[$wid][] = $r;
        }
        $lines = [];
        $totalUpserts = 0;
        foreach ($byWeek as $wid => $list) {
            $n = $this->gameRepo->upsertGames($wid, $list);
            $totalUpserts += $n;
            $lines[] = "Pool week $wid: $n games upserted";
        }
        if ($rows === []) {
            $lines[] = 'No games returned for ' . $yesterday . '–' . $today . ' (off-season or outside seeded pool_weeks).';
        }
        $msg = implode('; ', $lines);
        $this->logRepo->log($syncLogType, 'ok', $msg);
        return [
            'range' => $yesterday . ' → ' . $today,
            'lines' => $lines,
            'total_rows_api' => count($rows),
            'total_upserts' => $totalUpserts,
        ];
    }
}
