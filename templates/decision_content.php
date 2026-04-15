<?php

declare(strict_types=1);

?>

<h1>Decision helper</h1>

<?php if (!empty($staleGamesCount) && (int) $staleGamesCount > 0): ?>
    <p class="flash flash-warn">
        <strong><?= (int) $staleGamesCount ?></strong> game(s) from before today still need scores.
        <a href="<?= h(app_url('daily.php')) ?>">Daily sync</a> → run <strong>yesterday + today</strong>.
    </p>
<?php endif; ?>

<?php if ($week === null): ?>

    <p class="muted">No pool week.</p>

<?php else: ?>

    <p class="meta">

        <strong><?= h($week['week_label']) ?></strong>

        · Mon–Sun grid · schedule from MLB Stats API

        <?php if (ODDS_API_KEY !== ''): ?>

            · Odds (upcoming games only): The Odds API (h2h)

            <?php if ($oddsCacheNote !== null && !empty($oddsCacheNote['expires_at'])): ?>

                · cache until <?= h($oddsCacheNote['expires_at']) ?>

            <?php endif; ?>

        <?php endif; ?>

    </p>

    <p class="muted">Choose each entry’s team from the <strong>Team · summary</strong> column (buttons below the stat line).</p>

    <?php if (ODDS_API_KEY === ''): ?>

        <p class="muted">Add <code>ODDS_API_KEY</code> to <code>.env</code> to show moneyline odds in cells. Refresh cache under Admin.</p>

    <?php elseif ($oddsErr !== null): ?>

        <p class="flash flash-err">Odds: <?= h($oddsErr) ?></p>

    <?php endif; ?>

    <form method="get" class="filters" action="<?= h(app_url('decision.php')) ?>">

        <label>Week

            <select name="week" onchange="this.form.submit()">

                <?php foreach ($weeks as $w): ?>

                    <option value="<?= (int) $w['id'] ?>"<?= (int) $w['id'] === (int) $week['id'] ? ' selected' : '' ?>><?= h($w['week_label']) ?></option>

                <?php endforeach; ?>

            </select>

        </label>

        <label>Sort

            <select name="sort" onchange="this.form.submit()">

                <option value="alpha"<?= $sort === 'alpha' ? ' selected' : '' ?>>Alphabetical</option>

                <option value="games_desc"<?= $sort === 'games_desc' ? ' selected' : '' ?>>Games (most)</option>

                <option value="home_desc"<?= $sort === 'home_desc' ? ' selected' : '' ?>>Home games</option>

                <option value="ease_desc"<?= $sort === 'ease_desc' ? ' selected' : '' ?>>Est. ease (placeholder)</option>

            </select>

        </label>

    </form>

    <div class="filters">

        <label><input type="checkbox" id="f-hide-used1"> Hide teams already used by <?= h($entries[0]['label'] ?? 'Entry 1') ?></label>

        <label><input type="checkbox" id="f-hide-used2"> Hide teams already used by <?= h($entries[1]['label'] ?? 'Entry 2') ?></label>

        <label><input type="checkbox" id="f-hide-both"> Hide teams used by both</label>

        <label>Availability

            <select id="f-entry-avail">

                <option value="">All teams</option>

                <option value="1">Available for <?= h($entries[0]['label'] ?? 'Entry 1') ?> only</option>

                <option value="2">Available for <?= h($entries[1]['label'] ?? 'Entry 2') ?> only</option>

            </select>

        </label>

    </div>

    <p class="decision-row-legend muted">

        Row highlight (when the week dropdown matches the <strong>current pool week</strong><?php if ($weekUiCurrent !== null): ?> — <strong><?= h($weekUiCurrent['week_label']) ?></strong><?php endif; ?>):

        <span class="leg leg-e1"><?= h($entries[0]['label'] ?? 'Entry 1') ?> pick</span>

        <span class="leg leg-e2"><?= h($entries[1]['label'] ?? 'Entry 2') ?> pick</span>

        <span class="leg leg-both">Both entries</span>

        <span class="leg leg-prior">Already used (can’t pick again) / other schedule weeks</span>

    </p>

    <div class="grid-table-wrap">

        <table class="schedule grid-table" id="decision-grid">

            <thead>

                <tr>

                    <th class="sticky">Team · summary</th>

                    <th class="scenario-col">If this were your pick</th>

                    <?php foreach ($cols as $col): ?>

                        <th><?= h($col[0]) ?><br><span class="muted"><?= h($col[2]) ?></span></th>

                    <?php endforeach; ?>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($grid as $row):

                    $t = $row['team'];

                    $tid = (int) $row['team_id'];

                    $sc = $row['scenario'] ?? null;

                    $rec = $sc ? $sc['record'] : [];

                    $st = $sc ? (string) $sc['status'] : '';

                    $e1id = (int) ($entries[0]['id'] ?? 0);

                    $e2id = (int) ($entries[1]['id'] ?? 0);

                    $p1 = $picksData[$e1id] ?? null;

                    $p2 = $picksData[$e2id] ?? null;

                    $isPick1 = $p1 !== null && (int) $p1['team_id'] === $tid;

                    $isPick2 = $p2 !== null && (int) $p2['team_id'] === $tid;

                    $fb1 = $forbiddenByEntry[$e1id] ?? [];

                    $fb2 = $forbiddenByEntry[$e2id] ?? [];

                    $inFb1 = in_array($tid, $fb1, true);

                    $inFb2 = in_array($tid, $fb2, true);

                    $isHighlightWeek = $week !== null && $weekUiCurrent !== null
                        && (int) $week['id'] === (int) $weekUiCurrent['id'];

                    $rowClass = '';

                    if ($isPick1 && $isPick2) {

                        $rowClass = $isHighlightWeek ? 'row-pick-both' : 'row-pick-arch-both';

                    } elseif ($isPick1) {

                        $rowClass = $isHighlightWeek ? 'row-pick-e1' : 'row-pick-arch-e1';

                    } elseif ($isPick2) {

                        $rowClass = $isHighlightWeek ? 'row-pick-e2' : 'row-pick-arch-e2';

                    } elseif ($inFb1 && $inFb2) {

                        $rowClass = 'row-prior-both';

                    } elseif ($inFb1) {

                        $rowClass = 'row-prior-e1';

                    } elseif ($inFb2) {

                        $rowClass = 'row-prior-e2';

                    }

                    ?>

                    <tr class="decision-grid-row<?= $rowClass !== '' ? ' ' . h($rowClass) : '' ?>"

                        data-row="1"

                        data-used1="<?= $row['used_entry1'] ? '1' : '0' ?>"

                        data-used2="<?= $row['used_entry2'] ? '1' : '0' ?>">

                        <td class="sticky">

                            <strong><?= h($t['abbreviation']) ?></strong>

                            <?= h($t['city']) ?>

                            <?php if ($isPick1 && $isPick2): ?>

                                <?php if ($isHighlightWeek): ?>

                                    <span class="tag tag-pick-both-badge">Your pick · both entries</span>

                                <?php else: ?>

                                    <span class="tag tag-pick-archived">Picked · both · <?= h($week['week_label'] ?? '') ?></span>

                                <?php endif; ?>

                            <?php else: ?>

                                <?php if ($e1id > 0): ?>

                                    <?php if ($isPick1): ?>

                                        <?php if ($isHighlightWeek): ?>

                                            <span class="tag tag-pick-e1">Your pick · <?= h($entries[0]['label'] ?? 'Entry 1') ?></span>

                                        <?php else: ?>

                                            <span class="tag tag-pick-archived">Picked · <?= h($entries[0]['label'] ?? 'Entry 1') ?> · <?= h($week['week_label'] ?? '') ?></span>

                                        <?php endif; ?>

                                    <?php elseif ($inFb1): ?>

                                        <span class="tag tag-burned-e1">Already used · <?= h($entries[0]['label'] ?? 'Entry 1') ?></span>

                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php if ($e2id > 0): ?>

                                    <?php if ($isPick2): ?>

                                        <?php if ($isHighlightWeek): ?>

                                            <span class="tag tag-pick-e2">Your pick · <?= h($entries[1]['label'] ?? 'Entry 2') ?></span>

                                        <?php else: ?>

                                            <span class="tag tag-pick-archived">Picked · <?= h($entries[1]['label'] ?? 'Entry 2') ?> · <?= h($week['week_label'] ?? '') ?></span>

                                        <?php endif; ?>

                                    <?php elseif ($inFb2): ?>

                                        <span class="tag tag-burned-e2">Already used · <?= h($entries[1]['label'] ?? 'Entry 2') ?></span>

                                    <?php endif; ?>

                                <?php endif; ?>

                            <?php endif; ?>

                            <div class="muted">

                                G: <?= (int) $row['stats']['games'] ?>

                                · H/A: <?= (int) $row['stats']['home'] ?>/<?= (int) $row['stats']['away'] ?>

                                · Ease*: <?= h((string) $row['stats']['ease']) ?>

                            </div>

                            <div class="row-pick-actions">

                                <?php foreach ($entries as $idx => $e):

                                    $eid = (int) $e['id'];

                                    $can = $survivor->canEditPick($eid, $week);

                                    $forbidden = $forbiddenByEntry[$eid] ?? [];

                                    $curPick = $picksData[$eid] ?? null;

                                    $isCurrent = $curPick !== null && (int) $curPick['team_id'] === $tid;

                                    $blocked = !$can || (in_array($tid, $forbidden, true) && !$isCurrent);

                                    $tagClass = $idx === 0 ? 'tag-e1' : 'tag-e2';

                                    ?>

                                    <div class="row-pick-actions__line">

                                        <?php if (!$can): ?>

                                            <span class="muted"><?= h($e['label']) ?>: locked</span>

                                        <?php elseif ($isCurrent): ?>

                                            <span class="pick-current <?= h($tagClass) ?>">✓ <?= h($e['label']) ?></span>

                                            <form method="post" class="inline-pick-form pick-clear-form" action="<?= h(app_url('decision.php')) ?>">

                                                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                                                <input type="hidden" name="pool_week_id" value="<?= (int) $week['id'] ?>">

                                                <input type="hidden" name="sort" value="<?= h($sort) ?>">

                                                <input type="hidden" name="pick_action" value="clear">

                                                <input type="hidden" name="row_pick_entry" value="<?= $eid ?>">

                                                <button type="submit" class="btn btn-sm btn-clear-pick">Clear</button>

                                            </form>

                                        <?php elseif ($blocked): ?>

                                            <span class="muted"><?= h($e['label']) ?>: prior use</span>

                                        <?php else: ?>

                                            <form method="post" class="inline-pick-form" action="<?= h(app_url('decision.php')) ?>">

                                                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                                                <input type="hidden" name="pool_week_id" value="<?= (int) $week['id'] ?>">

                                                <input type="hidden" name="sort" value="<?= h($sort) ?>">

                                                <input type="hidden" name="row_pick_entry" value="<?= $eid ?>">

                                                <input type="hidden" name="row_pick_team" value="<?= $tid ?>">

                                                <button type="submit" class="btn btn-sm btn-pick"><?= h($e['label']) ?></button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </td>

                        <td class="scenario-col">

                            <?php if ($sc !== null):

                                $rp = $rec['pace_pct'] ?? null;

                                ?>

                                <span class="status-pill <?= h(survivor_status_pill_class($st)) ?>"><?= h(survivor_scenario_label($st)) ?></span>

                                <?php if ((int) ($rec['total'] ?? 0) > 0): ?>

                                    <div class="muted" style="font-size:0.85rem;margin-top:0.25rem;">

                                        <?= (int) $rec['wins'] ?>–<?= (int) $rec['losses'] ?> (<?= (int) $rec['remaining'] ?> rem / <?= (int) $rec['total'] ?> gms)

                                        <?php if ($rp !== null): ?>

                                            · pace <?= sprintf('%.0f', $rp * 100) ?>%

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

                                <div class="muted" style="font-size:0.8rem;margin-top:0.2rem;line-height:1.35;"><?= h($sc['summary']) ?></div>

                            <?php else: ?>

                                <span class="muted">—</span>

                            <?php endif; ?>

                        </td>

                        <?php foreach ($cols as $col):

                            $d = $col[1];

                            $cellGames = $row['cells'][$d] ?? [];

                            ?>

                            <td class="cell-game">

                                <?php if ($cellGames === []): ?>

                                    <span class="off">Off</span>

                                <?php else: ?>

                                    <?php foreach ($cellGames as $g):

                                        $isHome = (int) $g['home_team_id'] === $tid;

                                        $oppAbbr = $isHome ? ($g['away_abbr'] ?? '') : ($g['home_abbr'] ?? '');

                                        $loc = $isHome ? 'vs' : '@';

                                        $tstr = (new DateTimeImmutable($g['start_datetime']))->format('g:i A');

                                        $hab = $g['home_abbr'] ?? '';
                                        $aab = $g['away_abbr'] ?? '';
                                        $homeP = $g['home_probable_pitcher'] ?? '';
                                        $awayP = $g['away_probable_pitcher'] ?? '';
                                        $homeS = (string) ($g['home_pitcher_stats_line'] ?? '');
                                        $awayS = (string) ($g['away_pitcher_stats_line'] ?? '');

                                        $gst = (string) ($g['status'] ?? 'scheduled');
                                        $hsRaw = $g['home_score'] ?? null;
                                        $asRaw = $g['away_score'] ?? null;
                                        $hsNum = ($hsRaw !== null && $hsRaw !== '') ? (int) $hsRaw : null;
                                        $asNum = ($asRaw !== null && $asRaw !== '') ? (int) $asRaw : null;
                                        $hasScore = $hsNum !== null && $asNum !== null;

                                        $odd = null;
                                        if ($oddsSvc !== null && $gst === 'scheduled') {
                                            $odd = $oddsSvc->findOddsForGame(
                                                $oddsLookup,
                                                (int) $g['home_team_id'],
                                                (int) $g['away_team_id'],
                                                (string) $g['game_date_local']
                                            );
                                        }

                                        ?>

                                        <div>

                                            <strong><?= h($loc) ?> <?= h($oppAbbr) ?></strong><br>

                                            <?= h($tstr) ?><br>

                                            <div class="pitcher-matchup">
                                                <span class="pitcher-row"><?= h($hab) ?> <strong><?= h($homeP !== '' ? $homeP : 'SP TBD') ?></strong><?php if ($homeS !== ''): ?> <span class="muted">(<?= h($homeS) ?>)</span><?php endif; ?></span><br>
                                                <span class="pitcher-row"><?= h($aab) ?> <strong><?= h($awayP !== '' ? $awayP : 'SP TBD') ?></strong><?php if ($awayS !== ''): ?> <span class="muted">(<?= h($awayS) ?>)</span><?php endif; ?></span>
                                            </div>

                                            <?php if (($gst === 'final' || $gst === 'in_progress') && $hasScore): ?>
                                                <br><span class="game-result"><?php if ($gst === 'final'): ?>Final · <?php else: ?>Live · <?php endif; ?><?= h($hab) ?> <?= (int) $hsNum ?>–<?= (int) $asNum ?> <?= h($aab) ?></span>
                                            <?php elseif ($gst === 'final' && !$hasScore): ?>
                                                <br><span class="game-result-muted">Final</span>
                                            <?php elseif ($gst === 'postponed'): ?>
                                                <br><span class="game-result-muted">Postponed</span>
                                            <?php elseif ($odd !== null): ?>
                                                <br><span class="odds-line"><?= h($odd['line']) ?></span>
                                            <?php endif; ?>

                                        </div>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </td>

                        <?php endforeach; ?>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <p class="muted">*Estimated ease is a placeholder (home share heuristic) until a model exists.</p>

<?php endif; ?>

