# Deploying on DirectAdmin (no terminal, no per-minute cron)

Everything is done in the hosting control panel and the app's own **System** page. It can sit on the same host as Election Shield, on its own subdomain (for example `command.yourdomain.com`).

## What you need

- **PHP 8.3 or 8.4** with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `zip`.
- **A MySQL database** of its own (don't share Election Shield's).
- **HTTPS** on the subdomain (Let's Encrypt in the control panel). Installing the app and working offline need it.
- **The zip.** Either:
  - from GitHub: open the repository's **Actions** tab → the latest green **CI** run → **Artifacts** → `command-center-upload-packages`. It holds `command-center-shared-hosting.zip` (first install) and `command-center-shared-hosting-update.zip` (updates);
  - or build it on a computer with PHP, Composer and Node: `scripts/build-shared-hosting.sh` (it prints the setup key).

The zip includes every PHP library and the compiled front end, so nothing is built on the server.

## 1. Create the database

In **MySQL Management**, create a database and a user with all privileges on it. Note the name, user and password.

## 2. Upload and extract

```
command-center/   ← the application: must NOT be inside public_html
public_html/      ← web root: index.php, .htaccess, sw.js, build/, fonts/, icons/
```

In **File Manager**, open `domains/<subdomain>/`, upload the zip and extract it, so `command-center/` sits next to `public_html/`. If the subdomain's web root is a folder inside another site's `public_html`, move the contents of the extracted `public_html/` there; `index.php` looks for `command-center/` up to three folders above itself. Never put `command-center/` inside `public_html`.

## 3. Edit `command-center/.env`

Fill in every `CHANGE-ME`: `APP_URL` (`https://command.yourdomain.com`) and `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

`APP_KEY` and `ADMIN_PASSWORD` (the setup key) are generated: by the build script, or, in the zip from GitHub, on the first page visit (open the site once, then reopen `.env` in File Manager to read `ADMIN_PASSWORD`).

## 4. Create the first admin

Open `https://command.yourdomain.com/login`. Enter the setup key, then your name, email and a password. This creates the database tables and loads the polling unit register (13 LGAs, 169 wards, 3,308 PUs). You land on the **System** page.

**Check the register.** The bundled file totals about 4.6 million registered voters, roughly three times INEC's 2023 figure. The System page warns until you upload INEC's register (tick "This is INEC's official register") or confirm the figures.

## 5. Keep background work running

Messages, news fetching, the daily brief and leaderboard resets run in the background without a per-minute cron:

1. **After page visits**, automatically, at most every 15 seconds.
2. **A free pinger, every minute. Set this up.** At **cron-job.org**, add a job that opens the **pinger URL** from the System page (`https://…/cron/<secret>`) every minute. Keep the URL private.
3. **The host's cron, hourly, as a backup** (optional): `0 * * * * /usr/local/bin/php /home/USERNAME/domains/command.yourdomain.com/command-center/artisan app:tick >> /dev/null 2>&1`

On the System page, **Background work** shows **Running** and how it last ran.

## 6. Add your team

Under **Users**, add LGA leaders and ward coordinators (email and password) and field agents (phone number and a 4–6 digit PIN). Agents open the site on their phone, sign in, and install it: **Install the app** in Chrome on Android, or Share → **Add to Home Screen** in Safari on iPhone.

## 7. Connect AI drafting and SMS (optional)

- **System → Connections:** paste the Claude (Anthropic) API key, and the Africa's Talking username, API key and sender ID. They are stored encrypted; values in `.env` (`ANTHROPIC_API_KEY`, `AFRICASTALKING_*`) take priority.
- In the Africa's Talking dashboard, set the three callback URLs shown under Connections (delivery reports, bulk-SMS opt-out, incoming messages), so delivery and STOP replies come back.
- AI drafts run from the background work, so keep the pinger (step 5) running.

## 8. Backups and privacy

- **Backups:** use the host's own database backups (DirectAdmin → Create/Restore Backups), and from time to time **System → Backup** (an encrypted zip of every table, with a password you choose; it isn't stored). Keep a copy of `APP_KEY` from `.env` somewhere safe: phone numbers in the database and the backup are encrypted with it.
- **Delete-my-data requests** from the privacy page appear under **Data requests**: call the number to confirm it's them, then erase. An SMS reply "DELETE" erases at once (the number proves it).
- **Retention:** voter personal data is erased automatically 90 days after the election (change or switch off in **Settings → Privacy**).

## 9. Demo data (for presentations)

**System → Demo data → Load demo data** fills every screen with fictional records (made-up names, 0800 numbers) and shows demo sign-ins for a leader, a coordinator and a field agent. Present from a private window as each role. **Remove demo data** before real work starts; it deletes only what it added.

## Updating

Upload `command-center-shared-hosting-update.zip` (it has no `.env`, so your settings are kept), extract it over the old files, then press **System → Update database**. Each build gives the service worker a new version, so phones pick up the release on their next visit.
