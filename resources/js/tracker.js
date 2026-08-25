/**
 * Lightweight session tracking for the public site.
 *
 * Page views are recorded server-side (see TrackVisit middleware). This module
 * only extends the session while a visitor lingers on a page and finalises the
 * last known activity when they leave, so the admin can measure time-on-page.
 */
const HEARTBEAT_URL = '/track/heartbeat';
const HEARTBEAT_INTERVAL_MS = 25000;

let timer = null;

function heartbeat() {
    if (document.visibilityState === 'visible') {
        fetch(HEARTBEAT_URL, {
            method: 'POST',
            keepalive: true,
        }).catch(() => {});
    }
}

function leave() {
    if (navigator.sendBeacon) {
        navigator.sendBeacon(HEARTBEAT_URL);
    } else {
        heartbeat();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    timer = setInterval(heartbeat, HEARTBEAT_INTERVAL_MS);
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        leave();
        clearInterval(timer);
    } else if (timer === null) {
        timer = setInterval(heartbeat, HEARTBEAT_INTERVAL_MS);
    }
});

window.addEventListener('pagehide', () => {
    leave();
    clearInterval(timer);
});
