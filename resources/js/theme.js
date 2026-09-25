// Light / dark / system. The inline script in the layout's <head> applies the
// saved choice before the first paint; this keeps it in sync afterwards.
const KEY = 'cc-theme';

export function storedTheme() {
    try {
        return localStorage.getItem(KEY) || 'system';
    } catch {
        return 'system';
    }
}

export function applyTheme(choice) {
    const dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.content = dark ? '#0e0e0d' : meta.dataset.light || '#f7f6f3';
    }
    return dark;
}

export function registerTheme(Alpine) {
    Alpine.store('theme', {
        choice: storedTheme(),
        dark: document.documentElement.dataset.theme === 'dark',
        set(choice) {
            this.choice = choice;
            try {
                localStorage.setItem(KEY, choice);
            } catch {
                // Private mode: the choice lasts for this page only.
            }
            this.dark = applyTheme(choice);
        },
        toggle() {
            this.set(this.dark ? 'light' : 'dark');
        },
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedTheme() === 'system') {
            Alpine.store('theme').dark = applyTheme('system');
        }
    });
}
