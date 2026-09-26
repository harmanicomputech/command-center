# Command Center: progress tracker

The build brief is [`docs/HANDOFF.md`](HANDOFF.md). This file is updated at the end of every phase.

**Working branch:** `claude/confident-newton-e91rbz`

## Phases

| # | Phase | Status | Built | Left |
| --- | --- | --- | --- | --- |
| 1 | Foundation and design system | ✅ Done | Laravel 13 + Vite + Tailwind v4 + Alpine; tokens (light and dark), self-hosted Inter, Lucide icons; component library and the `/design` style guide; Command Center layout (sidebar, drawer, phone tab bar, ⌘K palette with scoped search) and Field Force shell (tab bar with the central Register button); five roles with server-side LGA/ward scope; staff email sign-in and agent phone + PIN sign-in (30-day remember-me, switch-off signs out everywhere); first-admin setup that loads the register; LGAs, wards and PUs; Map & wards pages with the LGA tile map; Users, Settings (placeholder registry), System (Update database, register import/confirm with the data warning, background runner and pinger), Audit log, My account; PWA manifest, icons, service worker, offline page; shared-hosting build script, CI and deploy guide | Lighthouse pass is phase 8 |
| 2 | Field capture | ✅ Done | Voter registration form (one-handed, segmented choices, required consent with text version, optional GPS rounded to ~100 m, success screen with confetti and points); the IndexedDB outbox shared by pages and the service worker (UUIDs, backoff, Background Sync, sync on open/online/"Sync now", failed items to fix, warning before sign-out); idempotent `POST /api/field/sync` with a fresh-CSRF token route; sync pill; phones encrypted at rest with a keyed hash; possible-duplicate flagging; Registrations page (spot-check sample, call links, verify / invalid, resolve duplicates, admin CSV export with audit row count); agent home with today's ring, streak and ward rank; My registrations (masked numbers); Team & invites (invite links, agent chooses PIN, new link, sign out every device); public privacy notice | Photo queue arrives with tasks and issues (phase 4) |
| 3 | Structure and CRM | ✅ Done | People directory with engagement (active / occasional / dormant over 14 days) and person profiles; structure health per ward (red with no coordinator or 7 quiet days) on its own page, on the dashboard and on ward profiles; influence notes per ward (traditional rulers, churches, mosques, age grades, town unions, market associations… with a contact, an encrypted phone and the relationship); meetings and events with a month calendar, an agenda, "needs an outcome" reminders, attendance and team attendees; ward profile with team, events and influence | Event photos arrive with the photo queue (phase 4); tasks will count towards engagement in phase 4 |
| 4 | Tasks, issues and gamification | ✅ Done | Tasks for an agent or a whole ward (target, due date, proof by photo or count), "My tasks" offline in the field app with progress updates, per-ward completion and overdue lists; issue reports with photos from the field (category, severity, people affected), an issue map, category chart, 8-week trend, status (new → noted → used → addressed, or not accepted) and a printable top-10-per-LGA briefing pack; photos shrunk on the phone (1600px, JPEG 0.7), queued behind their record in the outbox and stored privately (re-encoded, which strips GPS metadata); points worked out from the records (editable in Settings); leaderboards for agents, wards and LGAs, weekly (from Monday 00:00 Lagos) and all time, with a podium and the agent's own row pinned; badges (First 10, 100 club, Ward champion, 7-day streak); rewards log; a private review of suspicious patterns (bursts, consecutive numbers, all "strong"); event photos | "New task" push alerts arrive with Web Push in phase 5 |
| 5 | Voter intelligence and the daily dashboard | ⚪ Next | — | — |
| 6 | Surveys | ⚪ Not started | — | — |
| 7 | AI messaging and media | ⚪ Not started | — | — |
| 8 | Hardening | ⚪ Not started | — | — |

### Phase 1 notes

- **Tests:** 31 feature tests (auth and setup, scope and 403s, register import and warning, users, settings, background runner, every page renders, PWA files, guard tests for the Blade/JS lessons and for committed email addresses).
- **Checked in the browser:** dashboard, Map & wards, an LGA, Users, Settings, System, `/design`, sign-in, field home and Me, at 360, 768 and 1280px in light and dark mode. No horizontal overflow and no script errors. Fixed after review: truncated KPI labels, a flat map colour scale (now min→max), noisy "no data" hints, the users table on phones.
- **Reused from Election Shield:** the PU register CSV and importer (now also building LGAs and wards), BackgroundRunner, RunBackgroundWork, the pinger and `app:tick`, Settings, Audit, Phone, Time, the first-admin setup key, the LGA tile layout, the shared-hosting package, and the guard tests.
- **Tasks and issues** show a "coming in the next update" screen in the field app until phase 4.

### Phase 2 notes

