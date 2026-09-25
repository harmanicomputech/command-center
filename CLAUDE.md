# Command Center

Campaign platform for the Ebonyi State governorship election (6 Feb 2027). One Laravel 13 app with two faces: the **Command Center** (leadership, desktop first, sidebar layout) and the **Field Force** app (agents, phone first, offline-first PWA, bottom tab bar). MySQL in production, SQLite in-memory for tests. Deploys to DirectAdmin shared hosting with **no terminal and no per-minute cron**.

The full brief is `docs/HANDOFF.md`; progress and the owner's open decisions are in `docs/PROGRESS.md`. **Update both after every phase.**

## Commands

- `php artisan test` (or `scripts/test.sh` for a short summary): the whole suite, before every commit
- `vendor/bin/pint`: format code (CI runs `pint --test`)
- `npm run build`: compile CSS/JS into `public/build/` (the host has no Node; the build ships in the zip)
- `scripts/build-shared-hosting.sh [--update|--ci]`: upload zips in `dist/`; CI uploads them as the `command-center-upload-packages` artifact
- `NODE_PATH=$(npm root -g) node scripts/screenshots.mjs <dir> <login> <password> <paths…>`: screenshots at 360/768/1280 in light and dark, reports horizontal overflow and JS errors
- `node scripts/icons.mjs` (after adding a Lucide name), `NODE_PATH=$(npm root -g) node scripts/make-icons.mjs` (PWA icons)
- `php artisan pu:import [csv]`, `php artisan app:tick`

## Layout

- **Design system:** `resources/css/tokens.css` (every colour, shadow and radius; a re-brand is this one file), `resources/css/app.css` (Tailwind v4 mapping + component classes: `.card`, `.btn-*`, `.input`, `.segmented`, `.badge-*`, `.table`, `.nav-item`, `.tabbar`), `resources/views/components/` (Blade components: `x-button`, `x-card`, `x-kpi`, `x-input`, `x-segmented`, `x-empty`, `x-table`, `x-modal`, `x-lga-map`, …), `resources/views/design/index.blade.php` (the `/design` living style guide: add every new component there). JS in `resources/js/` (Alpine: theme, ⌘K palette, toasts, `x-count`, PWA).
- **Layouts:** `components/layouts/app` (Command Center: sidebar ≥1024px, drawer on tablets, tab bar on phones), `layouts/field` (Field Force), `layouts/guest`, `layouts/base`. Navigation for sidebar, tab bar and palette comes from `App\Support\Navigation` (items appear once their route exists).
- **Roles and scope:** `App\Enums\UserRole` (admin, strategist, lga_leader, ward_coordinator, agent). `User::limitToArea($query, $wardColumn)`, `Ward::visibleTo()`, `Lga::visibleTo()`, `User::canSeeLga()/canSeeWard()`, and the `BelongsToWard` trait (`inAreaOf($user)`) enforce scope on the server; controllers `abort_unless(... , 403)`. Route middleware: `staff` (Command Center, not agents), `role:admin,...`. Agents sign in with phone + PIN, staff with email + password (`Console\AuthController`); `TrackActivity` signs out switched-off accounts.
- **Geography:** `lgas`, `wards`, `polling_units`, loaded by `App\Services\PollingUnitImporter` from `database/data/ebonyi_polling_units.csv` at first-admin setup (totals kept on wards/LGAs). `RegisterStatus` drives the register warning. `LgaMap` is the schematic tile map.
- **Operator tasks:** `Console\SystemController` (Update database, register import/confirm, pinger), `Console\SettingsController` + `App\Support\SettingsRegistry` (every owner decision with a placeholder default; read with `Settings::get/int`), `Console\UserController` + `App\Services\UserDirectory`, `Console\AuditController`.
- **Background work:** `App\Support\BackgroundRunner` (after-response runs, `/cron/{token}`, `app:tick`). Add periodic tasks to `BackgroundRunner::tasks()` with `everyMinutes()/dailyAt()/weekly()` slots, not to `routes/console.php`.
- **PWA:** `public/sw.js` (VERSION bumped by the build script; precaches `/build/manifest.json`; caches only the `DATA_PAGES` allowlist), manifest served by `PwaController`, icons from `scripts/make-icons.mjs`.
- **Deploy:** `scripts/build-shared-hosting.sh`, `deploy/shared-hosting/` (`index.php` finds `command-center/` next to `public_html` and fills `{{PLACEHOLDER}}` secrets on first request), `docs/DEPLOY-SHARED-HOSTING.md`.

## Rules

- Never push with failing tests or `pint --test` failures. Screenshot new screens at 360/768/1280, light and dark, and fix overflow before a phase is done.
- The repo is public: no real personal data, API keys or the owner's email address, in code, fixtures or commits (tests use `example.com` and fake `0800` numbers; `GuardTest` checks).
- Data protection (brief §9): no religion, ethnicity or PVC/VIN per person; age band, not date of birth; voter phones encrypted with a keyed hash (`App\Support\Phone::hash`) for de-duplication; exports admin-only and audit-logged with row counts (`Audit::record(..., rows: n)`); pages with phone numbers are never cached by the service worker.
- Blade: never a directive straight after a letter or digit (`KB@if`), never a one-line `@php(...)` before a `@php … @endphp` block, never `</x-slot>@if` on one line (all guarded by `GuardTest`).
- JS: read a form's URL with `form.getAttribute('action')`.
- Mobile: grid children get `min-width: 0` (in base CSS); wide tables sit in `x-table` (scrolls inside the card) and hide secondary columns on phones; 48px targets in the field app; inputs are 16px on phones.
- Deferred alerts: a static pending list flushed in `app()->terminating`, not `dispatch()->afterResponse()`. Ignore far-future timestamps when deciding what's "new". `refresh()` a model before finishing a background job. Phone search strips a leading `234`/`0` (`Phone::searchDigits`).
- Times are stored in UTC and shown in Africa/Lagos (`App\Support\Time`).
