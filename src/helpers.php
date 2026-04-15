<?php
declare(strict_types=1);

function h(?string $s): string
{
    if ($s === null) {
        return '';
    }
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = APP_BASE_PATH;
    $path = ltrim($path, '/');
    if ($base === '') {
        return '/' . $path;
    }
    return $path === '' ? $base : $base . '/' . $path;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $out = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $out;
}

function survivor_status_pill_class(string $status): string
{
    if ($status === 'survived' || $status === 'clinched' || $status === 'ahead_pace') {
        return 'status-safe';
    }
    if ($status === 'on_pace') {
        return 'status-on_pace';
    }
    if ($status === 'week_pending') {
        return 'status-week_pending';
    }
    if ($status === 'in_danger') {
        return 'status-danger';
    }
    if ($status === 'eliminated') {
        return 'status-eliminated';
    }
    if ($status === 'no_pick' || $status === 'no_games') {
        return 'status-no_pick';
    }
    return 'status-no_pick';
}

function survivor_status_label(string $status): string
{
    if ($status === 'survived') {
        return 'Survived';
    }
    if ($status === 'clinched') {
        return 'Clinched survival';
    }
    if ($status === 'ahead_pace') {
        return 'Ahead of pace';
    }
    if ($status === 'on_pace') {
        return 'On pace';
    }
    if ($status === 'week_pending') {
        return 'Awaiting games';
    }
    if ($status === 'in_danger') {
        return 'In danger';
    }
    if ($status === 'eliminated') {
        return 'Eliminated';
    }
    if ($status === 'no_pick') {
        return 'No pick';
    }
    if ($status === 'no_games') {
        return 'No games';
    }
    return $status;
}

/**
 * Decision Helper: rows for current-week picks first (Entry 1, then Entry 2), then remaining teams in existing order.
 *
 * @param list<array<string,mixed>> $grid
 * @param list<array<string,mixed>> $entries
 * @param array<int, array<string,mixed>|null> $picksData
 * @return list<array<string,mixed>>
 */
function decision_grid_pinned_picks_first(array $grid, array $entries, array $picksData): array
{
    $pinned = [];
    $pinnedTeamIds = [];
    foreach ($entries as $e) {
        $eid = (int) $e['id'];
        $pick = $picksData[$eid] ?? null;
        if ($pick === null) {
            continue;
        }
        $tid = (int) $pick['team_id'];
        if (isset($pinnedTeamIds[$tid])) {
            continue;
        }
        foreach ($grid as $row) {
            if ((int) $row['team_id'] === $tid) {
                $pinned[] = $row;
                $pinnedTeamIds[$tid] = true;
                break;
            }
        }
    }
    $out = $pinned;
    foreach ($grid as $row) {
        $tid = (int) $row['team_id'];
        if (!isset($pinnedTeamIds[$tid])) {
            $out[] = $row;
        }
    }
    return $out;
}

/** Labels for Decision Helper “if you had picked this team” column. */
function survivor_scenario_label(string $status): string
{
    if ($status === 'survived') {
        return 'Would survive';
    }
    if ($status === 'clinched') {
        return 'Would clinch';
    }
    if ($status === 'ahead_pace') {
        return 'Ahead of pace';
    }
    if ($status === 'on_pace') {
        return 'On pace';
    }
    if ($status === 'week_pending') {
        return 'Awaiting games';
    }
    if ($status === 'in_danger') {
        return 'Below pace';
    }
    if ($status === 'eliminated') {
        return 'Would not survive';
    }
    if ($status === 'no_games') {
        return 'No games';
    }
    return survivor_status_label($status);
}
