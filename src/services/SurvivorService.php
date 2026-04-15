<?php
declare(strict_types=1);

/**
 * Weekly survival math: ≥50% wins for the week, games Mon–Sun only.
 * TODO: When wiring a real MLB API, confirm tie games / suspended games rules for your pool.
 */
final class SurvivorService
{
    public function __construct(
        private GameRepository $games,
        private PickRepository $picks,
        private WeekRepository $weeks
    ) {}

    public static function minWinsNeeded(int $totalGames): int
    {
        if ($totalGames <= 0) {
            return 0;
        }
        return (int) ceil($totalGames / 2);
    }

    /**
     * @return array{
     *   total:int,
     *   completed:int,
     *   wins:int,
     *   losses:int,
     *   remaining:int,
     *   win_pct:float|null,
     *   min_wins_needed:int,
     *   max_possible_wins:int
     * }
     */
    public function weeklyRecordForTeam(int $teamId, int $poolWeekId): array
    {
        $list = $this->games->gamesForTeamInWeek($teamId, $poolWeekId);
        $total = 0;
        $completed = 0;
        $wins = 0;
        $losses = 0;
        foreach ($list as $g) {
            if (($g['status'] ?? '') === 'cancelled') {
                continue;
            }
            $total++;
            if (($g['status'] ?? '') === 'final') {
                $completed++;
                $isHome = (int) $g['home_team_id'] === $teamId;
                $hs = $g['home_score'] !== null ? (int) $g['home_score'] : null;
                $as = $g['away_score'] !== null ? (int) $g['away_score'] : null;
                if ($hs === null || $as === null) {
                    continue;
                }
                if ($hs === $as) {
                    continue;
                }
                if ($isHome) {
                    if ($hs > $as) {
                        $wins++;
                    } else {
                        $losses++;
                    }
                } elseif ($as > $hs) {
                    $wins++;
                } else {
                    $losses++;
                }
            }
        }
        $remaining = $total - $completed;
        $decided = $wins + $losses;
        $decidedPct = $decided > 0 ? $wins / $decided : null;
        $min = self::minWinsNeeded($total);
        $maxPossible = $wins + $remaining;
        $pacePct = $total > 0 ? $wins / $total : null;

        return [
            'total' => $total,
            'completed' => $completed,
            'wins' => $wins,
            'losses' => $losses,
            'remaining' => $remaining,
            'win_pct' => $decidedPct,
            'pace_pct' => $pacePct,
            'min_wins_needed' => $min,
            'max_possible_wins' => $maxPossible,
        ];
    }