- **Tests:** 46 in all (15 new: sync idempotency, consent, validation, encryption at rest, duplicates, wrong phone clocks, the no-script fallback, masked numbers and stats, verification scope, duplicate resolution, audited exports, invites, revoking devices, team scope).
- **Checked in the browser:** field home, the register form, the outbox page, My registrations, Registrations and Team at 360/768/1280, light and dark. An end-to-end run in Chromium registered a voter **with the network off**, reloaded the app offline (page from the service worker, item still queued, pill "Offline: 1 saved on this phone"), then synced on reconnect ("All synced", record on the server). Fixed after review: header name squeezed by the pill, team rows cramped at 360px, a gap in the coordinator's tab bar.
- **Agents may register voters in any ward of their own LGA** (people near ward boundaries); the record belongs to the ward chosen.
- **Phone is optional** on the form ("if they have one"); duplicates are detected by phone only.

### Phase 3 notes

- **Tests:** 50 in all (4 new: engagement levels and People scope, red-ward rules, influence notes scope and encryption, events planning/recording/scope and Lagos time).
- **Checked in the browser:** dashboard, People, a profile, Structure health, Influence, Events (calendar and agenda), an event and a ward profile at 360/768/1280, light and dark. Fixed after review: KPI labels cut off on phones (now wrap), red-ward reasons overflowing the table, cramped calendar entries.
- **Religion** is recorded only as community influence notes on a ward (a church or mosque as an institution), never per voter, as the brief requires.

### Phase 4 notes

- **Tests:** 56 in all (6 new: tasks and scope, proof rules, idempotent reports, photo upload order and privacy, issues offline-first and review, the briefing pack, points from records and the weekly window, leaderboard ranks, badges, rewards, pattern flags).
- **Checked in the browser:** an issue with a photo reported **offline** in Chromium (two items queued: the report and its photo), then synced on reconnect; the stored photo is 1600px. Screenshots of My tasks, a task, Report an issue, the field leaderboard, Tasks, a task, Issues, the briefing pack, Leaderboard, Review and `/design` at 360/768/1280, light and dark. Fixed after review: the briefing pack's table overflowing at 360px, a lone leader off-centre on the podium, empty LGA tiles tinted as if they had data.
- **Points are computed, not stored:** verifying or invalidating a registration changes scores at once, and changing an amount in Settings applies to everyone's totals.

## Decisions waiting for the owner

Everything below has a working placeholder, so nothing is blocked. Change it when you decide.

| # | Decision | Placeholder in use | Where to change it |
| --- | --- | --- | --- |
| 1 | Party, brand colours and the app's public name | Green `#0f6e4f`, gold `#e0a526`; "Command Center" | Colours: `resources/css/tokens.css` (one file) and re-run `scripts/make-icons.mjs`; name: **Settings → Campaign** |
| 2 | Candidate and party code | Empty | **Settings → Campaign** |
| 3 | The register: confirm it, or supply INEC's PU and ward figures | Bundled register (4,592,490 voters, ~2.9× INEC 2023); warning shown on System | **System → Polling unit register** |
| 4 | Daily registration targets | 20 per agent, 100 per ward, 500,000 total | **Settings → Registration targets** |
| 5 | A domain, and shared hosting or a VPS | Shared hosting | `APP_URL` in `.env` |
| 6 | Past results (2019, 2023) | None; zones show "Unknown" | Phase 5 importer |
| 7 | Anthropic API key and monthly budget | None; AI drafting off | Phase 7 |
| 8 | Africa's Talking sender ID; USSD code or shortcode for polls | None | Phases 6–7 |
| 9 | Rewards policy for top mobilisers | None | Phase 4 |
| 10 | NDPC data-controller registration and a data protection officer | Not named (recommended before registering voters) | **Settings → Privacy** (shown on `/privacy`) |
| 11 | **GitHub Actions doesn't start jobs on this repository** (the CI run got no runner and no logs, usually because Actions is disabled or blocked by billing or a spending limit) | Zips are built locally with the script | Repository **Settings → Actions**, and the account's billing. Then re-run CI: the zips appear as its artifact |
| 12 | Points per action | 10 verified / 3 unverified registration, 15 task, 5 issue, 2 survey, 5 event | **Settings → Points** |

## Upload packages

| Phase | Full install | Update | Where |
| --- | --- | --- | --- |
| 2 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | Built locally in `dist/` at the end of phase 2 (27 MB each; the session's container is temporary). Once GitHub Actions runs (decision 11), download both from the CI artifact, or build them with `scripts/build-shared-hosting.sh` on any computer with PHP, Composer and Node. Existing installs: upload the update zip, then **System → Update database**. |
| 1 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | GitHub → **Actions** → the latest green **CI** run on this branch → **Artifacts** → `command-center-upload-packages` (kept 90 days). The full zip from CI has placeholder secrets that the app fills in on its first visit; read the setup key from `command-center/.env` afterwards. Also built locally with `scripts/build-shared-hosting.sh` (27 MB each). |

Deployment steps: [`docs/DEPLOY-SHARED-HOSTING.md`](DEPLOY-SHARED-HOSTING.md).
