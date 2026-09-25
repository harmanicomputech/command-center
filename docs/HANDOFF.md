# Campaign Command Center: build brief

This brief is for the developer (or Claude session) starting the **Command Center** project in the new GitHub repo `harmanicomputech/command-center` (check the exact name). It explains what to build, what to reuse from the Election Shield web app, the design standard, and the rules that come from running Election Shield in production.

Read all of it before writing code. Then keep a `docs/PROGRESS.md` tracker and a `CLAUDE.md` in the new repo from the first commit.

---

## 1. What we're building

A campaign platform for the **Ebonyi State governorship election (6 February 2027)**. It has two faces in **one Laravel app**:

| | Command Center, "the brain" | Field Force, "the engine" |
| --- | --- | --- |
| Who | Campaign leadership, strategists, analysts, the media team | Field agents, volunteers, ward coordinators |
| Where | Desktop first (also fine on tablet and phone) | Phone first, installed as a PWA, **works offline** |
| Does | Voter intelligence, CRM of the structure, media and sentiment, AI message drafting, surveys, the daily dashboard | Voter registration (canvassing), tasks, issue reports, surveys, leaderboards |

**Why one app:**
- one login and role system, one database, one deploy package, and no sync between two systems;
- a registration captured offline in Ishielu shows on the Command Center map as soon as the phone syncs.

The phone layout (bottom tab bar, big actions) is what field users see; the desktop layout (sidebar) is what leadership sees. Both come from the same app. Election Shield already works this way.

**Stack:** Laravel 13, PHP 8.3+, MySQL in production, SQLite in-memory for tests. It deploys to **DirectAdmin shared hosting with no terminal and no per-minute cron** (section 11).

---

## 2. Users, roles and scope

| Role | Sees | Can do |
| --- | --- | --- |
| `admin` | Everything | Users, settings, imports, exports, deleting data |
| `strategist` | All LGAs, all analytics | Dashboards, segments, messages (draft and approve), surveys, media |
| `lga_leader` | Their LGA | Ward coordinators and volunteers in it, tasks, their LGA's analytics |
| `ward_coordinator` | Their ward | Agents in the ward, assigning tasks, verifying registrations |
| `agent` (field agent or volunteer) | Their own records and tasks, their ward's leaderboard | Registering voters, doing tasks, reporting issues, running surveys |

- **Scope is enforced on the server,** in policies and query scopes, never only in the UI. An LGA leader who types another LGA's URL gets a 403.
- **Phone numbers of registered voters:** only coordinators and above see them in full. Agents see the numbers they captured themselves, masked once synced (`0803 *** **21`).
- **Every export and every bulk action is audit-logged** (who, what, how many rows).
- **Agent sign-in:** agents are invited by their coordinator. They sign in with phone number and PIN (or password), and stay signed in on their device for a long time (30 days, remember-me) so they can work offline. A coordinator can revoke a lost device.

---

## 3. Geography and data

**Geography is the backbone.** Every voter, task, issue, survey response and leaderboard row belongs to a **ward** (and so to an LGA). Polling units are optional detail.

- **Register:** reuse `database/data/ebonyi_polling_units.csv` from `harmanicomputech/electionshield-app` (3,308 PUs, 13 LGAs, 169 wards) and its importer, `app/Services/PollingUnitImporter.php`.
  - PU codes look like `EB/212/02633/007`.
  - Load the register at first-admin setup, like Election Shield does. It can be re-imported or replaced from a newer CSV on the System page.
- **⚠ Data warning:** the register totals **4,592,490 registered voters**. That's about three times INEC's 2023 figure for Ebonyi (about 1.6 million), and its PU names look generic ("Open Space 001").
  - Show a banner on the System page until an admin confirms the register or uploads INEC's.
  - Every "priority zone" calculation uses these numbers, so wrong figures would steer the campaign wrong.