    /**
     * @param bool $hypotheticalScenario If true, wording assumes “what if this team were your pick?” (Decision Helper).
     * @return array{
     *   status:string,
     *   summary:string,
     *   record:array,
     *   week_finalized:bool
     * }
     */
    public function analyzePick(int $entryId, array $weekRow, ?array $pickRow, bool $hypotheticalScenario = false): array
    {
        $weekId = (int) $weekRow['id'];
        if ($pickRow === null) {
            return [
                'status' => 'no_pick',
                'summary' => 'No pick submitted for this entry yet.',
                'record' => $this->emptyRecord(),
                'week_finalized' => false,
            ];
        }
        $teamId = (int) $pickRow['team_id'];
        $rec = $this->weeklyRecordForTeam($teamId, $weekId);
        $weekFinalized = $this->isWeekFinalizedForTeam($teamId, $weekId, $rec);

        if ($rec['total'] === 0) {
            return [
                'status' => 'no_games',
                'summary' => 'No scheduled games found for this team in the pool week (refresh schedule).',
                'record' => $rec,
                'week_finalized' => $weekFinalized,
            ];
        }

        $need = $rec['min_wins_needed'];
        $w = $rec['wins'];
        $r = $rec['remaining'];
        $h = $hypotheticalScenario;

        if ($weekFinalized) {
            if ($w >= $need) {
                return [
                    'status' => 'survived',
                    'summary' => sprintf(
                        'Final: %d–%d (%.1f%% of %d games). Met the ≥50%% requirement for the week.',
                        $w,
                        $rec['losses'],
                        ($rec['pace_pct'] ?? 0) * 100,
                        $rec['total']
                    ),
                    'record' => $rec,
                    'week_finalized' => true,
                ];
            }
            return [
                'status' => 'eliminated',
                'summary' => $h
                    ? sprintf(
                        'Final: %d–%d — below 50%% for the week (would not survive this week).',
                        $w,
                        $rec['losses']
                    )
                    : sprintf(
                        'Final: %d–%d — below 50%% for the week. This entry is eliminated.',
                        $w,
                        $rec['losses']
                    ),
                'record' => $rec,
                'week_finalized' => true,
            ];
        }

        if ($w >= $need) {
            return [
                'status' => 'clinched',
                'summary' => sprintf(
                    'Clinched survival: %d wins already meets/exceeds the %d needed (≥50%% over %d games).',
                    $w,
                    $need,
                    $rec['total']
                ),
                'record' => $rec,
                'week_finalized' => false,
            ];
        }

        if ($rec['max_possible_wins'] < $need) {
            return [
                'status' => 'eliminated',
                'summary' => $h
                    ? sprintf(
                        'Would not survive: even winning all %d remaining game(s) cannot reach %d wins needed.',
                        $r,
                        $need
                    )
                    : sprintf(
                        'Eliminated: even winning all %d remaining game(s) cannot reach %d wins needed.',
                        $r,
                        $need
                    ),
                'record' => $rec,
                'week_finalized' => false,
            ];
        }

        $decided = $w + $rec['losses'];
        if ($decided === 0 && $r > 0) {
            return [
                'status' => 'week_pending',
                'summary' => $h
                    ? 'No decided games yet — week still open.'
                    : 'No decided games yet this week.',
                'record' => $rec,
                'week_finalized' => false,
            ];
        }

        $lhs = $w * $rec['total'];
        $rhs = $need * $decided;
        if ($lhs > $rhs) {
            return [
                'status' => 'ahead_pace',
                'summary' => $h
                    ? sprintf(
                        'Ahead of the pace for ≥50%% over %d games: %d–%d after %d decided (need %d wins for the week).',
                        $rec['total'],
                        $w,
                        $rec['losses'],
                        $decided,
                        $need
                    )
                    : sprintf(
                        'Ahead of pace for the week: %d–%d after %d decided game(s); need %d wins of %d.',
                        $w,
                        $rec['losses'],
                        $decided,
                        $need,
                        $rec['total']
                    ),
                'record' => $rec,
                'week_finalized' => false,
            ];
        }
        if ($lhs === $rhs) {
            return [
                'status' => 'on_pace',
                'summary' => $h
                    ? sprintf(
                        'On pace for ≥50%%: %d–%d through %d decided of %d games (need %d wins).',
                        $w,
                        $rec['losses'],
                        $decided,
                        $rec['total'],
                        $need
                    )
                    : sprintf(
                        'On pace: %d–%d through %d decided of %d games (need %d wins).',
                        $w,
                        $rec['losses'],
                        $decided,
                        $rec['total'],
                        $need
                    ),
                'record' => $rec,
                'week_finalized' => false,
            ];
        }

        $needMore = $need - $w;
        return [
            'status' => 'in_danger',
            'summary' => $h
                ? sprintf(
                    'Would need %d more win(s) in %d remaining game(s) to reach %d wins (≥50%% over %d games). Currently %d–%d — below pace.',
                    $needMore,
                    $r,
                    $need,
                    $rec['total'],
                    $w,
                    $rec['losses']
                )
                : sprintf(
                    'Needs %d more win(s) in %d remaining game(s) to reach %d wins (≥50%% over %d games). Currently %d–%d — below pace.',
                    $needMore,
                    $r,
                    $need,
                    $rec['total'],
                    $w,
                    $rec['losses']
                ),
            'record' => $rec,
            'week_finalized' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function emptyRecord(): array
    {
        return [
            'total' => 0,
            'completed' => 0,
            'wins' => 0,
            'losses' => 0,
            'remaining' => 0,
            'win_pct' => null,
            'pace_pct' => null,
            'min_wins_needed' => 0,
            'max_possible_wins' => 0,
        ];
    }

    /**
     * Week is “final” for survival when every non-cancelled game for this team is final.
     */
    public function isWeekFinalizedForTeam(int $teamId, int $poolWeekId, ?array $rec = null): bool
    {
        $rec ??= $this->weeklyRecordForTeam($teamId, $poolWeekId);
        if ($rec['total'] === 0) {
            return false;
        }
        return $rec['remaining'] === 0;
    }

    /**
     * Entry eliminated before this pool week (any prior finalized week where they failed).
     */
    public function isEntryEliminatedBeforeWeek(int $entryId, int $currentWeekId): bool
    {
        $past = $this->weeks->allBeforeId($currentWeekId);
        foreach ($past as $w) {
            $wid = (int) $w['id'];
            $pick = $this->picks->findForEntryWeek($entryId, $wid);
            if ($pick === null) {
                continue;
            }
            $rec = $this->weeklyRecordForTeam((int) $pick['team_id'], $wid);
            if (!$this->isWeekFinalizedForTeam((int) $pick['team_id'], $wid, $rec)) {
                continue;
            }
            if ($rec['wins'] < $rec['min_wins_needed']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether pick can be edited: entry not eliminated before this week (deadline not enforced).
     */
    public function canEditPick(int $entryId, array $weekRow): bool
    {
        return !$this->isEntryEliminatedBeforeWeek($entryId, (int) $weekRow['id']);
    }
}
