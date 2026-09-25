// Small UI behaviours shared by every page: toasts, count-up numbers,
// confetti and copy-to-clipboard.
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function toast(message, tone = 'good') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, tone } }));
}

export function confetti(count = 70) {
    if (reducedMotion()) {
        return;
    }
    const colors = ['var(--brand)', 'var(--accent)', 'var(--good)', 'var(--info)', '#ffffff'];
    for (let i = 0; i < count; i++) {
        const piece = document.createElement('span');
        piece.className = 'confetti-piece';
        piece.style.left = `${Math.random() * 100}vw`;
        piece.style.background = colors[i % colors.length];
        piece.style.setProperty('--dx', `${(Math.random() - 0.5) * 240}px`);
        piece.style.setProperty('--spin', `${360 + Math.random() * 720}deg`);
        piece.style.setProperty('--fall', `${1400 + Math.random() * 1200}ms`);
        piece.style.animationDelay = `${Math.random() * 250}ms`;
        document.body.appendChild(piece);
        setTimeout(() => piece.remove(), 3000);
    }
}

export function registerUi(Alpine) {
    window.cc = Object.assign(window.cc || {}, { toast, confetti });

    Alpine.data('toasts', (initial = []) => ({
        items: [],
        init() {
            initial.forEach((item) => this.add(item));
            window.addEventListener('toast', (event) => this.add(event.detail));
        },
        add({ message, tone = 'good' }) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone });
            setTimeout(() => this.remove(id), tone === 'bad' ? 9000 : 5000);
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    }));

    // x-count="1234": counts up from 0 on first view (numbers only).
    Alpine.directive('count', (el, { expression }, { evaluate }) => {
        const target = Number(evaluate(expression));
        const decimals = Number(el.dataset.decimals || 0);
        const format = (value) =>
            value.toLocaleString('en-NG', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        if (!Number.isFinite(target) || reducedMotion() || target === 0) {
            el.textContent = format(target || 0) + (el.dataset.suffix || '');
            return;
        }

        el.textContent = format(0) + (el.dataset.suffix || '');
        const start = () => {
            const began = performance.now();
            const duration = 900;
            const step = (now) => {
                const t = Math.min(1, (now - began) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = format(target * eased) + (el.dataset.suffix || '');
                if (t < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        };

        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                start();
            }
        });
        observer.observe(el);
    });

    Alpine.data('copy', (text) => ({
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1600);
            } catch {
                toast('Could not copy. Select the text and copy it instead.', 'bad');
            }
        },
    }));
}
