<?php
declare(strict_types=1);
?>
<h1>Admin · data refresh</h1>
<p class="muted">
    Data source: <a href="https://statsapi.mlb.com/api/v1/schedule?sportId=1" target="_blank" rel="noopener">MLB Stats API</a> (regular season, <code>sportId=1</code>).
    For “catch up” scores without picking a week, use <a href="<?= h(app_url('daily.php')) ?>">Daily</a>.
    TODO: protect this page with HTTP basic auth or a password gate before production.
</p>
<form method="post" action="<?= h(app_url('admin.php')) ?>" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <p>
        <label for="pool_week_id">Pool week</label><br>
        <select name="pool_week_id" id="pool_week_id">
            <?php foreach ($weeks as $w): ?>
                <option value="<?= (int) $w['id'] ?>"><?= h($w['week_label']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <div class="admin-actions">
        <button type="submit" name="action" value="schedule" class="btn btn-primary">Import / refresh schedule</button>
        <button type="submit" name="action" value="results" class="btn">Refresh scores / results</button>
        <button type="submit" name="action" value="probables" class="btn">Refresh probable pitchers</button>
    </div>
</form>

<form method="post" action="<?= h(app_url('admin.php')) ?>" class="card" style="margin-top:1rem;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <p class="meta">
        <strong>Import full schedule</strong> — runs the same MLB Stats API import for <em>every</em> pool week in the database (replaces existing games per week). May take a minute.
    </p>
    <p><button type="submit" name="action" value="schedule_full" class="btn btn-primary">Import full schedule (all weeks)</button></p>
</form>

<h2>The Odds API (moneylines)</h2>
<p class="muted">
    Source: <a href="https://the-odds-api.com/" target="_blank" rel="noopener">the-odds-api.com</a>
    · Sport <code><?= h(ODDS_SPORT_KEY) ?></code> · US h2h · American odds · cache <?= (int) (ODDS_CACHE_TTL_SECONDS / 3600) ?>h
</p>
<?php if (ODDS_API_KEY === ''): ?>
    <p class="flash flash-err">Set <code>ODDS_API_KEY</code> in <code>.env</code> to enable odds on the Decision Helper.</p>
<?php else: ?>
    <p class="meta">
        Cache updated: <?= $oddsMeta['updated_at'] ? h($oddsMeta['updated_at']) : 'never' ?>
        · Expires: <?= $oddsMeta['expires_at'] ? h($oddsMeta['expires_at']) : '—' ?>
        <?= !empty($oddsMeta['valid']) ? ' (valid)' : ' (stale or empty — refresh below)' ?>
    </p>
    <form method="post" action="<?= h(app_url('admin.php')) ?>" class="card">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <p><button type="submit" name="action" value="odds_refresh" class="btn btn-primary">Refresh odds cache now</button></p>
    </form>
<?php endif; ?>

<h2>Recent sync log</h2>
<div class="log-box"><?php
    if ($logs === []) {
        echo "No log entries yet.\n";
    }
    foreach ($logs as $L) {
        echo h($L['created_at'] . ' [' . $L['sync_type'] . '] ' . $L['status'] . ' — ' . ($L['message'] ?? '')) . "\n";
    }
?></div>
