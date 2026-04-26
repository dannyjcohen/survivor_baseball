<?php
declare(strict_types=1);

/**
 * MLB Stats API (https://statsapi.mlb.com/api/v1/) — schedule, scores, probable pitchers.
 *
 * Docs / exploration: append resource to base, e.g. /schedule?sportId=1&startDate=…&endDate=…
 * TODO: If you need more fields (weather, delays), add hydrate= params to scheduleUrl().
 */
final class MlbApiClient
{
    /** @var array<string,int>|null */
    private ?array $mlbTeamIdToInternal = null;

    public function __construct(private PDO $db) {}

    /**
     * Full week schedule for a pool week (regular season only). Rows for GameRepository::upsertGames (no pool_week_id).
     *
     * @return list<array<string,mixed>>
     */
    public function fetchWeekSchedule(int $poolWeekId, WeekRepository $weekRepo): array
    {
        return $this->rowsForPoolWeek($poolWeekId, $weekRepo);
    }

    /**
     * Same payload as schedule; use to refresh scores/status without deleting rows first.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchWeekResults(int $poolWeekId, WeekRepository $weekRepo): array
    {
        return $this->rowsForPoolWeek($poolWeekId, $weekRepo);
    }

    /**
     * Same as results — schedule hydrate includes probable pitchers.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchWeekProbables(int $poolWeekId, WeekRepository $weekRepo): array
    {
        return $this->rowsForPoolWeek($poolWeekId, $weekRepo);
    }

    /**
     * Pull all games in [startYmd, endYmd] that fall inside a configured pool week.
     * Each row includes pool_week_id for grouping.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchScheduleRowsForDateRange(string $startYmd, string $endYmd, WeekRepository $weekRepo): array
    {
        $url = $this->scheduleUrl($startYmd, $endYmd);
        $json = $this->httpGetJson($url);
        return $this->parseScheduleToRows($json, $weekRepo);
    }

    /** @return array<string,mixed>|null */
    private function weekRow(int $poolWeekId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM pool_weeks WHERE id = ?');
        $st->execute([$poolWeekId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rowsForPoolWeek(int $poolWeekId, WeekRepository $weekRepo): array
    {
        $w = $this->weekRow($poolWeekId);
        if ($w === null) {
            return [];
        }
        $start = (string) $w['week_start_local'];
        $end = (string) $w['week_end_local'];
        $rows = $this->fetchScheduleRowsForDateRange($start, $end, $weekRepo);
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['pool_week_id'] !== $poolWeekId) {
                continue;
            }
            unset($r['pool_week_id']);
            $out[] = $r;
        }
        return $out;
    }

    private function scheduleUrl(string $startYmd, string $endYmd): string
    {
        $q = http_build_query([
            'sportId' => 1,
            'startDate' => $startYmd,
            'endDate' => $endYmd,
            'gameType' => 'R',
            'hydrate' => 'probablePitcher(note),team',
        ]);
        return MLB_STATS_API_BASE . '/schedule?' . $q;
    }

    private function httpGetJson(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 45,
                'header' => "User-Agent: SurvivorPool/1.0 (statsapi.mlb.com)\r\nAccept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Failed to fetch MLB Stats API: ' . $url);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON from MLB Stats API');
        }
        return $data;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseScheduleToRows(array $json, WeekRepository $weekRepo): array
    {
        $dates = $json['dates'] ?? [];
        $out = [];
        foreach ($dates as $d) {
            $games = $d['games'] ?? [];
            foreach ($games as $g) {
                $row = $this->mapGameToRow($g, $weekRepo);
                if ($row !== null) {
                    $out[] = $row;
                }
            }
        }
        return $this->enrichRowsWithPitchingStats($out);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function mapGameToRow(array $game, WeekRepository $weekRepo): ?array
    {
        if (($game['gameType'] ?? '') !== 'R') {
            return null;
        }
        $official = (string) ($game['officialDate'] ?? '');
        if ($official === '') {
            return null;
        }
        $pw = $weekRepo->findByLocalDate($official);
        if ($pw === null) {
            return null;
        }
        $wid = (int) $pw['id'];
        $gamePk = (string) ($game['gamePk'] ?? '');
        if ($gamePk === '') {
            return null;
        }
        $homeMlb = (int) ($game['teams']['home']['team']['id'] ?? 0);
        $awayMlb = (int) ($game['teams']['away']['team']['id'] ?? 0);
        $homeId = $this->resolveInternalTeamId($homeMlb);
        $awayId = $this->resolveInternalTeamId($awayMlb);
        if ($homeId === null || $awayId === null) {
            return null;
        }
        $status = $this->mapStatus($game);
        $gameDateUtc = (string) ($game['gameDate'] ?? '');
        $startLocal = $this->utcIsoToLocalDatetime($gameDateUtc);
        $hs = null;
        $as = null;
        if ($status === 'final' || $status === 'in_progress') {
            if (isset($game['teams']['home']['score'])) {
                $hs = (int) $game['teams']['home']['score'];
            }
            if (isset($game['teams']['away']['score'])) {
                $as = (int) $game['teams']['away']['score'];
            }
        }
        $hp = $game['teams']['home']['probablePitcher'] ?? null;
        $ap = $game['teams']['away']['probablePitcher'] ?? null;
        $homeProb = $this->pitcherName($hp);
        $awayProb = $this->pitcherName($ap);
        $homeProbId = $this->pitcherId($hp);
        $awayProbId = $this->pitcherId($ap);
        $homeRec = $this->parseLeagueRecordFromGameSide($game['teams']['home'] ?? []);
        $awayRec = $this->parseLeagueRecordFromGameSide($game['teams']['away'] ?? []);
        return [
            'pool_week_id' => $wid,
            'external_game_id' => $gamePk,
            'game_date_local' => $official,
            'start_datetime' => $startLocal,
            'home_team_id' => $homeId,
            'away_team_id' => $awayId,
            'home_score' => $hs,
            'away_score' => $as,
            'status' => $status,
            'home_probable_pitcher' => $homeProb,
            'away_probable_pitcher' => $awayProb,
            'home_probable_pitcher_id' => $homeProbId,
            'away_probable_pitcher_id' => $awayProbId,
            'home_season_wins' => $homeRec['wins'],
            'home_season_losses' => $homeRec['losses'],
            'away_season_wins' => $awayRec['wins'],
            'away_season_losses' => $awayRec['losses'],
        ];
    }

    /**
     * @param array<string,mixed> $side teams.home or teams.away from schedule JSON
     * @return array{wins:?int, losses:?int}
     */
    private function parseLeagueRecordFromGameSide(array $side): array
    {
        $lr = $side['leagueRecord'] ?? null;
        if (!is_array($lr)) {
            return ['wins' => null, 'losses' => null];
        }
        $w = $lr['wins'] ?? null;
        $l = $lr['losses'] ?? null;
        return [
            'wins' => is_numeric($w) ? (int) $w : null,
            'losses' => is_numeric($l) ? (int) $l : null,
        ];
    }

    /**
     * Batch-fetch season pitching W–L + ERA for schedule rows (stripped before DB upsert).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function enrichRowsWithPitchingStats(array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if (!empty($r['home_probable_pitcher_id'])) {
                $ids[] = (int) $r['home_probable_pitcher_id'];
            }
            if (!empty($r['away_probable_pitcher_id'])) {
                $ids[] = (int) $r['away_probable_pitcher_id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $lines = $ids === [] ? [] : $this->fetchPitchingSeasonLinesByIds($ids);
        foreach ($rows as &$r) {
            $hid = (int) ($r['home_probable_pitcher_id'] ?? 0);
            $aid = (int) ($r['away_probable_pitcher_id'] ?? 0);
            $r['home_pitcher_stats_line'] = $hid > 0 ? ($lines[$hid] ?? null) : null;
            $r['away_pitcher_stats_line'] = $aid > 0 ? ($lines[$aid] ?? null) : null;
            unset($r['home_probable_pitcher_id'], $r['away_probable_pitcher_id']);
        }
        unset($r);
        return $rows;
    }

    /**
     * @param list<int> $ids MLB person IDs
     * @return array<int, string> id => e.g. "5-2, 2.15 ERA"
     */
    private function fetchPitchingSeasonLinesByIds(array $ids): array
    {
        $season = MLB_SEASON_YEAR;
        $out = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $idsStr = implode(',', $chunk);
            $hydrate = 'stats(group=[pitching],type=[season],season=' . $season . ')';
            $url = MLB_STATS_API_BASE . '/people?personIds=' . $idsStr . '&hydrate=' . rawurlencode($hydrate);
            try {
                $json = $this->httpGetJson($url);
            } catch (Throwable $e) {
                continue;
            }
            foreach ($json['people'] ?? [] as $person) {
                $pid = (int) ($person['id'] ?? 0);
                if ($pid === 0) {
                    continue;
                }
                $line = $this->parsePitchingSeasonLineFromPerson($person);
                if ($line !== null) {
                    $out[$pid] = $line;
                }
            }
        }
        foreach ($ids as $mid) {
            if (isset($out[$mid])) {
                continue;
            }
            $fallback = $this->fetchPitchingSeasonLineSingle($mid, $season);
            if ($fallback !== null) {
                $out[$mid] = $fallback;
            }
        }
        return $out;
    }

    private function fetchPitchingSeasonLineSingle(int $playerId, int $season): ?string
    {
        $q = http_build_query([
            'stats' => 'season',
            'group' => 'pitching',
            'season' => $season,
        ]);
        try {
            $json = $this->httpGetJson(MLB_STATS_API_BASE . '/people/' . $playerId . '/stats?' . $q);
        } catch (Throwable $e) {
            return null;
        }
        foreach ($json['stats'] ?? [] as $block) {
            foreach ($block['splits'] ?? [] as $sp) {
                $stat = $sp['stat'] ?? null;
                if (is_array($stat)) {
                    return $this->formatPitchingStatLine($stat);
                }
            }
        }
        return null;
    }

    private function parsePitchingSeasonLineFromPerson(array $person): ?string
    {
        foreach ($person['stats'] ?? [] as $block) {
            $g = $block['group'] ?? [];
            if (($g['displayName'] ?? '') !== 'pitching') {
                continue;
            }
            foreach ($block['splits'] ?? [] as $sp) {
                $gt = (string) ($sp['gameType'] ?? 'R');
                if ($gt !== '' && $gt !== 'R') {
                    continue;
                }
                $stat = $sp['stat'] ?? null;
                if (is_array($stat)) {
                    return $this->formatPitchingStatLine($stat);
                }
            }
        }
        return null;
    }

    /** @param array<string,mixed> $stat MLB stat object */
    private function formatPitchingStatLine(array $stat): string
    {
        $w = (int) ($stat['wins'] ?? 0);
        $l = (int) ($stat['losses'] ?? 0);
        $era = trim((string) ($stat['era'] ?? ''));
        if ($era === '') {
            $era = '—';
        }
        return sprintf('%d-%d, %s ERA', $w, $l, $era);
    }

    private function pitcherName(?array $prob): ?string
    {
        if ($prob === null) {
            return null;
        }
        $name = $prob['fullName'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    private function pitcherId(?array $prob): ?int
    {
        if ($prob === null) {
            return null;
        }
        if (!isset($prob['id'])) {
            return null;
        }
        $id = (int) $prob['id'];
        return $id > 0 ? $id : null;
    }

    private function mapStatus(array $game): string
    {
        $s = $game['status'] ?? [];
        $detailed = (string) ($s['detailedState'] ?? '');
        if (stripos($detailed, 'Postpon') !== false) {
            return 'postponed';
        }
        if (stripos($detailed, 'Cancel') !== false) {
            return 'cancelled';
        }
        $abs = (string) ($s['abstractGameState'] ?? '');
        if ($abs === 'Final' || $abs === 'Game Over') {
            return 'final';
        }
        if ($abs === 'Live') {
            return 'in_progress';
        }
        return 'scheduled';
    }

    private function utcIsoToLocalDatetime(string $isoUtc): string
    {
        if ($isoUtc === '') {
            return '';
        }
        try {
            $utc = new DateTimeImmutable($isoUtc);
            $tz = new DateTimeZone(APP_TIMEZONE);
            return $utc->setTimezone($tz)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function resolveInternalTeamId(int $mlbTeamId): ?int
    {
        $map = $this->mlbTeamMap();
        return $map[(string) $mlbTeamId] ?? null;
    }

    /** @return array<string,int> */
    private function mlbTeamMap(): array
    {
        if ($this->mlbTeamIdToInternal !== null) {
            return $this->mlbTeamIdToInternal;
        }
        $st = $this->db->query('SELECT id, mlb_id FROM teams');
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(string) (int) $r['mlb_id']] = (int) $r['id'];
        }
        $this->mlbTeamIdToInternal = $map;
        return $map;
    }
}
