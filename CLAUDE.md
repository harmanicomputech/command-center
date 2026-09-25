# Command Center

Campaign platform for the Ebonyi State governorship election (6 Feb 2027). One Laravel 13 app with two faces: the **Command Center** (leadership, desktop first, sidebar layout) and the **Field Force** app (agents, phone first, offline-first PWA, bottom tab bar). MySQL in production, SQLite in-memory for tests. Deploys to DirectAdmin shared hosting with **no terminal and no per-minute cron**.

The full brief is `docs/HANDOFF.md`; progress and the owner's open decisions are in `docs/PROGRESS.md`. Update both after every phase.

## Commands

- `php artisan test`: run the tests (always the whole suite before a commit)
- `vendor/bin/pint`: format code (CI runs `pint --test`)
- `npm run build`: build CSS/JS into `public/build/` (the host has no Node; the build ships in the zip)
- `scripts/build-shared-hosting.sh [--update]`: build the upload zip

## Rules

- Never push with failing tests or `pint --test` failures.
- The repo is public: no real personal data, API keys or the owner's email address, in code, fixtures or commits.
