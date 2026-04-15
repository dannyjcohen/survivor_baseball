<?php
declare(strict_types=1);
$title = $title ?? 'MLB Survivor Pool';
$active = $active ?? '';
$main_class = $main_class ?? 'wrap main';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="<?= h(app_url('css/style.css')) ?>">
</head>
<body>
    <header class="site-header">
        <div class="wrap">
            <a class="brand" href="<?= h(app_url('dashboard.php')) ?>">Survivor Pool</a>
            <nav class="nav">
                <a href="<?= h(app_url('dashboard.php')) ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= h(app_url('picks.php')) ?>" class="<?= $active === 'picks' ? 'active' : '' ?>">Picks</a>
                <a href="<?= h(app_url('decision.php')) ?>" class="<?= $active === 'decision' ? 'active' : '' ?>">Decision Helper</a>
                <a href="<?= h(app_url('history.php')) ?>" class="<?= $active === 'history' ? 'active' : '' ?>">History</a>
                <a href="<?= h(app_url('daily.php')) ?>" class="<?= $active === 'daily' ? 'active' : '' ?>">Daily</a>
                <a href="<?= h(app_url('watch.php')) ?>" class="<?= $active === 'watch' ? 'active' : '' ?>">Multi-watch</a>
                <a href="<?= h(app_url('admin.php')) ?>" class="<?= $active === 'admin' ? 'active' : '' ?>">Admin</a>
            </nav>
        </div>
    </header>
    <main class="<?= h($main_class) ?>">
        <?php if (!empty($flash_ok)): ?>
            <p class="flash flash-ok"><?= h($flash_ok) ?></p>
        <?php endif; ?>
        <?php if (!empty($flash_err)): ?>
            <p class="flash flash-err"><?= h($flash_err) ?></p>
        <?php endif; ?>
        <?php include $template_body; ?>
    </main>
    <footer class="site-footer">
        <div class="wrap muted">Private pool · 2 entries · Vanilla PHP</div>
    </footer>
    <?php if (!empty($extra_scripts)): ?>
        <?= $extra_scripts ?>
    <?php endif; ?>
</body>
</html>
