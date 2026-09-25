// Screenshots every page at 360, 768 and 1280px in light and dark mode, and
// reports horizontal overflow (no page may scroll sideways). A dev tool:
//
//   NODE_PATH=$(npm root -g) node scripts/screenshots.mjs <out-dir> <login> <password> <path> [<path>…]
//
// Uses the preinstalled Chromium through the global Playwright.
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const { chromium } = createRequire(import.meta.url)('playwright');
const [outDir, login, password, ...paths] = process.argv.slice(2);
const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const widths = (process.env.WIDTHS || '360,768,1280').split(',').map(Number);
const themes = (process.env.THEMES || 'light,dark').split(',');
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const problems = [];

for (const theme of themes) {
  const context = await browser.newContext({ colorScheme: theme, reducedMotion: 'reduce' });
  const page = await context.newPage();
  page.on('pageerror', (error) => problems.push(`JS error: ${error.message}`));
  page.on('console', (message) => message.type() === 'error' && problems.push(`console: ${message.text()}`));

  if (login !== '-') {
    await page.goto(`${base}/login`);
    await page.fill('input[name=login]', login);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }

  for (const path of paths) {
    for (const width of widths) {
      await page.setViewportSize({ width, height: width < 500 ? 780 : 900 });
      await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(150);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      if (overflow > 0) {
        problems.push(`${path} @${width} ${theme}: ${overflow}px horizontal overflow`);
      }
      const name = `${path.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}-${width}-${theme}.png`;
      await page.screenshot({ path: `${outDir}/${name}`, fullPage: true });
    }
  }
  await context.close();
}

await browser.close();
console.log(problems.length ? problems.join('\n') : 'No overflow or script errors.');
