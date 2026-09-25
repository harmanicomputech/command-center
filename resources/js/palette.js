// The command palette (⌘K / Ctrl K): pages from the navigation plus a live
// search of people, wards and LGAs (server-side, scoped to the user's area).
export function registerPalette(Alpine) {
    Alpine.data('palette', (commands = [], searchUrl = null) => ({
        open: false,
        query: '',
        active: 0,
        remote: [],
        loading: false,
        timer: null,
        commands,

        init() {
            window.addEventListener('keydown', (event) => {
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.toggle();
                }
                if (event.key === '/' && !this.open && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName) && !document.activeElement?.isContentEditable) {
                    event.preventDefault();
                    this.show();
                }
            });
            window.addEventListener('open-palette', () => this.show());
        },

        show() {
            this.open = true;
            this.query = '';
            this.remote = [];
            this.active = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        close() {
            this.open = false;
        },

        get results() {
            const q = this.query.trim().toLowerCase();
            const pages = this.commands
                .filter((c) => !q || c.label.toLowerCase().includes(q) || (c.group || '').toLowerCase().includes(q))
                .slice(0, q ? 6 : 12);
            return [...pages, ...this.remote];
        },

        search() {
            this.active = 0;
            clearTimeout(this.timer);
            const q = this.query.trim();
            if (!searchUrl || q.length < 2) {
                this.remote = [];
                return;
            }
            this.loading = true;
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                    const data = response.ok ? await response.json() : { results: [] };
                    if (this.query.trim() === q) {
                        this.remote = data.results || [];
                    }
                } catch {
                    this.remote = [];
                } finally {
                    this.loading = false;
                }
            }, 160);
        },

        move(step) {
            const count = this.results.length;
            if (count) {
                this.active = (this.active + step + count) % count;
                this.$nextTick(() => this.$refs.list?.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }));
            }
        },

        go(item) {
            const target = item || this.results[this.active];
            if (target?.url) {
                this.close();
                window.location.href = target.url;
            }
        },
    }));
}
