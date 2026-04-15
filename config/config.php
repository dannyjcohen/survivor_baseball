<?php
/**
 * Loads environment from .env (project root) if present.
 * Falls back to getenv() / defaults.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . DIRECTORY_SEPARATOR . '.env';

if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $v = getenv($key);
    if ($v !== false) {
        return $v;
    }
    return $default;
}

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'survivorbaseball'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', '') ?? '');
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('APP_BASE_PATH', rtrim(env('APP_BASE_PATH', ''), '/'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'America/New_York') ?? 'America/New_York');

/** MLB Stats API base (no trailing slash). */
define('MLB_STATS_API_BASE', rtrim(env('MLB_STATS_API_BASE_URL', 'https://statsapi.mlb.com/api/v1'), '/'));
/** Season for pitcher W–L / ERA lines (regular season). */
$__mlb_season = env('MLB_SEASON_YEAR', (string) date('Y')) ?? (string) date('Y');
define('MLB_SEASON_YEAR', (int) ($__mlb_season !== '' ? $__mlb_season : (string) date('Y')));
unset($__mlb_season);
/** Optional: require this query/body key for daily.php (bookmark + cron). */
define('DAILY_SYNC_KEY', env('DAILY_SYNC_KEY', '') ?? '');

/** On normal page loads, run yesterday+today MLB sync at most once per calendar day (see app_meta). Set to 0 to disable. */
define('AUTO_DAILY_SYNC_ENABLED', (env('AUTO_DAILY_SYNC', '1') ?? '1') !== '0');

/** The Odds API — key from https://the-odds-api.com/ (never commit real keys). */
define('ODDS_API_KEY', env('ODDS_API_KEY', '') ?? '');
define('ODDS_API_BASE', rtrim(env('ODDS_API_BASE_URL', 'https://api.the-odds-api.com/v4'), '/'));
/** e.g. baseball_mlb (see /v4/sports). */
define('ODDS_SPORT_KEY', env('ODDS_SPORT_KEY', 'baseball_mlb') ?? 'baseball_mlb');
define('ODDS_CACHE_TTL_SECONDS', (int) (env('ODDS_CACHE_TTL_SECONDS', '21600') ?? 21600));

date_default_timezone_set(APP_TIMEZONE);
