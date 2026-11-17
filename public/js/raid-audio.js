/**
 * Raid audio for the match screens.
 *
 * A raid has a 30-second bed that starts with the clock and stops when the raid
 * ends. There is more than one bed: an ordinary raid gets the countdown, and a
 * Do or Die raid gets its own recording. So this holds a NAMED SET of clips and
 * plays one of them, rather than a single file.
 *
 * Only one plays at a time, because only one raid is ever running — starting a
 * clip stops whatever was already going. That also covers the operator pressing
 * Raid In and then Do or Die, or the other side's Raid In mid-raid.
 *
 * Sync: if a screen is opened or reloaded mid-raid, playback is seeked to how
 * far into the raid we already are, so the audio lines up with the number on
 * screen instead of restarting from the top.
 *
 * Browsers refuse to start audio before a user gesture. OBS's browser source
 * allows it; a normal tab does not, so arm() is exposed for a click handler and
 * isBlocked() lets the page show a prompt. Arming unlocks every clip, since the
 * gesture that unlocks one is not repeated before the next raid.
 *
 * Backwards compatible: configure({sources: [...]}) still defines a single
 * unnamed bed, and start()/play()/stop()/status() still work without a name.
 */
window.RaidAudio = (function () {

    var clips = {};             // name -> clip record
    var order = [];             // definition order; the first is the default
    var playingName = null;

    var raidSeconds = 30;       // overwritten from the match state
    var enabled = true;
    var volume = 0.85;
    var blocked = false;

    var DEFAULT = 'default';

    /* ── Clip plumbing ──────────────────────────────────── */

    function define(name, sources) {
        if (!sources || !sources.length) { return; }

        if (!clips[name]) { order.push(name); }

        clips[name] = {
            name: name,
            sources: sources.slice(),   // tried in order; first that decodes wins
            index: 0,
            el: null,
            ready: false,
            failed: false,
            playing: false,
            pendingSeek: null           // applied once metadata is known
        };
    }

    /** The named clip, or the first one defined. */
    function get(name) {
        if (name && clips[name]) { return clips[name]; }
        return order.length ? clips[order[0]] : null;
    }

    function element(c) {
        if (!c || c.el) { return c ? c.el : null; }

        c.el = new Audio();
        c.el.preload = 'auto';
        c.el.volume = volume;

        c.el.addEventListener('loadedmetadata', function () {
            c.ready = true;
            if (c.pendingSeek !== null) {
                seek(c, c.pendingSeek);
                c.pendingSeek = null;
            }
        });

        /* Fall through the source list. Browsers differ on WAV bit depth —
           Chrome will not decode 24-bit PCM, for instance — so an undecodable
           file moves on to the next candidate instead of killing the audio. */
        c.el.addEventListener('error', function () {
            var failedSrc = c.sources[c.index];
            c.index++;

            if (c.index < c.sources.length) {
                console.warn('[RaidAudio] ' + failedSrc + ' would not load (code '
                    + ((c.el.error && c.el.error.code) || '?') + '); trying ' + c.sources[c.index]);
                c.el.src = c.sources[c.index];
                c.el.load();
                return;
            }

            c.failed = true;
            console.error('[RaidAudio] None of the files for "' + c.name
                + '" could be loaded: ' + c.sources.join(', ') + '. That bed is off.');
        });

        c.el.addEventListener('ended', function () {
            c.playing = false;
            if (playingName === c.name) { playingName = null; }
        });

        c.el.src = c.sources[0];
        return c.el;
    }

    function seek(c, seconds) {
        if (!c || !c.el) { return; }

        var max = isFinite(c.el.duration) ? c.el.duration : null;
        var target = Math.max(0, seconds);

        if (max !== null) {
            if (target >= max) { return false; }     // past the end of the bed
            target = Math.min(target, max - 0.05);
        }

        try { c.el.currentTime = target; } catch (e) { /* not seekable yet */ }
        return true;
    }

    function halt(c) {
        if (!c) { return; }
        c.playing = false;
        if (!c.el) { return; }
        try {
            c.el.pause();
            c.el.currentTime = 0;
        } catch (e) { /* nothing to reset */ }
    }

    /* ── Playback ───────────────────────────────────────── */

    /**
     * Start a bed. secondsLeft is the raid clock at the moment the raid was
     * observed, so a mid-raid load resumes at the matching offset.
     */
    function start(secondsLeft, name) {
        if (!enabled) { return; }

        var c = get(name);
        if (!c || c.failed) { return; }

        // One raid, one bed: silence anything already running, including this
        // clip, so a second press restarts it rather than layering.
        order.forEach(function (n) { if (clips[n] !== c) { halt(clips[n]); } });

        var a = element(c);
        if (!a) { return; }

        var offset = Math.max(0, raidSeconds - secondsLeft);

        if (a.readyState < 1) {
            c.pendingSeek = offset;                  // seek once metadata arrives
        } else if (seek(c, offset) === false) {
            return;                                  // nothing of the bed left
        }

        var p = a.play();
        c.playing = true;
        playingName = c.name;

        if (p && p.catch) {
            p.catch(function (err) {
                c.playing = false;
                if (playingName === c.name) { playingName = null; }

                if (err && err.name === 'NotAllowedError') {
                    blocked = true;
                } else {
                    console.warn('[RaidAudio] Playback refused:', err);
                }
            });
        }
    }

    function stop() {
        order.forEach(function (n) { halt(clips[n]); });
        playingName = null;
    }

    /** Call from a user gesture to lift the autoplay block on every clip. */
    function arm() {
        if (!order.length) { return false; }

        order.forEach(function (n) {
            var c = clips[n];
            if (c.failed || c.playing) { return; }

            var a = element(c);
            if (!a) { return; }

            // play() then immediately pause() is the standard unlock.
            var p = a.play();
            if (p && p.then) {
                p.then(function () {
                    if (!c.playing) { halt(c); }
                    blocked = false;
                }).catch(function () { blocked = true; });
            } else {
                if (!c.playing) { halt(c); }
                blocked = false;
            }
        });

        return !blocked;
    }

    /**
     * One word describing where the audio is, for an on-page readout.
     *
     * With no name it reports the worst state across every bed, so a single
     * badge still tells an operator that something will not play — naming a
     * clip asks about just that one.
     */
    function status(name) {
        if (!enabled) { return 'off'; }
        if (!order.length) { return 'failed'; }

        var names = (name && clips[name]) ? [name] : order;
        var anyFailed = false, anyPlaying = false, allReady = true;

        names.forEach(function (n) {
            var c = clips[n];
            if (c.failed) { anyFailed = true; }
            if (c.playing) { anyPlaying = true; }
            if (!c.ready) { allReady = false; }
        });

        if (anyFailed) { return 'failed'; }
        if (blocked) { return 'blocked'; }
        if (anyPlaying) { return 'playing'; }
        return allReady ? 'ready' : 'loading';
    }

    return {
        /**
         * configure({sources: [...]})                  one unnamed bed
         * configure({clips: {countdown: [...], ...}})   several named beds
         */
        configure: function (options) {
            options = options || {};

            if (options.clips) {
                Object.keys(options.clips).forEach(function (name) {
                    define(name, options.clips[name]);
                });
            }

            if (options.sources && options.sources.length) {
                define(DEFAULT, options.sources);
            } else if (options.src) {
                define(DEFAULT, [options.src]);
            }

            if (options.raidSeconds > 0) { raidSeconds = options.raidSeconds; }
            if (options.volume !== undefined) { this.setVolume(options.volume); }

            // Start buffering straight away so the first raid is not late.
            order.forEach(function (n) { element(clips[n]); });
        },

        status: status,
        clipNames: function () { return order.slice(); },
        currentSource: function (name) {
            var c = get(name);
            return c ? (c.sources[c.index] || null) : null;
        },

        /**
         * Play a bed from the top — what a "Raid In" or "Do or Die" click
         * wants. Calling it while something is playing rewinds, so starting a
         * raid mid-raid restarts the bed rather than letting it run on.
         */
        play: function (name) { start(raidSeconds, name); },

        start: start,
        stop: stop,
        arm: arm,
        isBlocked: function () { return blocked; },
        isPlaying: function (name) {
            var c = get(name);
            return c ? c.playing : false;
        },
        playingClip: function () { return playingName; },
        hasFailed: function (name) {
            var c = get(name);
            return c ? c.failed : false;
        },
        isSupported: function () { return typeof Audio !== 'undefined'; },
        setEnabled: function (on) {
            enabled = !!on;
            if (!enabled) { stop(); }
        },
        isEnabled: function () { return enabled; },
        setVolume: function (v) {
            volume = Math.max(0, Math.min(1, Number(v) || 0));
            order.forEach(function (n) {
                if (clips[n].el) { clips[n].el.volume = volume; }
            });
        }
    };
})();
