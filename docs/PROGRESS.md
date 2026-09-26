# Command Center: progress tracker

The build brief is [`docs/HANDOFF.md`](HANDOFF.md). This file is updated at the end of every phase.

**Working branch:** `claude/confident-newton-e91rbz`

## Status: paused for a live test (26 Sep 2026)

Phases 1–7 are built, tested (89 automated tests) and pushed. Development is **paused at the start of phase 8** at the owner's request, so the app can be deployed and tried on a live DirectAdmin server. The owner will bring feedback; work resumes with that feedback, then phase 8.

**Deployed for the live test:** `command-center-shared-hosting.zip` (first install; its `.env` has placeholder secrets that are generated on the first visit) and `command-center-shared-hosting-update.zip`, built from the commit that added this note. Follow [`docs/DEPLOY-SHARED-HOSTING.md`](DEPLOY-SHARED-HOSTING.md).

**What to try on the live server** (and report back on):
1. Install: database, `.env`, the first admin with the setup key, the register loading, **System → Update database** saying up to date.
2. Background work: the pinger at cron-job.org, and **System → Background work** showing *Running*.
3. Sign-in for staff (email + password) and agents (phone + PIN); installing the app on an Android phone and an iPhone.
4. Field Force on a phone: register a voter, report an issue with a photo, report "what people are saying", run a survey, **with mobile data off**, then back on (the sync pill should clear).
5. Command Center: dashboard, map and wards, segments, people, structure, tasks, issues, leaderboard, surveys, narratives, complaints.
6. Web Push: **System → Set up notifications**, then turn them on under **Notifications** and send a test.
7. Optional, if keys are ready: the Claude key (draft a message) and Africa's Talking (a small SMS broadcast to your own number, then reply STOP).
8. Anything slow, confusing, broken, or not matching the campaign's look.

## What's left to finish the app

- **Phase 8, hardening** (not started):
  - Lighthouse and accessibility pass (target: Performance ≥ 90 on mobile, Accessibility ≥ 95, PWA installable), including WCAG AA contrast and focus checks on every screen.
  - Load test with 500,000 voter rows: add indexes, paginate, cache aggregates (dashboard, map, segments, leaderboards) where pages get slow. Ideally measured on MySQL as well as SQLite.
  - Backups: an **encrypted** zip download (AES, admin-chosen password; no passwords or tokens), reusing Election Shield's `DataMaintenance::backup()`, audited.
  - "Delete my data": a request form on the privacy page (verified by the team by phone before erasing), an SMS "DELETE" keyword (the sender's number proves it's theirs), and an admin **Erase** on a voter. Erasure clears personal fields and keeps anonymous counts.
  - The retention switch: erase voter personal data automatically after the election (setting, default 90 days after 6 Feb 2027, i.e. 7 May 2027), with the date shown on System and Settings.
  - Build the final zips and write the final summary here.
- **Feedback from the live test** (to be added here).
- **Owner decisions** below, still on placeholders.


## Phases

