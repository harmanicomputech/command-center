// Service worker registration and the install prompt (Android's
// beforeinstallprompt; an "Add to Home Screen" guide on iPhone).
export function registerPwa(Alpine) {
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }

    let deferred = null;

    Alpine.store('install', {
        available: false,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.navigator.standalone,
        installed: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        async prompt() {
            if (!deferred) {
                return;
            }
            deferred.prompt();
            await deferred.userChoice;
            deferred = null;
            this.available = false;
        },
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event;
        Alpine.store('install').available = true;
    });

    window.addEventListener('appinstalled', () => {
        Alpine.store('install').installed = true;
        Alpine.store('install').available = false;
    });

    // Logging out clears pages the service worker saved for offline use.
    document.addEventListener('submit', (event) => {
        if (event.target.matches?.('[data-logout]') && navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage('clear-data');
        }
    });

    Alpine.store('network', {
        online: navigator.onLine,
    });
    window.addEventListener('online', () => (Alpine.store('network').online = true));
    window.addEventListener('offline', () => (Alpine.store('network').online = false));
}
