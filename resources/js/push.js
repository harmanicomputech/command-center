// Web Push, turned on per device. On iPhone it needs iOS 16.4+ and the app
// installed on the Home Screen.
const keyBytes = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
};

const post = (url, body) =>
    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(body),
    });

export function registerPush(Alpine) {
    Alpine.data('pushPanel', (publicKey, allTopics) => ({
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        on: false,
        busy: false,
        message: '',
        topics: [...allTopics],
        async init() {
            if (!this.supported) {
                return;
            }
            const registration = await navigator.serviceWorker.ready.catch(() => null);
            this.on = Boolean(await registration?.pushManager.getSubscription());
        },
        async subscription() {
            const registration = await navigator.serviceWorker.ready;
            return (await registration.pushManager.getSubscription()) || registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });
        },
        async enable() {
            this.busy = true;
            try {
                if ((await Notification.requestPermission()) !== 'granted') {
                    this.message = 'Notifications are blocked for this site. Allow them in the browser’s site settings.';
                    return;
                }
                const sub = (await this.subscription()).toJSON();
                const response = await post('/push/subscribe', { ...sub, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0], topics: this.topics });
                this.on = response.ok;
                this.message = response.ok ? 'On for this device. Try “Send a test”.' : 'The server couldn’t save this device.';
            } catch {
                this.message = 'Couldn’t turn on notifications. Check your connection and try again.';
            } finally {
                this.busy = false;
            }
        },
        async disable() {
            this.busy = true;
            try {
                const registration = await navigator.serviceWorker.ready;
                const sub = await registration.pushManager.getSubscription();
                if (sub) {
                    await post('/push/unsubscribe', { endpoint: sub.endpoint });
                    await sub.unsubscribe();
                }
                this.on = false;
                this.message = 'Off for this device.';
            } finally {
                this.busy = false;
            }
        },
        async test() {
            const response = await post('/push/test', {}).catch(() => null);
            this.message = response?.ok ? 'Test sent. It should appear in a few seconds.' : 'The test couldn’t be delivered.';
        },
    }));
}
