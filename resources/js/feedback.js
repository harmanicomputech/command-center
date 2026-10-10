// Touch feedback and navigation feedback, so nobody taps twice.
//
//  - Pressed state: anything tappable gets [data-pressed] the moment a
//    finger lands (and a soft ripple), since iOS Safari shows nothing by
//    default and only applies :active when the page listens for touches.
//  - Navigation: a thin progress bar while the next page loads; a tapped
//    tab becomes the current tab straight away.
//  - Forms: the submit button shows a spinner, and a second submit is
//    blocked until the page changes (or comes back from the back button).
const PRESSABLE = '.btn, .tab, .tab-hero, .card-interactive, .nav-item, .menu-item, .more-tile, .segmented label, [data-pressable]';
const RIPPLE = '.btn, .tab, .card-interactive, .nav-item, .menu-item, .more-tile, [data-pressable]';
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function registerFeedback() {
    // iOS Safari applies :active styles only when a touch listener exists.
    document.addEventListener('touchstart', () => {}, { passive: true });

    pressedStates();
    navigationProgress();
    formSubmits();
}

function pressedStates() {
    let pressed = null;
    let release = null;

    const clear = () => {
        if (!pressed) return;
        const element = pressed;
        pressed = null;
        // Keep the state long enough to be seen on a quick tap.
        release = setTimeout(() => element.removeAttribute('data-pressed'), 140);
    };

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (event.button !== 0) return;
            const element = event.target.closest(PRESSABLE);
            if (!element || element.matches(':disabled, [aria-disabled="true"]')) return;

            clearTimeout(release);
            pressed?.removeAttribute('data-pressed');
            pressed = element;
            element.setAttribute('data-pressed', '');

            if (!reducedMotion() && element.matches(RIPPLE)) {
                ripple(element, event);
            }
        },
        { passive: true },
    );

    document.addEventListener('pointerup', clear, { passive: true });
    document.addEventListener('pointercancel', clear, { passive: true });
    // Scrolling a list isn't a tap.
    window.addEventListener('scroll', clear, { passive: true, capture: true });
}

function ripple(element, event) {
    const rect = element.getBoundingClientRect();
    if (getComputedStyle(element).position === 'static') {
        element.style.position = 'relative';
    }

    // A clipping layer, so the element itself keeps its own overflow.
    const layer = document.createElement('span');
    layer.className = 'ripple-layer';
    const wave = document.createElement('span');
    wave.className = 'ripple';
    const size = Math.max(rect.width, rect.height) * 2.2;
    wave.style.width = wave.style.height = `${size}px`;
    wave.style.left = `${event.clientX - rect.left - size / 2}px`;
    wave.style.top = `${event.clientY - rect.top - size / 2}px`;
    layer.appendChild(wave);
    element.appendChild(layer);
    wave.addEventListener('animationend', () => layer.remove(), { once: true });
    setTimeout(() => layer.remove(), 900);
}

let bar = null;
let stopTimer = null;

export function startProgress() {
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'nav-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
    }
    bar.classList.remove('nav-progress-done');
    // Restart the animation.
    bar.classList.remove('nav-progress-active');
    void bar.offsetWidth;
    bar.classList.add('nav-progress-active');
    document.documentElement.setAttribute('aria-busy', 'true');

    // A download or a blocked navigation never unloads the page.
    clearTimeout(stopTimer);
    stopTimer = setTimeout(stopProgress, 12000);
}

export function stopProgress() {
    clearTimeout(stopTimer);
    document.documentElement.removeAttribute('aria-busy');
    if (bar?.classList.contains('nav-progress-active')) {
        bar.classList.add('nav-progress-done');
        setTimeout(() => bar?.classList.remove('nav-progress-active', 'nav-progress-done'), 400);
    }
    document.querySelectorAll('[data-loading]').forEach((element) => {
        element.removeAttribute('data-loading');
        element.removeAttribute('aria-busy');
    });
    document.querySelectorAll('form[data-submitting]').forEach((form) => form.removeAttribute('data-submitting'));
}

function navigationProgress() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-progress')) return;
        if (link.target && link.target !== '_self') return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        // Same page, different anchor: no load.
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        // The tapped tab is current straight away.
        const tab = link.closest('.tabbar') ? link : null;
        if (tab) {
            tab.closest('.tabbar').querySelectorAll('[aria-current]').forEach((other) => other.removeAttribute('aria-current'));
            tab.setAttribute('aria-current', 'page');
        }
        link.setAttribute('data-loading', '');
        startProgress();
    });

    // Back/forward from the browser's cache: nothing is loading any more.
    window.addEventListener('pageshow', (event) => event.persisted && stopProgress());
}

function formSubmits() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.hasAttribute('data-submitting')) {
            // Already on its way: don't send it twice.
            event.preventDefault();
            return;
        }

        // Let the form's own handlers decide first (confirm(), Alpine, the outbox).
        setTimeout(() => {
            if (event.defaultPrevented || form.target === '_blank' || form.hasAttribute('data-download')) return;
            form.setAttribute('data-submitting', '');
            const button = event.submitter || form.querySelector('[type="submit"], button:not([type])');
            if (button) {
                button.setAttribute('data-loading', '');
                button.setAttribute('aria-busy', 'true');
            }
            startProgress();
        });
    });
}
