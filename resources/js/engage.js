// Engage pages: the broadcast form's live count, length and cost preview.
export function registerEngage(Alpine) {
    Alpine.data('broadcastForm', (options) => ({
        type: 'voters',
        roles: ['agent'],
        lgas: [],
        message: options.message || '',
        footer: options.footer || '',
        count: options.count || 0,
        loading: false,
        get full() {
            const text = this.message.trim();
            if (!text) return '';
            return this.footer && !text.toLowerCase().includes(this.footer.toLowerCase()) ? `${text} ${this.footer}` : text;
        },
        get sms() {
            return window.cc.sms(this.full);
        },
        get cost() {
            return Math.round(this.count * this.sms.parts * options.costPerPart * 100) / 100;
        },
        init() {
            this.$watch('type', () => this.refresh());
            this.$watch('roles', () => this.refresh());
            this.$watch('lgas', () => this.refresh());
        },
        async refresh() {
            clearTimeout(this.pending);
            this.pending = setTimeout(async () => {
                this.loading = true;
                try {
                    const audience = this.type === 'team' ? { type: 'team', roles: this.roles, lga_id: this.lgas } : { type: 'voters', filters: options.filters };
                    const response = await fetch(options.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ audience, message: this.message }),
                    });
                    if (response.ok) {
                        this.count = (await response.json()).count;
                    }
                } finally {
                    this.loading = false;
                }
            }, 250);
        },
    }));
}
