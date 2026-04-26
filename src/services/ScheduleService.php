<?php
declare(strict_types=1);

/**
 * Schedule grid + week helpers.
 * TODO: Replace mock-driven rows with real MLB API payloads in MlbApiClient.
 */
final class ScheduleService
{
    public function __construct(
        private GameRepository $games,
        private TeamRepository $teams
    ) {}

    /**
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
     *   Keys Mon..Sun with Y-m-d
     */
    public function weekDayColumns(array $weekRow): array
    {
        $start = new DateTimeImmutable($weekRow['week_start_local'] . ' 00:00:00');
        $out = [];
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->modify('+' . $i . ' days');
            $out[] = [$labels[$i], $d->format('Y-m-d'), $d->format('M j')];
        }
        return $out;
    }

    /**
     * @param list<int> $used1
     * @param list<int> $used2
     * @return list<array<string,mixed>>
     */
    public function buildTeamWeekGrid(int $poolWeekId, array $weekRow, array $used1, array $used2): array
    {
        $teamMap = $this->teams->mapById();
        $allGames = $this->games->gamesForWeek($poolWeekId);
        $cols = $this->weekDayColumns($weekRow);
        $dates = array_column($cols, 1);

        $byTeamDate = [];
        foreach ($allGames as $g) {
            foreach (['home_team_id', 'away_team_id'] as $k) {
                $tid = (int) $g[$k];
                $d = $g['game_date_local'];
                $byTeamDate[$tid][$d][] = $g;
            }
        }

        $rows = [];
        foreach ($teamMap as $tid => $t) {
            $homeCount = 0;
            $gameCount = 0;
            $cells = [];
            foreach ($dates as $d) {
                $cellGames = $byTeamDate[$tid][$d] ?? [];
                $cells[$d] = $cellGames;
                foreach ($cellGames as $cg) {
                    $gameCount++;
                    if ((int) $cg['home_team_id'] === $tid) {
                        $homeCount++;
                    }
                }
            }
            $rows[] = [
                'team' => $t,
                'team_id' => $tid,
                'cells' => $cells,
                'stats' => [
                    'games' => $gameCount,
                    'home' => $homeCount,
                    'away' => $gameCount - $homeCount,
                    'ease' => $this->placeholderEase($homeCount, $gameCount),
                ],
                'opponent_week' => $this->summarizeOpponentWeek($tid, $cells, $teamMap),
                'used_entry1' => in_array($tid, $used1, true),
                'used_entry2' => in_array($tid, $used2, true),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['team']['city'] . $a['team']['name'], $b['team']['city'] . $b['team']['name']));

        return $rows;
    }

    private function placeholderEase(int $homeGames, int $totalGames): float
    {
        if ($totalGames === 0) {
            return 0.0;
        }
        return round(($homeGames / $totalGames) * 0.5 + 0.25, 3);
    }

    /**
     * @param array<string, list<array<string,mixed>>> $cells date (Y-m-d) => games
     * @param array<int, array<string,mixed>> $teamMap
     * @return array{
     *   combined_w:?int,
     *   combined_l:?int,
     *   games:int,
     *   games_with_opp_record:int,
     *   matchups:list<array{abbr:string, w:?int, l:?int, at:bool}>
     * }
     */
    private function summarizeOpponentWeek(int $teamId, array $cells, array $teamMap): array
    {
        $combinedW = 0;
        $combinedL = 0;
        $gamesWithOppRec = 0;
        $games = 0;
        $matchups = [];
        foreach ($cells as $cellGames) {
            foreach ($cellGames as $g) {
                $games++;
                $isHome = (int) $g['home_team_id'] === $teamId;
                $oid = $isHome ? (int) $g['away_team_id'] : (int) $g['home_team_id'];
                $abbr = $isHome ? (string) ($g['away_abbr'] ?? '') : (string) ($g['home_abbr'] ?? '');
                $ot = $teamMap[$oid] ?? null;
                $w = null;
                $l = null;
                if (
                    $ot !== null
                    && isset($ot['season_wins'], $ot['season_losses'])
                    && $ot['season_wins'] !== null
                    && $ot['season_losses'] !== null
                ) {
                    $w = (int) $ot['season_wins'];
                    $l = (int) $ot['season_losses'];
                    $combinedW += $w;
                    $combinedL += $l;
                    $gamesWithOppRec++;
                }
                $matchups[] = ['abbr' => $abbr, 'w' => $w, 'l' => $l, 'at' => !$isHome];
            }
        }
        return [
            'combined_w' => $gamesWithOppRec > 0 ? $combinedW : null,
            'combined_l' => $gamesWithOppRec > 0 ? $combinedL : null,
            'games' => $games,
            'games_with_opp_record' => $gamesWithOppRec,
            'matchups' => $matchups,
        ];
    }

    /**
     * @param list<array<string,mixed>> $gridRows from buildTeamWeekGrid
     * @return list<array<string,mixed>>
     */
    public function sortGrid(array $gridRows, string $sort): array
    {
        $rows = $gridRows;
        switch ($sort) {
            case 'games_desc':
                usort($rows, fn ($a, $b) => $b['stats']['games'] <=> $a['stats']['games']);
                break;
            case 'home_desc':
                usort($rows, fn ($a, $b) => $b['stats']['home'] <=> $a['stats']['home']);
                break;
            case 'ease_desc':
                usort($rows, fn ($a, $b) => $b['stats']['ease'] <=> $a['stats']['ease']);
                break;
            case 'alpha':
            default:
                usort($rows, fn ($a, $b) => strcmp(
                    $a['team']['city'] . $a['team']['name'],
                    $b['team']['city'] . $b['team']['name']
                ));
        }
        return $rows;
    }
}