- **The 13 LGAs:** Abakaliki, Afikpo North, Afikpo South, Ebonyi, Ezza North, Ezza South, Ikwo, Ishielu, Ivo, Izzi, Ohaozara, Ohaukwu, Onicha.
- **Past results:** add an importer for the 2019 and 2023 governorship results per LGA and ward (CSV: lga, ward, party, votes). The campaign supplies the data. It is the baseline for strongholds and swing areas.
- **Maps:**
  - Start with the schematic LGA tile map from Election Shield (`app/Services/LgaMap.php`, a 4×5 grid placed roughly geographically, with layers).
  - Then add a real ward-boundary map: GRID3 Nigeria boundaries (CC BY 4.0, from `services3.arcgis.com`) simplified to a small GeoJSON file committed to the repo.
  - Render it as an SVG choropleth with no map tiles, so it works offline and prints well.
  - The environment that built Election Shield had `services3.arcgis.com` blocked. Fetch the file once wherever network allows, or ask the owner to allow the host.

---

## 4. Command Center modules

### 4.1 Voter intelligence (Ebonyi-focused)

- **Zone classification, per ward and per LGA:**
  - **Stronghold:** our share at least 55%.
  - **Swing:** 40–55%.
  - **Weak:** under 40%.
  - **Unknown:** not enough data.
- **Our share** blends three sources with weights set on the Settings page:
  - past results;
  - canvass support levels from Field Force (section 5.1);
  - survey results.

  Show which sources each figure comes from, and how many records it rests on ("based on 312 canvassed voters and the 2023 result").
- **Priority score per ward:** registered voters × (1 − certainty) × reachability. Big, uncertain, reachable wards come first.
  - Example: Izzi and Ikwo are high-population, priority mobilisation zones.
  - Example: Abakaliki is urban, so it's a digital and media target.
  - These are default presets the strategists can edit, not hard-coded rules.
- **Segments** are built from canvass data:
  - age band (18–24, 25–34, 35–44, 45–59, 60+);
  - occupation (farmer, trader, civil servant, student, artisan, transport, unemployed, other);
  - gender;
  - ward and LGA;
  - support level;
  - issues they care about.
- **Religion and community influence:** **never record religion per person** (section 9). Record it at community level instead, as **influence notes** on a ward: key churches, traditional rulers, age grades, town unions and market associations, each with a contact person and a relationship status.
- **Screens:**
  - map and ward table with filters;
  - ward profile: zone, trend, segments, issues, structure, tasks done, influence notes;
  - segment explorer, with counts and a "send to messaging" action.

### 4.2 Campaign CRM (structure and supporters)

- **People:**
  - LGA leaders, ward coordinators, volunteers and agents, all as users;
  - influencers and stakeholders, who aren't users.
- **Tracking:**
  - meetings and events: date, ward, type, attendance, notes, photos;
  - engagement level per person (active, occasional, dormant), worked out from their activity in the last 14 days.
- **Structure health per ward:** does it have a coordinator? How many active agents? When was the last meeting? A ward with no coordinator or no activity in 7 days is flagged red on the dashboard.
- **Calendar view** of upcoming events across LGAs.

### 4.3 Media and sentiment monitoring (realistic scope)

**What does *not* work:**
- **Facebook:** reading public conversations. Meta closed that API access, and CrowdTangle shut down in August 2024.
- **WhatsApp:** it's end-to-end encrypted, so no app can read its groups.

Don't promise either.

**What to build:**
1. **Narratives feed.** Agents and the media team report a rumour, opposition narrative or complaint they saw. Each report has:
   - the source (Facebook, WhatsApp, radio, market talk, blog);
   - a screenshot or photo;
   - an optional link;
   - the LGA or ward;
   - a topic tag;
   - a tone (positive, neutral, negative).

   Similar reports are grouped into **narratives** (manually, or with AI clustering suggestions). Each narrative shows a trend line and a status: new, watching, responding, closed.
2. **News and blog tracker.** Fetch RSS feeds of local and national news sites and blogs from a list admins manage. Keyword alerts cover the candidate, the opponents and the issues. It runs in the background runner (section 11).
3. **Our own pages.** Log posts and engagement from the campaign's own Facebook page manually or by CSV. If the page is connected, use the Graph API for the campaign's *own* page insights, which Meta still allows.
4. **Complaints dashboard,** built from issue reports (section 5.4) plus narratives. It shows what people complain about, where, and whether it's rising.

### 4.4 AI messaging engine