| # | Phase | Status | Built | Left |
| --- | --- | --- | --- | --- |
| 1 | Foundation and design system | ✅ Done | Laravel 13 + Vite + Tailwind v4 + Alpine; tokens (light and dark), self-hosted Inter, Lucide icons; component library and the `/design` style guide; Command Center layout (sidebar, drawer, phone tab bar, ⌘K palette with scoped search) and Field Force shell (tab bar with the central Register button); five roles with server-side LGA/ward scope; staff email sign-in and agent phone + PIN sign-in (30-day remember-me, switch-off signs out everywhere); first-admin setup that loads the register; LGAs, wards and PUs; Map & wards pages with the LGA tile map; Users, Settings (placeholder registry), System (Update database, register import/confirm with the data warning, background runner and pinger), Audit log, My account; PWA manifest, icons, service worker, offline page; shared-hosting build script, CI and deploy guide | Lighthouse pass is phase 8 |
| 2 | Field capture | ✅ Done | Voter registration form (one-handed, segmented choices, required consent with text version, optional GPS rounded to ~100 m, success screen with confetti and points); the IndexedDB outbox shared by pages and the service worker (UUIDs, backoff, Background Sync, sync on open/online/"Sync now", failed items to fix, warning before sign-out); idempotent `POST /api/field/sync` with a fresh-CSRF token route; sync pill; phones encrypted at rest with a keyed hash; possible-duplicate flagging; Registrations page (spot-check sample, call links, verify / invalid, resolve duplicates, admin CSV export with audit row count); agent home with today's ring, streak and ward rank; My registrations (masked numbers); Team & invites (invite links, agent chooses PIN, new link, sign out every device); public privacy notice | Photo queue arrives with tasks and issues (phase 4) |
| 3 | Structure and CRM | ✅ Done | People directory with engagement (active / occasional / dormant over 14 days) and person profiles; structure health per ward (red with no coordinator or 7 quiet days) on its own page, on the dashboard and on ward profiles; influence notes per ward (traditional rulers, churches, mosques, age grades, town unions, market associations… with a contact, an encrypted phone and the relationship); meetings and events with a month calendar, an agenda, "needs an outcome" reminders, attendance and team attendees; ward profile with team, events and influence | Event photos arrive with the photo queue (phase 4); tasks will count towards engagement in phase 4 |
| 4 | Tasks, issues and gamification | ✅ Done | Tasks for an agent or a whole ward (target, due date, proof by photo or count), "My tasks" offline in the field app with progress updates, per-ward completion and overdue lists; issue reports with photos from the field (category, severity, people affected), an issue map, category chart, 8-week trend, status (new → noted → used → addressed, or not accepted) and a printable top-10-per-LGA briefing pack; photos shrunk on the phone (1600px, JPEG 0.7), queued behind their record in the outbox and stored privately (re-encoded, which strips GPS metadata); points worked out from the records (editable in Settings); leaderboards for agents, wards and LGAs, weekly (from Monday 00:00 Lagos) and all time, with a podium and the agent's own row pinned; badges (First 10, 100 club, Ward champion, 7-day streak); rewards log; a private review of suspicious patterns (bursts, consecutive numbers, all "strong"); event photos | "New task" push alerts arrive with Web Push in phase 5 |
| 5 | Voter intelligence and the daily dashboard | ✅ Done | Past-results importer (CSV lga,ward,party,votes per year; unmatched names reported, never guessed); zone classification per ward and LGA blending past results, canvass support and (phase 6) surveys with Settings weights, a minimum sample, and "based on …" explanations; certainty, reachability presets per LGA (Izzi/Ikwo priority mobilisation, Abakaliki urban digital and media — editable) and priority scores; week-on-week trend; Map & wards with zone cards, an LGA table and a sortable ward table; ward profile with zone, segments (support, age, occupation) and top issues; segment explorer (counts only) with saved segments; the daily dashboard (winning / losing / push next, field activity, map with zone / registrations / activity / issues layers, priority wards, leaderboard highlights) and a printable one-page brief; Web Push (reused from Election Shield) with topics, deferred sending, the 7 AM "daily brief is ready" and quiet-ward alerts, new-task and security-issue alerts; the ward-boundary map as an upload | The real ward boundaries (decision 13); the AI "push next" suggestion (phase 7) |
| 6 | Surveys | ✅ Done | Survey builder (single and multiple choice, rating, short text, "which issue matters most", voting intention), LGA targeting and a quota per ward, launch/close (questions change only in drafts); agents run surveys **offline** one question per screen and go straight to the next respondent (quota-full surveys drop off their list); a public web link (honeypot, one answer per phone and per browser); USSD polls replayed from the full input history, END-only writes, screens under 182 characters; SMS polls ("PULSE 2"); results with n on every figure, small samples (n < 30) faded and marked, breakdowns by LGA, ward, age, occupation and gender, quota progress, an anonymous CSV export (admin, audited); voting-intention answers feed the zone engine as its third source; responses earn 2 points and count as activity | Sending SMS invitations to answer arrives with broadcasts (phase 7) |
| 7 | AI messaging and media | ✅ Done | **Messages:** pick a segment (or a saved one), a goal, a channel (SMS 160, WhatsApp, radio script, town-hall points, flyer), a language (English, Igbo, both) and a tone; Claude (official Anthropic PHP SDK, `claude-opus-5`, adaptive thinking, structured output, server-side refusal fallback) drafts three versions in the background while the page waits; an editor edits and approves one, saved with their name and audited. **Policy brief** knowledge base by topic, in a cached system prompt (the stable prefix is deterministic). **No personal data in prompts:** segment descriptions and counts, issue counts per category and community, and a scrubber that removes phone numbers and emails from typed text. Every AI call logged with tokens, cache hits and cost; a monthly budget stops drafting; spend on **System** and **Messages**. **SMS broadcasts** (Election Shield's Audience / BroadcastDispatcher / SmsSender / SendBroadcastBatch adapted) to a voter segment or the team, with a live count, GSM/Unicode part count and naira cost preview, a confirmed and audited send, one message per number, STOP opt-outs (inbound STOP, AT opt-out callback, stored as keyed hashes), delivery reports, approved SMS drafts straight to a broadcast. **Narratives:** agents report what people are saying from the field app (offline, with a screenshot), the media team adds reports on the web; an inbox to group them into narratives by hand or from AI grouping suggestions a person accepts; trend lines, status (new, watching, responding, closed), "spiking" push alerts, "Draft a response". **Complaints** dashboard (issues + negative narratives by theme and LGA, rising themes). **News tracker** (RSS/Atom feeds admins list, keyword alerts with a push notification, every 30 minutes from the background runner). **Our pages** (posts and engagement, typed or CSV import). The daily AI **"push next"** suggestion at 06:30 Lagos on the dashboard and brief | Facebook Graph API for the page's own insights (decision 17); WhatsApp broadcasts need a verified Meta business and approved templates (decision 18) |
| 8 | Hardening | ⏸ Paused before starting (live test first) | — | See “What's left” above |

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

### Phase 5 notes

- **Tests:** 67 in all (11 new: unknown zones, canvass-only zones and the sample floor, results import/blend/LGA fallback, priority and presets, the weekly trend, segments counting without listing people, the ward-map import, push keys and subscriptions, alerts sent once to the right people, old security reports raising no alarm, the 7 AM slot, the dashboard and brief).
- **Checked in the browser** with local demo data (never committed): dashboard, brief, Map & wards, segments, past results, presets, notifications, System and a ward profile at 360/768/1280, light and dark. The ward map was checked with a throwaway grid of squares standing in for boundaries. Fixed after review: ward names cut off in the winning/losing lists, a 169-row ward table on phones (now 40 with "show all"), ordinal breakdowns sorted by count instead of by scale, a Blade loop variable overwriting the segment label.
- **The ward map** is ready but needs boundaries: `services3.arcgis.com` (GRID3) is blocked from the build environment, and the GitHub copies of geoBoundaries sit in Git LFS, which it can't read. Upload the GeoJSON on **System → Ward map**; until then maps show LGA tiles.
- **Zones never use invented results.** With no past results and no party set, zones rest on canvassing alone (once a ward has 30 canvassed voters).

### Phase 6 notes

- **Tests:** 73 in all (6 new: building and launching, drafts-only edits, offline field answers with phone de-duplication and points, results with n and breakdowns, the anonymous export, intention feeding the zones, the public link with honeypot and repeat blocking, USSD replay and SMS keyword polls).
- **Checked in the browser:** a survey run **offline** in the field app in Chromium (four questions, the respondent screen, synced on reconnect), plus screenshots of the field survey list and runner, Surveys, the builder, the results page and the public link at 360/768/1280, light and dark. Fixed after review: Alpine's reactive objects couldn't be stored in IndexedDB (now saved as plain data), "By lga" label, a missing variable in the zone engine found by the new test.
- **Phones are never stored with survey answers**, only a keyed hash to stop repeat answers; the CSV export has no names or numbers.

### Phase 7 notes

- **Tests:** 89 in all (16 new): drafting with the cached policy brief and no personal data in the prompt, cost logging, approval with the approver saved, key and budget checks, the exact SDK request (caching breakpoint, structured output, adaptive thinking, fallbacks header) against a mocked HTTP client, the daily suggestion, the knowledge base, SMS part counting, a broadcast end to end with opt-outs, one message per number and delivery reports, STOP replies, team audiences and access, field narrative reports with screenshots, grouping and area scope, spike alerts, AI grouping suggestions checked by a person, RSS and Atom parsing with keyword alerts, page-post CSV import, every Engage page rendering, keys stored encrypted.
- **Checked in the browser:** Messages, a draft (three versions, edit and approve), Policy brief, Broadcasts (new, draft, sent with failures), Narratives and a narrative, Complaints, News, Our pages, System (Connections, AI usage) and the field "What are people saying?" form at 360/768/1280, light and dark: no overflow or script errors. Fixed after review: two icons missing from the icon set, and page-header buttons stacking on wide screens.
- **Claude is never called in the page request:** drafts, grouping suggestions and the daily suggestion run from the queue (the background runner), because a shared host cuts long requests. The SDK timeout is 90 s with one retry. If the host's PHP time limit is lower than about two minutes, drafts may fail; a VPS or the pinger helps.
- **Model and cost:** `claude-opus-5` ($5 / $25 per million tokens; cache reads at 10 %). A typical SMS draft is a few thousand input tokens (most from cache) and under a thousand output tokens: about 2–3 US cents. Set `ANTHROPIC_MODEL` in `.env` to change the model.
- **SMS cost** in the preview is a placeholder ₦4 per part (Settings → Messaging and AI). Igbo letters switch SMS to Unicode (70 characters per part), which the counters show.

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
| 7 | Anthropic API key and monthly budget | No key (AI drafting off); budget US$50 a month | Key: **System → Connections** (or `ANTHROPIC_API_KEY` in `.env`); budget: **Settings → Messaging and AI** |
| 8 | Africa's Talking account, sender ID, SMS price; a USSD code or shortcode for polls | None (broadcasts can be drafted, not sent); ₦4 per SMS part in the preview; "Reply STOP to opt out" footer | Keys and sender ID: **System → Connections**; callback URLs there too; price and footer: **Settings → Messaging and AI**; poll URLs on each survey's page |
| 9 | Rewards policy for top mobilisers | None | Phase 4 |
| 10 | NDPC data-controller registration and a data protection officer | Not named (recommended before registering voters) | **Settings → Privacy** (shown on `/privacy`) |
| 11 | **GitHub Actions doesn't start jobs on this repository** (the CI run got no runner and no logs, usually because Actions is disabled or blocked by billing or a spending limit) | Zips are built locally with the script | Repository **Settings → Actions**, and the account's billing. Then re-run CI: the zips appear as its artifact |
| 13 | **Ward boundaries** for the real ward map: allow `services3.arcgis.com` in the build environment's network settings, or download GRID3's Nigeria ward boundaries (CC BY 4.0) and upload them | LGA tile map | **System → Ward map** |
| 14 | Zone thresholds and source weights | Stronghold 55%+, swing 40–55%, weak under 40%; results 50 / canvass 35 / surveys 15; minimum sample 30 | **Settings → Voter intelligence** |
| 15 | Reachability and labels per LGA | 100% everywhere; Izzi and Ikwo "Priority mobilisation", Abakaliki "Urban: digital and media" | **Map & wards → LGA presets** |
| 16 | News feeds and alert keywords (opponents' names, issues) | No feeds; keywords "Ebonyi, governorship, Abakaliki" plus the candidate | **News → Add a feed**; keywords in **Settings → Messaging and AI** |
| 17 | Connect the campaign's Facebook page (Graph API) for automatic page insights | Manual entry and CSV import | Needs a Meta app and page token; not built until decided |
| 18 | WhatsApp broadcasts (verified Meta business, approved templates) | SMS only | Election Shield's `WhatsAppSender` can be added when the account exists |
| 12 | Points per action | 10 verified / 3 unverified registration, 15 task, 5 issue, 2 survey, 5 event | **Settings → Points** |

## Upload packages

| Phase | Full install | Update | Where |
| --- | --- | --- | --- |
| 7 (live test) | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | Built in `dist/` and sent to the owner in the session for the DirectAdmin test. The full zip's `.env` has placeholders: `APP_KEY` and the setup key are generated on the first visit (read `ADMIN_PASSWORD` from `command-center/.env` in File Manager afterwards). |
| 5 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | Built locally in `dist/` at the end of phase 5 (see the note on phase 2: the session's container is temporary, and GitHub Actions isn't running jobs yet). Existing installs: upload the update zip, then **System → Update database**. |
| 2 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | Built locally in `dist/` at the end of phase 2 (27 MB each; the session's container is temporary). Once GitHub Actions runs (decision 11), download both from the CI artifact, or build them with `scripts/build-shared-hosting.sh` on any computer with PHP, Composer and Node. Existing installs: upload the update zip, then **System → Update database**. |
| 1 | `command-center-shared-hosting.zip` | `command-center-shared-hosting-update.zip` | GitHub → **Actions** → the latest green **CI** run on this branch → **Artifacts** → `command-center-upload-packages` (kept 90 days). The full zip from CI has placeholder secrets that the app fills in on its first visit; read the setup key from `command-center/.env` afterwards. Also built locally with `scripts/build-shared-hosting.sh` (27 MB each). |

Deployment steps: [`docs/DEPLOY-SHARED-HOSTING.md`](DEPLOY-SHARED-HOSTING.md).
