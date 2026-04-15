<?php
declare(strict_types=1);
?>
<h1>Daily sync · MLB results</h1>

<div class="card" style="margin-bottom:1.25rem;">
    <h2 style="margin-top:0;">Automatic sync</h2>
    <p class="meta" style="margin-bottom:0.75rem;">
        On the <strong>first normal page load of each day</strong> (any page), the app pulls MLB results for <strong>yesterday + today</strong> and records that day in the database so it does not repeat. No button required.
        <?php if (defined('AUTO_DAILY_SYNC_ENABLED') && AUTO_DAILY_SYNC_ENABLED): ?>
            <span class="muted">(Enabled — disable with <code>AUTO_DAILY_SYNC=0</code> in <code>.env</code>.)</span>
        <?php else: ?>
            <span class="muted">(<code>AUTO_DAILY_SYNC=0</code> — automatic runs are off.)</span>
        <?php endif; ?>
    </p>
    <?php if ($autoDailySyncDate !== null && $autoDailySyncDate !== ''): ?>
        <p class="muted" style="margin:0;">Last automatic run counted for local calendar day: <strong><?= h($autoDailySyncDate) ?></strong></p>
    <?php else: ?>
        <p class="muted" style="margin:0;">No automatic run recorded yet (needs <code>app_meta</code> table — import <code>sql/schema.sql</code> or <code>sql/migration_app_meta.sql</code>).</p>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <h2 style="margin-top:0;">Manual run (optional)</h2>
    <p class="meta" style="margin-bottom:0.75rem;">
        Same API pull: <strong>yesterday + today</strong> (<code><?= h(APP_TIMEZONE) ?></code>). Use if you need an extra refresh the same day.
    </p>
    <?php if (DAILY_SYNC_KEY === ''): ?>
        <p style="margin:0;">
            <a class="btn btn-primary" href="<?= h($syncRunUrlOpen) ?>">Run sync now (one click)</a>
        </p>
        <p class="muted" style="margin:0.75rem 0 0;">Bookmark: <code><?= h($syncRunUrlOpen) ?></code></p>
    <?php else: ?>
        <p class="muted" style="margin:0 0 0.75rem;">
            <code>DAILY_SYNC_KEY</code> is set — use the form below (or a bookmark with your real key instead of <code>YOUR_SECRET</code>).
        </p>
        <p style="margin:0;">
            <a class="btn btn-primary" href="<?= h(app_url('daily.php')) ?>#sync-form">Jump to sync form</a>
        </p>
    <?php endif; ?>
</div>

<p class="meta">
    Pulls from
    <a href="https://statsapi.mlb.com/api/v1/schedule?sportId=1" target="_blank" rel="noopener">MLB Stats API</a>
    (regular season), upserts scores and status into the matching <code>pool_weeks</code> row by <code>officialDate</code>.
</p>
<?php if (DAILY_SYNC_KEY !== ''): ?>
    <p class="flash flash-err">A sync key is required: add <code>?key=…</code> to the URL or use the form below.</p>
<?php endif; ?>

<?php if ($syncError !== null): ?>
    <p class="flash flash-err"><?= h($syncError) ?></p>
<?php endif; ?>

<?php if ($syncResult !== null): ?>
    <div class="card">
        <h2>Last run</h2>
        <p><strong>Range:</strong> <?= h($syncResult['range']) ?></p>
        <p><strong>API games mapped:</strong> <?= (int) $syncResult['total_rows_api'] ?> ·
            <strong>Upsert operations:</strong> <?= (int) $syncResult['total_upserts'] ?></p>
        <ul>
            <?php foreach ($syncResult['lines'] as $ln): ?>
                <li><?= h($ln) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p><strong>Bookmark / cron URL</strong> (replace <code>YOUR_SECRET</code> with the value from <code>DAILY_SYNC_KEY</code> in <code>.env</code>; omit <code>&amp;key=</code> if the key is empty):</p>
<p class="muted"><code><?= h(app_url('daily.php?run=1&key=YOUR_SECRET')) ?></code></p>
<p><strong>JSON API</strong> (same sync, responds with <code>application/json</code>; use for monitors or cron that expect a machine-readable result):</p>
<p class="muted"><code><?= h(app_url('daily_json.php?run=1&key=YOUR_SECRET')) ?></code></p>

<form id="sync-form" method="post" action="<?= h(app_url('daily.php')) ?>" class="card" style="margin-top:1rem;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="sync">
    <?php if (DAILY_SYNC_KEY !== ''): ?>
        <p>
            <label for="key">Sync key</label><br>
            <input type="password" name="key" id="key" autocomplete="off" style="max-width:280px;">
        </p>
    <?php endif; ?>
    <p><button type="submit" class="btn btn-primary">Run sync (yesterday + today)</button></p>
</form>

<p class="muted"><strong>Cron</strong> (example Windows Task Scheduler / cron calling curl daily morning):</p>
<pre class="log-box">curl -sS "<?= h(app_url('daily.php?run=1&key=YOUR_SECRET')) ?>"</pre>
