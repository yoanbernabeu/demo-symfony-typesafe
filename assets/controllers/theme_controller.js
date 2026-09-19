import { Controller } from '@hotwired/stimulus';

/*
 * Switches between the light and the dark theme. The choice is remembered, and
 * the system preference is used until one is made.
 */
export default class extends Controller {
    connect() {
        let stored = null;
        try {
            stored = localStorage.getItem('theme');
        } catch {
            // Storage can be unavailable (private window, blocked site data): the system preference still applies
        }

        this.apply(stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    toggle() {
        const dark = !document.documentElement.classList.contains('dark');
        this.apply(dark);

        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        } catch {
            // Not remembered, which is fine
        }
    }

    apply(dark) {
        document.documentElement.classList.toggle('dark', dark);
    }
}
