<?php
declare(strict_types=1);
?>
<h1>Weekly picks</h1>
<?php if ($week === null): ?>
    <p class="muted">No pool week available.</p>
<?php else: ?>
    <p class="meta">
        <strong><?= h($week['week_label']) ?></strong>
    </p>
    <form method="post" class="picks" action="<?= h(app_url('picks.php')) ?>">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="pool_week_id" value="<?= (int) $week['id'] ?>">
        <?php foreach ($entries as $e):
            $eid = (int) $e['id'];
            $can = $survivor->canEditPick($eid, $week);
            $cur = $picksData[$eid] ?? null;
            $forbidden = $pickRepo->usedTeamIdsExcludingWeek($eid, (int) $week['id']);
            ?>
            <div class="card" style="margin-bottom:1rem;">
                <h2><?= h($e['label']) ?></h2>
                <?php if (!$can): ?>
                    <p class="muted">Read-only (eliminated before this week).</p>
                    <p><strong><?= $cur ? h($cur['city'] . ' ' . $cur['team_name']) : '—' ?></strong></p>
                <?php else: ?>
                    <label for="team_<?= $eid ?>">Team</label>
                    <select name="team_<?= $eid ?>" id="team_<?= $eid ?>" required>
                        <option value="">— Select —</option>
                        <?php foreach ($teams as $t):
                            $tid = (int) $t['id'];
                            $dis = in_array($tid, $forbidden, true);
                            $sel = $cur && (int) $cur['team_id'] === $tid ? ' selected' : '';
                            if ($dis && !$sel) {
                                continue;
                            }
                            ?>
                            <option value="<?= $tid ?>"<?= $sel ?>><?= h($t['city'] . ' ' . $t['name'] . ' (' . $t['abbreviation'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="used-list">Previously used (cannot select again): <?php
                        $labels = [];
                        foreach ($usedByEntry[$eid] as $utid) {
                            foreach ($teams as $t) {
                                if ((int) $t['id'] === (int) $utid) {
                                    $labels[] = $t['abbreviation'];
                                    break;
                                }
                            }
                        }
                        echo $labels === [] ? '—' : h(implode(', ', $labels));
                    ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php
        $anyEdit = false;
        foreach ($entries as $e) {
            if ($survivor->canEditPick((int) $e['id'], $week)) {
                $anyEdit = true;
                break;
            }
        }
        ?>
        <?php if ($anyEdit): ?>
            <p><button type="submit" class="btn btn-primary">Save picks</button></p>
        <?php endif; ?>
    </form>
    <p class="meta">Switch week:
        <?php
        $links = [];
        foreach ($weeks as $w) {
            $links[] = '<a href="' . h(app_url('picks.php?week=' . (int) $w['id'])) . '">' . h($w['week_label']) . '</a>';
        }
        echo implode(' · ', $links);
        ?>
    </p>
    <script>
    (function () {
        var fb = <?= json_encode($forbiddenByEntry, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var form = document.querySelector('form.picks');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            var ok = true;
            form.querySelectorAll('select[name^="team_"]').forEach(function (sel) {
                var m = sel.name.match(/^team_(\d+)$/);
                if (!m) return;
                var eid = m[1];
                var v = parseInt(sel.value, 10);
                if (!v) return;
                var list = fb[eid] || [];
                if (list.indexOf(v) !== -1) {
                    ok = false;
                }
            });
            if (!ok) {
                e.preventDefault();
                alert('Cannot pick a team already used in a prior week for that entry.');
            }
        });
    })();
    </script>
<?php endif; ?>
