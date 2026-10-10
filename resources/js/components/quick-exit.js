/**
 * Quick exit: leave immediately for a neutral page.
 *
 * Order matters: the visible page is cleared and navigation starts first;
 * ending the server session happens in the background via sendBeacon so
 * nothing slows the exit down. location.replace() means the Back button
 * does not return here, and pages are sent with Cache-Control: no-store so
 * the browser does not redisplay them from cache.
 *
 * This cannot erase browser history entries from earlier pages, server
 * logs or network records; the site explains that honestly.
 */
export function quickExit() {
    return {
        exit() {
            const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content');
            const target = meta('aisa-quick-exit') || 'https://www.google.com/';

            try {
                const data = new FormData();
                data.append('_token', meta('csrf-token') || '');
                navigator.sendBeacon?.(meta('aisa-quick-exit-endpoint'), data);
            } catch (e) {
                // Exiting matters more than the beacon.
            }

            try {
                sessionStorage.clear();
            } catch (e) {
                // Storage may be unavailable.
            }

            document.title = '';
            document.body.replaceChildren();
            window.location.replace(target);
        },
    };
}