- **Choose a segment** (for example "farmers in Izzi and Ikwo, 25–44") plus a goal, a channel (SMS of 160 characters, WhatsApp, radio script, town-hall talking points, flyer) and a language (English, Igbo, or both). Claude drafts three variants, and an editor refines or approves one.
- **Knowledge base:** a policy brief the campaign maintains in the app, covering agriculture, youth jobs, markets and roads, water, security, education and health, plus the top issues from Field Force data for that segment. This makes messages specific: "the Onu–Ikwo road that 14 of you reported".
- **API:**
  - Use the official Anthropic PHP SDK (`composer require anthropic-ai/sdk`, `use Anthropic\Client;`). It ships in `vendor/`, so no terminal is needed on the host.
  - Default model: `claude-opus-5`.
  - Put the policy brief in a stable, cached system prompt (prompt caching) and the per-request segment and goal after it.
  - Store the API key as `ANTHROPIC_API_KEY` in `.env`.
  - Log tokens and cost per draft, and show the month's spend on the System page.
  - **The Claude session building this should load its `claude-api` skill before writing that code,** because the SDK's parameter names and current options come from there.
- **Rules:**
  - **Human approval before anything is sent.**
  - **No personal data in prompts:** segments and counts only, never names or phone numbers.
  - Every approved message is saved with who approved it.
  - Approved messages can go straight to SMS broadcasts (section 6).
- **"What message to push next":** a daily AI summary on the dashboard, built from the day's aggregate numbers (zone shifts, rising complaints, narratives). It's clearly labelled as a suggestion.

### 4.5 Surveys and polling

- **Survey builder:** single choice, multiple choice, rating, short text, and "which issue matters most". Each survey targets LGAs and wards and has a quota per ward.
- **Channels:**
  - **Agent-run, in the field app, offline:** the main one, for rural wards.
  - **SMS or USSD polls through Africa's Talking,** for quick urban polls in Abakaliki. The same account as Election Shield's USSD service, with a separate service code or shortcode.
  - **A web link** for online respondents.
- **Results** by LGA, ward, age band and occupation, with sample size (n) always shown. Figures with n < 30 are shown faded and marked "small sample".
- **Duplicate protection:** one response per phone per survey.

### 4.6 The daily dashboard (the key output)

This is one screen, and it is the home page for leadership:
1. **Where we are winning:** strongholds holding or growing, with the week's change.
2. **Where we are losing:** weak or falling wards, and swing wards trending the wrong way.
3. **What message to push next:** the AI suggestion (4.4) plus the top rising issues and narratives.
4. **Field activity today:**
   - registrations (today, the week, the total against the target);
   - tasks done;
   - active agents;
   - issues reported;
   - wards with no activity.
5. **The map,** with a layer switch: zone, registrations, activity, issues.
6. **Leaderboard highlights:** the top LGA, the top ward and the top 3 agents.

It gets a **printable one-page version** (PDF or print CSS) for morning briefings, and a **Web Push** alert at 7 AM: "Your daily brief is ready."

---

## 5. Field Force modules (phone PWA, offline first)

### 5.1 Voter registration (canvassing)

- **Fast form, under 60 seconds per voter, one-handed:**
  - name;
  - phone (Nigerian format, normalised to `+234…`);
  - gender;
  - age band;
  - occupation;
  - LGA and ward, pre-filled from the agent's ward;
  - PU, optional;
  - community or village;
  - support level: strong supporter, leaning us, undecided, leaning opponent, opponent;
  - top issue;
  - optional notes;
  - **consent tick-box (required):** "I agree that the campaign can store my details and contact me".
- **Registration IDs:**
  - Each registration gets a client-generated UUID, so syncing twice never creates two records.
  - The server flags a **possible duplicate** when the same phone number is already registered. A coordinator resolves it, and it doesn't score points until then.
- **Verification:** coordinators spot-check registrations with a call from the app, and mark them verified or invalid. Invalid ones remove the agent's points.
- **Optional GPS** at capture, with permission. It's used only to confirm the ward, never shown on maps at person level.

### 5.2 Offline first

This is the most important engineering requirement. Villages in most of Ebonyi's rural LGAs have poor network.
- **App shell:** cached by the service worker.
- **Offline data on the device:** the agent's ward list, PU list and open tasks are cached in IndexedDB.
- **Outbox:**
  - every form (registration, task update, issue, survey response) goes to an IndexedDB outbox first, with its UUID;
  - the outbox syncs when online (Background Sync where supported, plus a sync on app open and a "Sync now" button);
  - it retries with backoff; a 4xx answer marks the item failed and shows why, and 5xx or network errors are retried.
