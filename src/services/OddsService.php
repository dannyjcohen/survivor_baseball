<?php
declare(strict_types=1);

/**
 * The Odds API — https://the-odds-api.com/ — MLB h2h (moneyline), cached 6h in DB.
 * Uses sport key baseball_mlb (see /v4/sports). Regions us, market h2h, American odds.
 */
final class OddsService
{
    private const CACHE_KEY = 'the_odds_api_mlb_h2h_us';

    public function __construct(
        private OddsCacheRepository $cacheRepo,
        private TeamRepository $teams
    ) {}

    /**
     * Force HTTP fetch, store cache, return decoded events list.
     *
     * @return list<array<string,mixed>>
     */
    public function refreshFromApi(): array
    {
        if (ODDS_API_KEY === '') {
            throw new RuntimeException('ODDS_API_KEY is not set in .env');
        }
        $url = $this->buildOddsUrl();
        $raw = $this->httpGet($url);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON from The Odds API');
        }
        $this->cacheRepo->set(self::CACHE_KEY, $raw, ODDS_CACHE_TTL_SECONDS);
        return $data;
    }

    /**
     * Return cached events if valid; otherwise fetch (when API key set).
     *
     * @return list<array<string,mixed>>
     */
    public function getEvents(bool $forceRefresh): array
    {
        if (ODDS_API_KEY === '') {
            return [];
        }
        if (!$forceRefresh) {
            $row = $this->cacheRepo->get(self::CACHE_KEY);
            if ($row !== null) {
                $exp = strtotime($row['expires_at']);
                if ($exp !== false && $exp > time()) {
                    $dec = json_decode($row['payload'], true);
                    return is_array($dec) ? $dec : [];
                }
            }
        }
        return $this->refreshFromApi();
    }

    /** @return array{expires_at:?string,updated_at:?string,valid:bool} */
    public function getCacheMeta(): array
    {
        $row = $this->cacheRepo->get(self::CACHE_KEY);
        if ($row === null) {
            return ['expires_at' => null, 'updated_at' => null, 'valid' => false];
        }
        $valid = strtotime($row['expires_at']) > time();
        return [
            'expires_at' => $row['expires_at'],
            'updated_at' => $row['updated_at'],
            'valid' => $valid,
        ];
    }

    /**
     * Lookup key: home_team_id|away_team_id|Y-m-d (local date from commence_time).
     * Value: display string + raw prices for template.
     *
     * @return array<string, array{line:string, home:int|null, away:int|null, book:string}>
     */
    public function buildLookupForWeek(array $weekRow, bool $forceRefresh): array
    {
        $events = $this->getEvents($forceRefresh);
        $teamRows = $this->teams->allOrdered();
        $byId = [];
        foreach ($teamRows as $t) {
            $byId[(int) $t['id']] = $t;
        }
        $lookup = [];
        foreach ($events as $ev) {
            $homeName = (string) ($ev['home_team'] ?? '');
            $awayName = (string) ($ev['away_team'] ?? '');
            $hid = $this->matchOddsNameToTeamId($homeName, $byId);
            $aid = $this->matchOddsNameToTeamId($awayName, $byId);
            if ($hid === null || $aid === null) {
                continue;
            }
            $commence = (string) ($ev['commence_time'] ?? '');
            if ($commence === '') {
                continue;
            }
            $localDate = $this->commenceToLocalDate($commence);
            if (!$this->dateInWeek($localDate, $weekRow)) {
                continue;
            }
            $h2h = $this->extractPreferredH2h($ev);
            if ($h2h === null) {
                continue;
            }
            $line = sprintf(
                'H2H: %s / %s (%s)',
                $this->fmtAmerican($h2h['home']),
                $this->fmtAmerican($h2h['away']),
                $h2h['book_label']
            );
            $k = $hid . '|' . $aid . '|' . $localDate;
            $lookup[$k] = [
                'line' => $line,
                'home' => $h2h['home'],
                'away' => $h2h['away'],
                'book' => $h2h['book_label'],
            ];
        }
        return $lookup;
    }

    /**
     * Find odds for a scheduled game row (home/away ids + local game date).
     *
     * @param array<string,array{line:string, home:int|null, away:int|null, book:string}> $lookup
     */
    public function findOddsForGame(
        array $lookup,
        int $homeTeamId,
        int $awayTeamId,
        string $gameDateLocal
    ): ?array {
        $base = new DateTimeImmutable($gameDateLocal);
        $dates = [
            $base->format('Y-m-d'),
            $base->modify('-1 day')->format('Y-m-d'),
            $base->modify('+1 day')->format('Y-m-d'),
        ];
        foreach ($dates as $d) {
            $k = $homeTeamId . '|' . $awayTeamId . '|' . $d;
            if (isset($lookup[$k])) {
                return $lookup[$k];
            }
        }
        return null;
    }

    private function buildOddsUrl(): string
    {
        return ODDS_API_BASE . '/sports/' . rawurlencode(ODDS_SPORT_KEY) . '/odds?' . http_build_query([
            'regions' => 'us',
            'markets' => 'h2h',
            'oddsFormat' => 'american',
            'apiKey' => ODDS_API_KEY,
        ]);
    }

    private function httpGet(string $url): string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 45,
                'header' => "User-Agent: SurvivorPool/1.0 (the-odds-api.com)\r\nAccept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Failed to fetch The Odds API: ' . $url);
        }
        return $raw;
    }

    private function commenceToLocalDate(string $isoUtc): string
    {
        try {
            $dt = new DateTimeImmutable($isoUtc);
            return $dt->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('Y-m-d');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function dateInWeek(string $ymd, array $weekRow): bool
    {
        return $ymd >= $weekRow['week_start_local'] && $ymd <= $weekRow['week_end_local'];
    }

    /**
     * @param array<int,array<string,mixed>> $teamsById
     */
    private function matchOddsNameToTeamId(string $oddsName, array $teamsById): ?int
    {
        $n = self::norm($oddsName);
        foreach ($teamsById as $id => $t) {
            $full = self::norm($t['city'] . ' ' . $t['name']);
            if ($n === $full) {
                return (int) $id;
            }
        }
        foreach ($teamsById as $id => $t) {
            if ($n === self::norm($t['name'])) {
                return (int) $id;
            }
        }
        $aliases = [
            'athletics' => ['Oakland Athletics', 'Athletics'],
            'yankees' => ['New York Yankees'],
        ];
        foreach ($aliases as $needle => $candidates) {
            if (str_contains($n, $needle)) {
                foreach ($candidates as $cand) {
                    foreach ($teamsById as $id => $t) {
                        $full = self::norm($t['city'] . ' ' . $t['name']);
                        if ($full === self::norm($cand)) {
                            return (int) $id;
                        }
                    }
                }
            }
        }
        return null;
    }

    private static function norm(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        return mb_strtolower($s);
    }

    /**
     * @return array{home:int|null, away:int|null, book_key:string, book_label:string}|null
     */
    private function extractPreferredH2h(array $event): ?array
    {
        $homeName = (string) ($event['home_team'] ?? '');
        $awayName = (string) ($event['away_team'] ?? '');
        $books = $event['bookmakers'] ?? [];
        $labels = [
            'draftkings' => 'DK',
            'fanduel' => 'FD',
            'betmgm' => 'MGM',
            'bovada' => 'BV',
            'williamhill_us' => 'WH',
        ];
        foreach ($labels as $want => $short) {
            foreach ($books as $b) {
                if (($b['key'] ?? '') !== $want) {
                    continue;
                }
                $line = $this->h2hFromBookmaker($b, $homeName, $awayName);
                if ($line !== null) {
                    $line['book_label'] = $short;
                    return $line;
                }
            }
        }
        foreach ($books as $b) {
            $line = $this->h2hFromBookmaker($b, $homeName, $awayName);
            if ($line !== null) {
                $k = (string) ($b['key'] ?? 'bk');
                $line['book_label'] = strtoupper(substr($k, 0, 3));
                return $line;
            }
        }
        return null;
    }

    /**
     * @return array{home:int|null, away:int|null, book_key:string, book_label:string}|null
     */
    private function h2hFromBookmaker(array $bookmaker, string $homeTeamName, string $awayTeamName): ?array
    {
        $markets = $bookmaker['markets'] ?? [];
        foreach ($markets as $m) {
            if (($m['key'] ?? '') !== 'h2h') {
                continue;
            }
            $outcomes = $m['outcomes'] ?? [];
            $homePrice = null;
            $awayPrice = null;
            foreach ($outcomes as $o) {
                $name = (string) ($o['name'] ?? '');
                $price = $o['price'] ?? null;
                if ($price === null || $name === '') {
                    continue;
                }
                if (self::norm($name) === self::norm($homeTeamName)) {
                    $homePrice = (int) $price;
                }
                if (self::norm($name) === self::norm($awayTeamName)) {
                    $awayPrice = (int) $price;
                }
            }
            if ($homePrice !== null || $awayPrice !== null) {
                return [
                    'home' => $homePrice,
                    'away' => $awayPrice,
                    'book_key' => (string) ($bookmaker['key'] ?? ''),
                    'book_label' => '',
                ];
            }
        }
        return null;
    }

    private function fmtAmerican(?int $p): string
    {
        if ($p === null) {
            return '—';
        }
        return $p > 0 ? '+' . $p : (string) $p;
    }
}
