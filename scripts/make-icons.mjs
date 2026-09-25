// Renders the PWA icons from scripts/icon-source.html with the preinstalled
// Chromium. Re-run after a re-brand: node scripts/make-icons.mjs
import { createRequire } from 'node:module';

// Playwright is preinstalled globally: run with NODE_PATH=$(npm root -g).
const { chromium } = createRequire(import.meta.url)('playwright');
import { fileURLToPath } from 'node:url';

const source = fileURLToPath(new URL('./icon-source.html', import.meta.url));
const out = (name) => fileURLToPath(new URL(`../public/icons/${name}`, import.meta.url));
const icons = [
  ['icon-32.png', 32, false],
  ['icon-192.png', 192, false],
  ['icon-512.png', 512, false],
  ['icon-maskable-512.png', 512, true],
  ['apple-touch-icon.png', 180, true],
];

const browser = await chromium.launch();
const page = await browser.newPage();
for (const [name, size, maskable] of icons) {
  await page.setViewportSize({ width: size, height: size });
  await page.goto(`file://${source}?size=${size}&maskable=${maskable ? 1 : 0}`);
  await page.locator('#icon > div').screenshot({ path: out(name), omitBackground: true });
  console.log(`Wrote ${name}`);
}
await browser.close();