- **Photos** are compressed on the device (max 1600 px, JPEG about 0.7) and queued separately.
- **Sync pill in the header:**
  - "All synced ✓";
  - "12 waiting";
  - "Offline: 12 saved on this phone";
  - "2 failed: tap to fix".
- **Never lose data:** the outbox survives app restarts. Items are removed only after the server confirms them. Warn before logging out while items are waiting.
- **Sync endpoint:** one idempotent `POST /api/field/sync` taking a batch of items and returning a result per item. It needs a session or a token, and CSRF-safe handling for the PWA.

### 5.3 Tasks and coordination

- **Tasks:** door-to-door in a street or community, community meeting, market storm, distributing flyers, following up undecided voters.
- **Fields:** assignee (an agent or a whole ward), due date, target (for example 50 households) and proof (photo or count).
- **Agents** see "My tasks" offline, update progress and mark tasks done with proof.
- **Coordinators and leaders** see completion per ward and LGA, and overdue tasks.

### 5.4 Issue reporting

- **Categories:** bad road, water, electricity, health centre, school, security, flooding or erosion, market, other.
- **Each report has:** a photo, the ward and community, a description, a severity, and how many people are affected (an estimate).
- **In the Command Center:**
  - an issue map and list;
  - trends;
  - "top 10 issues per LGA", for speeches and the candidate's "solution-driven leader" positioning;
  - a status (new, noted, used in a message, addressed);
  - an export to a briefing pack.

### 5.5 Volunteer gamification

- **Points:**
  - verified registration: 10;
  - unverified: 3, going up to 10 when verified;
  - task completed with proof: 15;
  - issue report accepted: 5;
  - survey response: 2;
  - event attendance: 5.

  The amounts are editable in Settings.
- **Leaderboards:** agents within a ward, agents within an LGA, wards across the state, and LGAs. They run weekly and all-time, and reset every Monday at 00:00 Lagos time.
- **Badges:** "First 10", "100 club", "Ward champion", "7-day streak".
- **Rewards:** admins record rewards given (airtime, a recognition shout-out) against the weekly winners.
- **Anti-gaming:**
  - only verified or sampled records count fully;
  - suspicious patterns are flagged for review: many registrations in minutes, the same phone pattern, or all "strong supporter";
  - never shown publicly.

---

## 6. Messaging and notifications (reuse from Election Shield)

- **Web Push** to staff devices, with topics:
  - daily brief ready;
  - new task assigned;
  - a ward went quiet;
  - a narrative spiking;
  - a security issue reported.

  Reuse `PushNotifier` and `PushAlerts` (minishlink/web-push v11, with VAPID keys generated from the System page).
- **SMS broadcasts to consenting registered voters and volunteers,** through Africa's Talking:
  - the audience is a segment (4.1);
  - opt-out keywords (STOP) and delivery reports are handled;
  - there's a count and cost preview before sending.

  Reuse `app/Services/Broadcasting/` (`Audience`, `BroadcastDispatcher`, `SmsSender`, `WhatsAppSender`) and `Jobs/SendBroadcastBatch`.
- **WhatsApp:** only through the WhatsApp Cloud API with approved templates and a verified Meta business, as in Election Shield.

---

## 7. Visual design: it must be beautiful

The owner's top request is that this looks **premium and visually appealing**. Make it feel like a modern product (Linear, Stripe or Vercel dashboards), not a government form. Beauty here means clarity, calm, confidence and polish, not decoration.

### 7.1 Design system first (build it in phase 1, before any feature screens)

- **Build tooling:** Tailwind CSS v4, Alpine.js and Vite, compiled on the developer's machine or in CI into `public/build/` and shipped in the upload zip. The host has no Node, which is fine because nothing is built on the server. Keep a `resources/views/components/` library of Blade components.
- **Tokens:** define them as CSS variables and map them into Tailwind.
  - **Brand colour:** placeholder `--brand: #0f6e4f` (deep green) with `--accent: #e0a526` (warm gold). Swap them for the candidate's party colours once decided; everything must follow the tokens.
  - **Neutrals:** warm greys.
  - **Status colours:** good, warn, bad and info, each with a soft background.
  - Everything is tokenised, so a re-brand is a single-file change.
