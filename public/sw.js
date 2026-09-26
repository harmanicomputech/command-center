/*
 * Command Center service worker.
 * - App shell (built CSS/JS, fonts, icons, the offline page): cache first,
 *   for an instant start with no network. Built files are listed in
 *   /build/manifest.json and precached on install.
 * - A short allowlist of pages (the field app and the leadership overview):
 *   network first, falling back to the last copy seen.
 * - Never cached: anything else, and above all pages or API responses with
 *   phone numbers or other personal data (users, registrations, exports).
 * - The page cache is cleared on logout.
 * Bump VERSION on every release (scripts/build-shared-hosting.sh does it).
 */
importScripts('/outbox.js');

const VERSION = 'v5';
const SHELL = `cc-shell-${VERSION}`;
const PAGES = 'cc-pages';
const SHELL_FILES = [
  '/offline',
  '/outbox.js',
  '/manifest.webmanifest',
  '/icons/icon-32.png',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/fonts/inter-latin.woff2',
  '/fonts/inter-latin-ext.woff2',
];
// Pages safe to keep offline: no voter or staff phone numbers on any of them.
// (The field outbox page lists only this phone's own queue, from IndexedDB.)
const DATA_PAGES = [/^\/$/, /^\/field(\/(register|tasks|tasks\/\d+|issues|me|outbox|leaderboard|surveys|surveys\/\d+))?$/, /^\/areas(\/[^/]+){0,2}$/, /^\/brief$/];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL);
    await cache.addAll(SHELL_FILES);
    try {
      const manifest = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json();
      const files = new Set();
      Object.values(manifest).forEach((entry) => {
        files.add(`/build/${entry.file}`);
        (entry.css || []).forEach((css) => files.add(`/build/${css}`));
      });
      await cache.addAll([...files]);
    } catch (error) {
      // No build manifest (development): assets are cached as they load.
    }
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith('cc-shell-') && key !== SHELL).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'clear-data') {
    event.waitUntil(caches.delete(PAGES));
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(page(request, url));
    return;
  }

  if (/^\/(build|fonts|icons)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest' || url.pathname === '/outbox.js') {
    event.respondWith(shell(request));
  }
});

async function page(request, url) {
  const cacheable = DATA_PAGES.some((pattern) => pattern.test(url.pathname));

  try {
    const response = await fetch(request);

    // A redirect (to /login) means the session is gone: keep nothing.
    if (cacheable && response.ok && !response.redirected) {
      const cache = await caches.open(PAGES);
      await cache.put(url.pathname, response.clone());
    }

    return response;
  } catch (error) {
    const cached = cacheable ? await caches.match(url.pathname, { cacheName: PAGES }) : undefined;

    return cached || (await caches.match('/offline')) || Response.error();
  }
}

async function shell(request) {
  const cached = await caches.match(request, { ignoreSearch: true });

  if (cached) {
    return cached;
  }

  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(SHELL);
    cache.put(request, response.clone());
  }
  return response;
}

// The outbox (public/outbox.js) sends what is waiting even after the app is
// closed, where the browser supports Background Sync. A failure throws, so
// the browser tries again later.
self.addEventListener('sync', (event) => {
  if (event.tag === 'cc-outbox') {
    event.waitUntil(self.ccOutbox.sync(false).then((state) => {
      if (state.offline || (state.pending > 0 && state.auth !== false)) {
        throw new Error('Outbox not empty yet');
      }
    }));
  }
});

// Web Push: the daily brief, new tasks, quiet wards, security issues.
self.addEventListener('push', (event) => {
  let message = { title: 'Command Center', body: 'Something needs your attention.', url: '/' };
  try {
    message = { ...message, ...event.data.json() };
  } catch (error) {
    // No or unreadable payload: show the generic alert.
  }

  event.waitUntil(self.registration.showNotification(message.title, {
    body: message.body,
    tag: message.tag,
    renotify: Boolean(message.tag),
    requireInteraction: Boolean(message.urgent),
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-32.png',
    data: { url: message.url || '/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
        await client.focus();
        return client.navigate ? client.navigate(target) : undefined;
      }
    }
    return self.clients.openWindow(target);
  })());
});
