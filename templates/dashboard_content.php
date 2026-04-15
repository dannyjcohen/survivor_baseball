<?php
declare(strict_types=1);
?>
<h1>Dashboard</h1>
<?php if (!empty($staleGamesCount) && (int) $staleGamesCount > 0): ?>
    <p class="flash flash-warn">
        <strong><?= (int) $staleGamesCount ?></strong> game(s) from before today still need scores from MLB.
        <a href="<?= h(app_url('daily.php')) ?>">Open Daily sync</a> and run <strong>Run sync (yesterday + today)</strong> (or use your bookmark / cron).
    </p>
<?php endif; ?>
<?php if ($week === null): ?>
    <p class="muted">No pool weeks configured. Import <code>sql/schema.sql</code> and <code>sql/seed.sql</code>.</p>
<?php else: ?>
    <p class="meta">
        <strong>Current pool week:</strong> <?= h($week['week_label']) ?>
        (<?= h($week['week_start_local']) ?> – <?= h($week['week_end_local']) ?>)
    </p>
    <div class="grid-2">
        <?php foreach ($cards as $c):
            $a = $c['analysis'];
            $rec = $a['record'] ?? [];
            $st = $a['status'] ?? 'no_pick';
            $pill = survivor_status_pill_class($st);
            ?>
            <section class="card">
                <h2><?= h($c['entry']['label']) ?></h2>
                <?php if ($c['elim_before']): ?>
                    <p class="flash flash-err">Eliminated before this week — picks locked.</p>
                <?php endif; ?>
                <p>
                    <span class="status-pill <?= h($pill) ?>"><?= h(survivor_status_label($st)) ?></span>
                    <?php if ($c['pick']): ?>
                        <strong><?= h($c['pick']['city'] . ' ' . $c['pick']['team_name']) ?></strong>
                        (<?= h($c['pick']['abbreviation']) ?>)
                    <?php else: ?>
                        <span class="muted">No pick yet</span>
                    <?php endif; ?>
                </p>
                <p class="meta">
                    <?php if ($c['locked']): ?>Pick is <strong>locked</strong> (eliminated before this week).<?php else: ?>Pick is <strong>editable</strong>.<?php endif; ?>
                </p>
                <?php if ($rec && (int) $rec['total'] > 0): ?>
                    <p>
                        <strong>Weekly record:</strong> <?= (int) $rec['wins'] ?>–<?= (int) $rec['losses'] ?>
                        · <strong>Games:</strong> <?= (int) $rec['completed'] ?> / <?= (int) $rec['total'] ?> completed
                        · <strong>Win % (pace vs total week games):</strong>
                        <?= $rec['pace_pct'] !== null ? h(number_format((float) $rec['pace_pct'] * 100, 1)) . '%' : '—' ?>
                    </p>
                <?php elseif ($rec): ?>
                    <p class="muted">No games in schedule for this team this week — refresh schedule on Admin.</p>
                <?php endif; ?>
                <p><?= h($a['summary'] ?? '') ?></p>
                <?php if ($c['upcoming'] !== []): ?>
                    <h3>Upcoming games this week</h3>
                    <table class="schedule">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Matchup</th>
                                <th>Pitchers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($c['upcoming'] as $g):
                                $isHome = (int) $g['home_team_id'] === (int) ($c['pick']['team_id'] ?? 0);
                                $opp = $isHome ? ($g['away_abbr'] ?? '') : ($g['home_abbr'] ?? '');
                                $loc = $isHome ? 'Home' : 'Away';
                                $tstr = (new DateTimeImmutable($g['start_datetime']))->format('D M j g:i A');
                                $match = ($isHome ? 'vs ' : '@ ') . $opp;
                                $hab = $g['home_abbr'] ?? '';
                                $aab = $g['away_abbr'] ?? '';
                                $homeP = $g['home_probable_pitcher'] ?? '';
                                $awayP = $g['away_probable_pitcher'] ?? '';
                                $homeS = (string) ($g['home_pitcher_stats_line'] ?? '');
                                $awayS = (string) ($g['away_pitcher_stats_line'] ?? '');
                                ?>
                                <tr>
                                    <td><?= h($tstr) ?></td>
                                    <td><?= h($loc) ?> <?= h($match) ?></td>
                                    <td class="pitcher-matchup-cell"><?= h($hab) ?>: <strong><?= h($homeP !== '' ? $homeP : 'TBD') ?></strong><?php if ($homeS !== ''): ?> <span class="muted">(<?= h($homeS) ?>)</span><?php endif; ?><br><?= h($aab) ?>: <strong><?= h($awayP !== '' ? $awayP : 'TBD') ?></strong><?php if ($awayS !== ''): ?> <span class="muted">(<?= h($awayS) ?>)</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
