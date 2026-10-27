/**
 * Raid countdown audio for the live score overlay.
 *
 * Plays a supplied countdown recording for the length of a raid, started the
 * moment the 30-second raid clock begins and stopped as soon as the raid ends.
 * (An earlier version synthesised a drum pattern; this plays the real bed.)
 *
 * Sync: if the overlay is opened or reloaded mid-raid, playback is seeked to
 * how far into the raid we already are, so the audio lines up with the number
 * on screen instead of restarting from the top.
 *
 * Browsers refuse to start audio before a user gesture. OBS's browser source
 * allows it; a normal tab does not, so arm() is exposed for a click handler and
 * isBlocked() lets the page show a prompt.
 */
window.RaidAudio = (function () {

    var sources = [];           // tried in order; first one that decodes wins
    var index = 0;
    var raidSeconds = 30;       // overwritten from the match state
    var el = null;
    var enabled = true;
    var volume = 0.85;
    var blocked = false;
    var failed = false;
    var ready = false;
    var pendingSeek = null;     // applied once metadata is known
    var playing = false;

    function element() {
        if (el || !sources.length) { return el; }

        el = new Audio();
        el.preload = 'auto';
        el.volume = volume;

        el.addEventListener('loadedmetadata', function () {
            ready = true;
            if (pendingSeek !== null) {
                seek(pendingSeek);
                pendingSeek = null;
            }
        });

        /* Fall through the source list. Browsers differ on WAV bit depth —
           Chrome will not decode 24-bit PCM, for instance — so an undecodable
           file moves on to the next candidate instead of killing the audio. */
        el.addEventListener('error', function () {
            var failedSrc = sources[index];
            index++;

            if (index < sources.length) {
                console.warn('[RaidAudio] ' + failedSrc + ' would not load (code '
                    + ((el.error && el.error.code) || '?') + '); trying ' + sources[index]);
                el.src = sources[index];
                el.load();
                return;
            }

            failed = true;
            console.error('[RaidAudio] None of the countdown files could be loaded: '
                + sources.join(', ') + '. Countdown audio is off.');
        });

        el.addEventListener('ended', function () { playing = false; });

        el.src = sources[0];
        return el;
    }

    function seek(seconds) {
        if (!el) { return; }
        var max = isFinite(el.duration) ? el.duration : null;
        var target = Math.max(0, seconds);
        if (max !== null) {
            if (target >= max) { return false; }   // past the end of the bed
            target = Math.min(target, max - 0.05);
        }
        try { el.currentTime = target; } catch (e) { /* not seekable yet */ }
        return true;
    }

    function isBlocked() { return blocked; }

    /** Call from a user gesture to lift the autoplay block. */
    function arm() {
        var a = element();
        if (!a || failed) { return false; }

        // play() then immediately pause() is the standard unlock.
        var p = a.play();
        if (p && p.then) {
            p.then(function () {
                if (!playing) { a.pause(); seek(0); }
                blocked = false;
            }).catch(function () { blocked = true; });
        } else {
            if (!playing) { a.pause(); seek(0); }
            blocked = false;
        }
        return !blocked;
    }

    /**
     * Start the countdown bed. secondsLeft is the raid clock at the moment the
     * raid was observed, so a mid-raid load resumes at the matching offset.
     */
    function start(secondsLeft) {
        if (!enabled || failed) { return; }

        var a = element();
        if (!a) { return; }

        var offset = Math.max(0, raidSeconds - secondsLeft);

        if (a.readyState < 1) {
            pendingSeek = offset;               // seek once metadata arrives
        } else if (seek(offset) === false) {
            return;                             // nothing of the bed left to play
        }

        var p = a.play();
        playing = true;
        if (p && p.catch) {
            p.catch(function (err) {
                playing = false;
                if (err && err.name === 'NotAllowedError') {
                    blocked = true;
                } else {
                    console.warn('[RaidAudio] Playback refused:', err);
                }
            });
        }
    }

    function stop() {
        playing = false;
        if (!el) { return; }
        try {
            el.pause();
            el.currentTime = 0;
        } catch (e) { /* nothing to reset */ }
    }

    /** One word describing where the audio is, for an on-page readout. */
    function status() {
        if (!enabled) { return 'off'; }
        if (failed) { return 'failed'; }
        if (blocked) { return 'blocked'; }
        if (playing) { return 'playing'; }
        return ready ? 'ready' : 'loading';
    }

    return {
        configure: function (options) {
            options = options || {};

            if (options.sources && options.sources.length) {
                sources = options.sources.slice();
            } else if (options.src) {
                sources = [options.src];
            }

            if (options.raidSeconds > 0) { raidSeconds = options.raidSeconds; }
            if (options.volume !== undefined) { this.setVolume(options.volume); }
            if (sources.length) { element(); }   // start buffering straight away
        },
        status: status,
        currentSource: function () { return sources[index] || null; },
        start: start,
        stop: stop,
        arm: arm,
        isBlocked: isBlocked,
        isPlaying: function () { return playing; },
        hasFailed: function () { return failed; },
        isSupported: function () { return typeof Audio !== 'undefined'; },
        setEnabled: function (on) {
            enabled = !!on;
            if (!enabled) { stop(); }
        },
        isEnabled: function () { return enabled; },
        setVolume: function (v) {
            volume = Math.max(0, Math.min(1, Number(v) || 0));
            if (el) { el.volume = volume; }
        }
    };
})();
