# Command Center

Campaign platform for the Ebonyi State governorship election (6 February 2027): one Laravel 13 app with two faces.

- **Command Center** (leadership, desktop first): the daily dashboard, voter intelligence, the structure, media and AI message drafting.
- **Field Force** (agents, phone first, offline-first PWA): voter registration, tasks, issue reports and surveys.

- Build brief: [`docs/HANDOFF.md`](docs/HANDOFF.md)
- Progress and open decisions: [`docs/PROGRESS.md`](docs/PROGRESS.md)
- Deploying on shared hosting: [`docs/DEPLOY-SHARED-HOSTING.md`](docs/DEPLOY-SHARED-HOSTING.md)

## Development

```
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed      # loads the polling unit register
php artisan serve
php artisan test && vendor/bin/pint --test
```

Screenshots of every page at 360/768/1280px in light and dark mode (uses the Playwright install on the machine):

```
NODE_PATH=$(npm root -g) node scripts/screenshots.mjs /tmp/shots you@example.com <password> / /areas /design
```

Icons are [Lucide](https://lucide.dev) (ISC licence), copied into `resources/icons/lucide.php` by `node scripts/icons.mjs`. The typeface is [Inter](https://rsms.me/inter/) (SIL Open Font Licence), self-hosted in `public/fonts/`.