- **Dark mode:** a full dark theme from day one (system preference plus a toggle), tested on every screen.
- **Type:** **Inter**, self-hosted as WOFF2 so it works offline (not from Google Fonts at runtime).
  - Use tabular numbers for all figures.
  - Scale: 12, 14, 16, 18, 22, 28 and 36/44 for hero numbers.
  - Use a heavier weight for KPIs and a regular weight for body text.
- **Space, shape and depth:**
  - an 8px grid;
  - generous whitespace;
  - cards with 16px radius, a soft layered shadow and a 1px hairline border.
- **Icons:** Lucide, as inline SVG (an MIT-licensed set, stroke 1.75), used consistently.
- **Motion:**
  - 150–250 ms ease-out on hover and press;
  - numbers count up on first load;
  - skeleton shimmer while loading;
  - page transitions with the View Transitions API where supported;
  - everything respects `prefers-reduced-motion`.
- **States:**
  - designed empty states (a friendly illustration or icon plus the next action);
  - skeleton loading;
  - inline success toasts;
  - error states with a way forward.

### 7.2 Command Center look

- **Layout:** a left sidebar (grouped: Overview, Intelligence, Field, Engage, Admin) and a top bar with a global search (people, wards, LGAs) and a command palette (⌘K).
- **Dashboard:**
  - a hero row of KPI tiles, each with a big number, the change against last week (▲ green / ▼ red, with the sign in text, not colour alone) and a sparkline;
  - below that, the map as the visual centrepiece, then the three "winning / losing / push next" cards.
