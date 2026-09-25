// The sync pill, the register form and the outbox page, on top of
// window.ccOutbox (public/outbox.js, shared with the service worker).
import { confetti, toast } from './ui';

export function registerOutbox(Alpine) {
    const box = () => window.ccOutbox;

    Alpine.store('outbox', {
        total: 0,
        pending: 0,
        failed: 0,
        syncing: false,
        needsSignIn: false,
        ready: false,

        async init() {
            if (!box()) {
                return;
            }
            box().channel?.addEventListener('message', (event) => this.apply(event.data));
            await this.refresh();
            this.ready = true;
            this.sync();
            window.addEventListener('online', () => this.sync(true));
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.sync());
            setInterval(() => this.pending && navigator.onLine && this.sync(), 30000);
        },

        apply(state) {
            this.total = state.total;
            this.pending = state.pending;
            this.failed = state.failed;
            if (state.auth === false) {
                this.needsSignIn = true;
            } else if (state.sent !== undefined && !state.offline) {
                this.needsSignIn = false;
            }
        },

        async refresh() {
            this.apply(await box().counts());
        },

        async sync(force = false) {
            if (!box() || this.syncing) {
                return;
            }
            this.syncing = true;
            try {
                const state = await box().sync(force);
                this.apply(state);
                if (force && state.sent) {
                    toast(`${state.sent} sent to the server.`);
                }
            } finally {
                this.syncing = false;
            }
        },

        get online() {
            return Alpine.store('network').online;
        },

        // "All synced ✓" · "12 waiting" · "Offline: 12 saved on this phone" · "2 failed: tap to fix"
        get label() {
            if (this.failed) {
                return `${this.failed} failed: tap to fix`;
            }
            if (this.needsSignIn && this.pending) {
                return `${this.pending} waiting: sign in`;
            }
            if (!this.online && this.pending) {
                return `Offline: ${this.pending} saved on this phone`;
            }
            if (this.syncing && this.pending) {
                return `Sending ${this.pending}…`;
            }
            return this.pending ? `${this.pending} waiting` : 'All synced';
        },

        get tone() {
            if (this.failed || (this.needsSignIn && this.pending)) {
                return 'bad';
            }
            if (!this.online && this.pending) {
                return 'warn';
            }
            return this.pending ? 'info' : 'good';
        },
    });

    // The registration form: saved to the outbox first, then synced.
    Alpine.data('registerForm', (options) => ({
        saved: null,
        errors: {},
        fixing: null,
        locating: false,
        location: null,
        wardId: String(options.wardId || ''),
        units: options.units || {},
        wards: options.wards || [],
        phonePreview: '',

        async init() {
            // Keep the agent's wards and polling units on the phone.
            box()?.setRef('geo', { wards: this.wards, units: this.units }).catch(() => {});

            const fix = new URLSearchParams(location.search).get('fix');
            if (fix && box()) {
                const item = await box().get(fix);
                if (item) {
                    this.fixing = item;
                    this.errors = item.errors || {};
                    this.$nextTick(() => this.fill(item.payload));
                }
            }
        },

        get wardUnits() {
            return this.units[this.wardId] || [];
        },

        fill(payload) {
            const form = this.$refs.form;
            Object.entries(payload).forEach(([name, value]) => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach((field) => {
                    if (field.type === 'radio') {
                        field.checked = field.value === String(value);
                    } else if (field.type === 'checkbox') {
                        field.checked = Boolean(value);
                    } else {
                        field.value = value ?? '';
                    }
                });
            });
            this.wardId = String(payload.ward_id || this.wardId);
            this.$nextTick(() => {
                const unit = form.querySelector('[name="polling_unit_id"]');
                unit && (unit.value = payload.polling_unit_id || '');
            });
        },

        previewPhone(value) {
            const digits = value.replace(/\D/g, '');
            let local = null;
            if (digits.length === 13 && digits.startsWith('234')) local = '0' + digits.slice(3);
            else if (digits.length === 11 && digits.startsWith('0')) local = digits;
            else if (digits.length === 10 && /^[789]/.test(digits)) local = '0' + digits;
            this.phonePreview = !digits ? '' : local ? `+234 ${local.slice(1, 4)} ${local.slice(4, 7)} ${local.slice(7)}` : 'Not a Nigerian mobile number yet';
        },

        locate() {
            if (!navigator.geolocation) {
                toast('This phone can’t share its location.', 'bad');
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.location = { latitude: position.coords.latitude.toFixed(3), longitude: position.coords.longitude.toFixed(3) };
                    this.locating = false;
                },
                () => {
                    this.locating = false;
                    toast('Location not available. You can save without it.', 'bad');
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
            );
        },

        validate(payload) {
            const errors = {};
            if (!payload.name?.trim()) errors.name = ['Enter the voter’s name.'];
            ['gender', 'age_band', 'occupation', 'support_level'].forEach((field) => {
                if (!payload[field]) errors[field] = ['Choose one.'];
            });
            if (!payload.ward_id) errors.ward_id = ['Choose the ward.'];
            if (payload.phone && this.phonePreview.startsWith('Not')) errors.phone = ['Enter a Nigerian mobile number, e.g. 0803 123 4567.'];
            if (!payload.consent) errors.consent = ['The voter must agree before we can save their details.'];
            return errors;
        },

        async submit(event) {
            if (!box()) {
                return; // No script support for the outbox: the form posts normally.
            }
            event.preventDefault();
            const data = new FormData(this.$refs.form);
            const payload = Object.fromEntries([...data.entries()].filter(([key]) => key !== '_token'));
            payload.consent = data.get('consent') === '1';
            payload.captured_at = new Date().toISOString();
            if (this.location) Object.assign(payload, this.location);

            this.errors = this.validate(payload);
            if (Object.keys(this.errors).length) {
                this.$nextTick(() => this.$refs.form.querySelector('[data-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }

            const label = payload.name.trim();
            this.saved = this.fixing ? await box().replace(this.fixing.id, payload, label) : await box().add('voter', payload, label);
            Alpine.store('outbox').refresh();
            window.scrollTo({ top: 0 });
            confetti();
            Alpine.store('outbox').sync();
        },

        another() {
            const keepWard = this.wardId;
            this.$refs.form.reset();
            this.saved = null;
            this.fixing = null;
            this.errors = {};
            this.location = null;
            this.phonePreview = '';
            this.wardId = keepWard;
            history.replaceState(null, '', location.pathname);
            window.scrollTo({ top: 0 });
            this.$nextTick(() => this.$refs.form.querySelector('[name="name"]')?.focus());
        },
    }));

    // The outbox page: what's waiting on this phone.
    Alpine.data('outboxList', () => ({
        items: [],
        async init() {
            await this.load();
            box()?.channel?.addEventListener('message', () => this.load());
        },
        async load() {
            this.items = box() ? await box().all() : [];
        },
        async discard(item) {
            if (confirm(`Delete “${item.label}” from this phone? It has not reached the server.`)) {
                await box().remove(item.id);
                await this.load();
                Alpine.store('outbox').refresh();
            }
        },
        when(item) {
            return new Date(item.createdAt).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
        },
    }));

    // Sign-out warns while items are waiting on this phone.
    document.addEventListener('submit', (event) => {
        const store = Alpine.store('outbox');
        if (event.target.matches?.('[data-logout]') && store.total > 0) {
            if (!confirm(`${store.total} ${store.total === 1 ? 'item is' : 'items are'} still on this phone and not yet on the server. If you sign out, they stay here and send when you sign back in. Sign out anyway?`)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }
    }, true);
}
