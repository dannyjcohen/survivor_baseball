<?php
declare(strict_types=1);
?>
<h1>History</h1>
<?php if ($histRows === []): ?>
    <p class="muted">No picks recorded yet.</p>
<?php else: ?>
    <table class="schedule">
        <thead>
            <tr>
                <th>Week</th>
                <?php foreach ($entries as $e): ?>
                    <th><?= h($e['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($histRows as $hr):
                $w = $hr['week'];
                ?>
                <tr>
                    <td>
                        <strong><?= h($w['week_label']) ?></strong><br>
                        <span class="muted"><?= h($w['week_start_local']) ?> – <?= h($w['week_end_local']) ?></span>
                    </td>
                    <?php foreach ($entries as $e):
                        $eid = (int) $e['id'];
                        $c = $hr['cells'][$eid];
                        $a = $c['analysis'];
                        $rec = $a['record'] ?? [];
                        $p = $c['pick'];
                        $st = $a['status'] ?? 'no_pick';
                        ?>
                        <td>
                            <?php if ($p === null): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <strong><?= h($p['abbreviation']) ?></strong>
                                <span class="status-pill <?= h(survivor_status_pill_class($st)) ?>"><?= h(survivor_status_label($st)) ?></span>
                                <?php if ((int) ($rec['total'] ?? 0) > 0): ?>
                                    <p class="muted" style="margin:0.35rem 0 0;">
                                        <?= (int) $rec['wins'] ?>–<?= (int) $rec['losses'] ?>
                                        · <?= $rec['pace_pct'] !== null ? h(number_format((float) $rec['pace_pct'] * 100, 1)) : '—' ?>% of <?= (int) $rec['total'] ?> gms
                                    </p>
                                <?php endif; ?>
                                <p class="muted" style="margin:0.25rem 0;"><?= h($a['summary'] ?? '') ?></p>
                                <p class="muted" style="margin:0;">Team burned for this entry: <strong>Yes</strong> (cannot reuse)</p>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
