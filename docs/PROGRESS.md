# Command Center: progress tracker

The build brief is [`docs/HANDOFF.md`](HANDOFF.md). This file is updated at the end of every phase.

**Working branch:** `claude/confident-newton-e91rbz`

## Phases

| # | Phase | Status | Built | Left |
| --- | --- | --- | --- | --- |
| 1 | Foundation and design system | ✅ Done | Laravel 13 + Vite + Tailwind v4 + Alpine; tokens (light and dark), self-hosted Inter, Lucide icons; component library and the `/design` style guide; Command Center layout (sidebar, drawer, phone tab bar, ⌘K palette with scoped search) and Field Force shell (tab bar with the central Register button); five roles with server-side LGA/ward scope; staff email sign-in and agent phone + PIN sign-in (30-day remember-me, switch-off signs out everywhere); first-admin setup that loads the register; LGAs, wards and PUs; Map & wards pages with the LGA tile map; Users, Settings (placeholder registry), System (Update database, register import/confirm with the data warning, background runner and pinger), Audit log, My account; PWA manifest, icons, service worker, offline page; shared-hosting build script, CI and deploy guide | Lighthouse pass is phase 8 |
| 2 | Field capture | ⚪ Next | — | Voter registration with consent, IndexedDB outbox + `/api/field/sync`, sync pill, duplicates and verification, agent home, invites, privacy notice |
| 3 | Structure and CRM | ⚪ Not started | — | — |
| 4 | Tasks, issues and gamification | ⚪ Not started | — | — |
| 5 | Voter intelligence and the daily dashboard | ⚪ Not started | — | — |
| 6 | Surveys | ⚪ Not started | — | — |
| 7 | AI messaging and media | ⚪ Not started | — | — |
| 8 | Hardening | ⚪ Not started | — | — |

### Phase 1 notes

- **Tests:** 31 feature tests (auth and setup, scope and 403s, register import and warning, users, settings, background runner, every page renders, PWA files, guard tests for the Blade/JS lessons and for committed email addresses).
- **Checked in the browser:** dashboard, Map & wards, an LGA, Users, Settings, System, `/design`, sign-in, field home and Me, at 360, 768 and 1280px in light and dark mode. No horizontal overflow and no script errors. Fixed after review: truncated KPI labels, a flat map colour scale (now min→max), noisy "no data" hints, the users table on phones.
- **Reused from Election Shield:** the PU register CSV and importer (now also building LGAs and wards), BackgroundRunner, RunBackgroundWork, the pinger and `app:tick`, Settings, Audit, Phone, Time, the first-admin setup key, the LGA tile layout, the shared-hosting package, and the guard tests.
- **Voter registration, tasks and issues** show a "coming in the next update" screen in the field app until phases 2 and 4.

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
| 10 | NDPC data-controller registration and a data protection officer | Not named (recommended before registering voters) | Phase 2 privacy notice |

## Upload packages

| Phase | Full install | Update | Where |
| --- | --- | --- | --- |
| 1 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | GitHub → **Actions** → the latest green **CI** run on this branch → **Artifacts** → `command-center-upload-packages` (kept 90 days). The full zip from CI has placeholder secrets that the app fills in on its first visit; read the setup key from `command-center/.env` afterwards. Also built locally with `scripts/build-shared-hosting.sh` (27 MB each). |

Deployment steps: [`docs/DEPLOY-SHARED-HOSTING.md`](DEPLOY-SHARED-HOSTING.md).
