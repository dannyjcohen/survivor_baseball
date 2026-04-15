# MLB Survivor Pool (Vanilla PHP)

Private dashboard for **two pool entries** (one owner), server-rendered PHP + MySQL, no build step.

## Requirements

- PHP 7.4+ (PDO `mysql`, `json`, `session`)
- MySQL 5.7+ / MariaDB 10+
- Apache with `mod_rewrite` optional, or PHP built-in server

## Setup

1. Copy `.env.example` to `.env` in the project root and set database credentials and `APP_BASE_PATH`.

   - If the vhost document root **is** the `public/` folder (e.g. `http://survivorbaseball.localhost/`), set **`APP_BASE_PATH` empty** (or omit it).
   - Only set a prefix when the app is in a **subfolder** of the document root (e.g. `http://localhost/survivorbaseball/public/` → `APP_BASE_PATH=/survivorbaseball/public`).

2. Create the database:

   ```sql
   CREATE DATABASE survivorbaseball CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Import schema and seed:

   ```bash
   mysql -u root -p survivorbaseball < sql/schema.sql
   mysql -u root -p survivorbaseball < sql/seed.sql
   ```

   `seed.sql` defines **pool weeks** (Mon–Sun) for the season — the UI and schedule import only include weeks that exist in `pool_weeks`. To align an older DB with the **Week 6 (Apr 27)** restart, run `sql/migration_pool_restart_week6_apr27_2026.sql`. Legacy installs may use `sql/migration_pool_weeks_9_28.sql` if needed. After adding weeks, use **Admin → Import full schedule (all weeks)** to load MLB games.

4. Point the web server document root at the `public/` folder (recommended), or open URLs under `/public/`.

### PHP built-in server

```bash
cd public
php -S localhost:8080
```

Then open `http://localhost:8080/dashboard.php` (with empty `APP_BASE_PATH` in `.env`).

## Project layout

- `config/config.php` — loads `.env`, defines constants
- `src/` — PDO, repositories, `services/` (schedule, survival math, MLB API abstraction)
- `templates/` — PHP templates included by pages in `public/`
- `sql/` — `schema.sql`, `seed.sql`
- `public/` — entry scripts, CSS, JS

## MLB Stats API (live schedule & scores)

The app reads **https://statsapi.mlb.com/api/v1/** (no API key). Implementation: `src/services/MlbApiClient.php`.

- **Admin → Import / refresh schedule** — replaces that pool week’s rows with regular-season games for Mon–Sun (`gameType=R`), mapped by `officialDate` to `pool_weeks`.
- **Refresh scores / results** and **probable pitchers** — same schedule feed; upserts by `gamePk` (`external_game_id`). Probable starters for **both** teams are stored; **season W–L and ERA** (regular season, `gameType=R`) are fetched from the Stats API during sync and cached in `games.home_pitcher_stats_line` / `away_pitcher_stats_line`. Set **`MLB_SEASON_YEAR`** in `.env` if the calendar year differs from the season you want (e.g. early spring). MLB does not expose a simple “league rank” in the same call; ERA is shown as a quick quality signal.
- **Daily** (`public/daily.php`) — bookmark or cron: pulls **yesterday + today** in `APP_TIMEZONE`, upserts into whichever pool week each `officialDate` belongs to. Optional `DAILY_SYNC_KEY` in `.env`; then use `daily.php?run=1&key=YOUR_SECRET` or the form.

PHP needs outbound HTTP (`allow_url_fopen=1` or equivalent for `file_get_contents` on URLs).

`sql/seed.sql` does **not** insert games; load data via Admin or Daily after install.

### The Odds API (moneylines)

- Set `ODDS_API_KEY` in `.env` (from [the-odds-api.com](https://the-odds-api.com/)). **Never commit keys**; rotate any key that was exposed.
- Default sport is `baseball_mlb` (`ODDS_SPORT_KEY`). Cache TTL defaults to **6 hours** (`ODDS_CACHE_TTL_SECONDS=21600`), stored in table `odds_cache`.
- **Admin → Refresh odds cache now** forces a new pull; otherwise the Decision Helper uses the cached payload until it expires.
- Run `sql/migration_odds_cache.sql` if your database was created before the `odds_cache` table existed.
- Run `sql/migration_pitcher_stats_lines.sql` if your database predates pitcher stat columns on `games`.

## Security notes

- Forms use CSRF tokens (`helpers.php`).
- Use prepared statements everywhere (repositories).
- Protect `admin.php` with HTTP basic auth or a password gate before exposing to the internet.

## Pool rules (implemented)

- Weeks run Monday–Sunday; pool restarts at **Week 6 on 2026-04-27** (weeks 1–5 omitted; see `sql/seed.sql`).
- Picks stay **editable** for the current week unless the entry was **eliminated** in a prior week (`deadline_datetime` is stored but not enforced in the UI).
- Each entry picks one team per week; **no team reuse** for that entry across weeks.
- Survival: **≥ 50% wins** over that entry’s team’s games **in the week** (see `SurvivorService`).