- **Charts:** hand-built SVG or a light library.
  - Follow the dataviz rules: a validated categorical palette for parties (APC #2a78d6, PDP #eb6834, LP #1baf7a, OTHERS grey, as in Election Shield), a sequential ramp for intensity maps, and a diverging ramp for stronghold → weak.
  - Direct labels instead of legends where possible.
  - Every chart has a table view for accessibility.
- **Map:** a large and tactile choropleth with a ward hover card (zone, share, registrations, top issue) and click-through to the ward profile.
- **Tables:** sticky headers, zebra-free with hairlines, right-aligned numbers, row hover, and an inline bar for shares.

### 7.3 Field Force look (phone)

- **Feels like a consumer app:**
  - a bottom tab bar (Home, Register, Tasks, Issues, Me);
  - a big central **＋ Register voter** button;
  - 48px minimum tap targets;
  - thumb-reachable actions;
  - high contrast for outdoor sunlight.
- **Home:**
  - a greeting ("Good morning, Chika");
  - today's progress ring (registrations against the daily target);
  - streak 🔥;
  - my rank in the ward;
  - my open tasks;
  - the sync pill.
- **Registration:**
  - a single scrolling form with large segmented buttons, not dropdowns, for age band, gender and support level;
  - a success screen with a small celebration (confetti, respecting reduced motion) and "+10 points";
  - then "Register another".
- **Leaderboard:** a podium for the top 3 with avatar initials, then a ranked list with the agent's own row highlighted and pinned.
- Works and looks right at **360px width**, with no horizontal scroll ever.

### 7.4 Quality bar

- **Check in a real browser:** every screen at 360px, 768px and 1280px, in light and dark mode, using the preinstalled Chromium and Playwright to screenshot.
- **Lighthouse:** PWA installable, Performance ≥ 90 on mobile, Accessibility ≥ 95.
- **WCAG AA contrast;** focus rings visible; never colour alone.
- Keep a `/design` page (admin only) showing every component and token: the living style guide.

---

## 8. PWA

- **Manifest:**
  - name "Command Center" (or the campaign's name);
  - theme colour from the brand token;
  - maskable icons (192 and 512);
  - app shortcuts: "Register voter", "Report issue", "My tasks".
- **Service worker (`sw.js`, with a VERSION constant you bump on every release):**
  - the app shell is cache-first;
  - data pages are network-first with an offline fallback;
  - **pages or API responses with phone numbers or personal data are never cached;**
  - the offline page is branded.
- **Install prompts:** Android uses `beforeinstallprompt`; iPhone shows an "Add to Home Screen" guide. Web Push on iPhone needs iOS 16.4+ and an installed app.

---

## 9. Data protection (Nigeria Data Protection Act 2023)

Political opinion is **sensitive personal data** under the NDPA. The campaign will hold hundreds of thousands of records, so design for this from day one:
- **Consent:**
  - explicit consent captured and stored with each registration (who captured it, when, the consent text version);
  - no consent means no record.
- **Minimum data:** age *band*, not date of birth; **no religion, ethnicity or voter card (PVC/VIN) number per person.**
- **Security:**
  - phone numbers encrypted at rest (Laravel `encrypted` cast), plus a SHA-256 hash column for de-duplication and search;
  - role-scoped access;
  - exports audit-logged, and restricted to admins.
- **Opt-out:** SMS STOP and a "delete my data" request flow; deletion removes personal fields and keeps anonymous counts.
- **Privacy notice:** a public page linked from the capture form. Suggest to the owner that the campaign registers with the NDPC as a data controller, and names a data protection officer.
- **Backups:** encrypted zip, without passwords or tokens (reuse `DataMaintenance::backup()`). A retention policy deletes voter personal data after the election (setting, default 90 days after 6 Feb 2027).
- **The repo is public:** never commit real data, API keys or the owner's email address. `.env.example` uses placeholders like `you@example.com`.

---

## 10. What to reuse from `harmanicomputech/electionshield-app`

That repo is public. Read or copy from branch `claude/election-day-tools`, which contains everything. It's the same stack and host, and it was hardened in production.

| Need | Reuse |
| --- | --- |
| PU register and importer | `database/data/ebonyi_polling_units.csv`, `app/Services/PollingUnitImporter.php` |
| LGA tile map | `app/Services/LgaMap.php`, `resources/views/partials/lga-map.blade.php` |
| Background work without cron | `app/Support/BackgroundRunner.php`, `app/Http/Middleware/RunBackgroundWork.php`, `RunnerController` (`/cron/{token}`), `Commands/Tick.php` |
| Settings, audit, phone and time helpers | `app/Support/Settings.php`, `Audit.php`, `Phone.php`, `Time.php` |
| First-admin setup key, roles, users, My account | the auth and console controllers, `ADMIN_PASSWORD` as a one-time setup key |
| Web Push | `app/Services/PushNotifier.php`, `PushAlerts.php`, `Console/PushController`, the push parts of `public/js/app.js` and `public/sw.js` |
| SMS and WhatsApp broadcasts | `app/Services/Broadcasting/*`, `Jobs/SendBroadcastBatch`, `Api/MessagingCallbackController`, the consent, opt-out and `/join` pages |
| Offline queue and photo queue | `public/js/app.js` (the localStorage form queue, the IndexedDB `es-queue` photo store, background sync `es-photos`). Upgrade it to one IndexedDB outbox for everything (5.2) |
| Backups and clearing test data | `app/Services/DataMaintenance.php` |
| Printable reports | `resources/views/reports/*` and its print CSS |
| Shared-hosting package | `scripts/build-shared-hosting.sh`, `deploy/shared-hosting/` (`index.php` finds the app folder next to `public_html`; `.htaccess`; `env.template`, which generates secrets), `docs/DEPLOY-SHARED-HOSTING.md` |
| Tests as examples | `tests/Feature/*`, including the guard tests below |

Copy the code and adapt it. Don't add a dependency on the Election Shield app. **Later, optionally:** export the field structure (coordinators and agents per ward) to Election Shield before election day, so the same people become polling agents.

---

## 11. Hosting and deployment (the same DirectAdmin host)

- **The host has no terminal:**
  - every operator task must be doable in the web console;
  - migrations run from a System → **Update database** button;
  - the first admin is created at `/login` with the `.env` setup key;
  - the PU register loads at setup.
- **No per-minute cron:** use Election Shield's BackgroundRunner. Work runs:
  - after the response on page visits (fastcgi or LiteSpeed finish), at most every 15 seconds;
  - from a free **cron-job.org** job hitting `/cron/<token>` every minute (the token is an HMAC of APP_KEY, and the URL is shown on the System page);
  - from an optional hourly `artisan app:tick` host cron.

  Queue-style work (SMS batches, RSS fetch, AI daily summary, leaderboard reset, push) runs from there. `QUEUE_CONNECTION=database`, with small batches.
- **`SESSION_DRIVER=file` and `CACHE_STORE=file`:** the database doesn't exist before first setup, and the setup page gave a 500 on a fresh install until this was changed.
- **Package:**
  - `scripts/build-shared-hosting.sh` builds a full zip (with a generated `.env`) and `--update` builds a zip without `.env`;
  - the zip includes `vendor/` and `public/build/`;
  - after the Vite build, bump `sw.js` VERSION.
- **A VPS is optional.** If the campaign gets one (about $10–20/month), a real queue worker and cron make AI and RSS work easier. Keep the shared-hosting path working anyway.

---

## 12. Lessons from Election Shield: follow these rules

- **Blade:**
  - never put a directive straight after a letter or digit (`KB@if` breaks);
  - never use a one-line `@php(...)` before a `@php … @endphp` block in the same view.

  Add guard tests that scan the views for both.
- **JS:** read a form's URL with `form.getAttribute('action')`. A field named `action` hides `form.action` and posted to "[object HTMLInputElement]".
- **Deferred work:** don't use `dispatch()->afterResponse()` for alerts. Election Shield sent duplicates across requests; use a static pending list flushed in `app()->terminating`, reset for each new app instance.
- **Record timestamps:** ignore records far in the future (a wrong clock) when deciding what's "new".
- **Background jobs:** a scheduled broadcast with no recipients stayed on "sending" until the model was `refresh()`ed before finishing.
- **Phone search:** strip a leading `234` or `0`, so `0802…` matches `+234802…`.
- **Mobile layout:**
  - give grid children `min-width: 0`;
  - wrap wide tables in a horizontal-scroll container;
  - check 360px on every page.
- **Tests:** only push when the whole suite and `vendor/bin/pint --test` pass. Run the full suite before every commit, not just the new tests.
- **Privacy:** never put the owner's email address or any real phone numbers in the public repo, commits or fixtures.

---

## 13. Build order

Each phase ends with passing tests, browser screenshots at 360px and 1280px (light and dark), an updated `docs/PROGRESS.md`, and an upload zip.

1. **Foundation and design system:**
   - the Laravel 13 app, Vite + Tailwind + Alpine build and tokens;
   - the component library and the `/design` page;
   - layouts (sidebar and phone tab bar), dark mode;
   - auth, roles and scopes, first-admin setup, the PU register import, the audit log, settings;
   - the System page with Update database, BackgroundRunner and pinger;
   - the PWA shell, and the shared-hosting package.
2. **Field capture:**
   - voter registration with consent, the IndexedDB outbox and sync endpoint, the sync pill;
   - duplicate detection and verification;
   - agent home screen;
   - invites for coordinators and agents.

   After this phase agents can start registering, so ship it early.
3. **Structure and CRM:** leaders, coordinators and volunteers, meetings, events, engagement, ward structure health.
4. **Tasks, issues and gamification:** tasks with proof, issue reports with photos, points, leaderboards, badges.
5. **Voter intelligence and the daily dashboard:** past-results import, zone classification, priority scores, segments, the map, the daily dashboard and its printable brief.
6. **Surveys:** the builder, field survey mode, SMS/USSD polls, results.
7. **AI messaging and media:** policy knowledge base, Claude drafting and approval, SMS broadcasts to segments, the narratives feed, the RSS tracker, the daily "push next" summary.
8. **Hardening:** Lighthouse and accessibility pass, load test with 500k voter rows (indexes, pagination, cached aggregates), backups, data-protection flows, the election-day retention switch.

---

## 14. Decisions the owner still needs to make

1. The candidate's party and **brand colours** (for the tokens), and the app's public name.
2. **The register:** confirm it, or supply INEC's PU and ward figures (see section 3).
3. **Past results** (2019 and 2023 governorship, per LGA and ward), to seed zone classification.
4. **Daily registration targets** per agent and per ward.
5. **The Anthropic API key and monthly budget** for AI drafting.
6. **Africa's Talking:** sender ID, and whether to use a separate USSD code or shortcode for polls.
7. **A domain,** for example `command.techatronagency.com`, and whether to stay on shared hosting or use a VPS.
8. **Rewards policy** for top mobilisers.
9. **The NDPC data-controller registration** and who the data protection officer is.
