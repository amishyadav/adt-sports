/**
 * Repaint ticker for the live match screens.
 *
 * Runs inside a Web Worker on purpose. A main-thread setInterval is throttled
 * hard once its page is hidden OR merely occluded by another window — Chrome
 * drops it to roughly 1Hz and, after a few minutes, about once a minute. On a
 * venue display or a second monitor sitting behind the operator's window that
 * made the match clock visibly lag, even though scores (pushed over the socket)
 * still arrived at once.
 *
 * Worker timers are not subject to that intensive throttling, so the board
 * keeps repainting at full rate regardless of window stacking.
 *
 * Served as a same-origin file rather than a blob: URL so it satisfies the
 * app's `default-src 'self'` CSP without widening worker-src.
 */
var interval = 250;
var handle = null;

function start() {
    stop();
    handle = setInterval(function () {
        postMessage(Date.now());
    }, interval);
}

function stop() {
    if (handle !== null) {
        clearInterval(handle);
        handle = null;
    }
}

self.onmessage = function (event) {
    var data = event.data || {};

    if (data.cmd === 'start') {
        if (data.interval > 0) {
            interval = data.interval;
        }
        start();
        return;
    }

    if (data.cmd === 'stop') {
        stop();
    }
};
