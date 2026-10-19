/**
 * Globaler Audio-Player (docs/konzept.md Abschnitt 22): Alpine-Store, Markup im Layout unter @persist, bleibt bei
 * wire:navigate erhalten und spielt beim Seitenwechsel (Werk, Kuenstler, Archiv) weiter. Die Werk-Seite ruft
 * $store.player.load(...) auf; derselbe Guide wird nicht neu geladen. Sperrbildschirm ueber die Media Session API,
 * Ausgabe (AirPods, Lautsprecher) ueber den iOS-Routenwaehler des Audio-Elements.
 * Offline (14.10.2026): jedes geladene Audio wird im Hintergrund im Handy-Speicher (Cache Storage "kb-audio",
 * hoechstens 40 Guides) abgelegt und beim naechsten Mal von dort gespielt, auch ohne Netz.
 */
document.addEventListener('alpine:init', () => {
    // Lightbox: ein Foto gross ueber allem (Archiv, Aufnahme), Tipp schliesst
    Alpine.store('lightbox', {
        src: '',
        open(src) { this.src = src; },
        close() { this.src = ''; },
    });

    Alpine.store('player', {
        el: null, src: '', remote: '', error: '', key: null, title: '', artist: '', href: '', label: '', playing: false, rate: 1, position: 0, duration: 0, canRoute: false,
        attach(el) {
            if (this.el === el) return;
            this.el = el;
            this.canRoute = typeof el.webkitShowPlaybackTargetPicker === 'function';
            el.addEventListener('timeupdate', () => { this.position = el.currentTime });
            el.addEventListener('loadedmetadata', () => { if (isFinite(el.duration)) this.duration = el.duration });
            el.addEventListener('play', () => { this.playing = true; this.error = '' });
            // Datei aus dem Handy-Speicher kaputt oder nicht lesbar: einmal direkt vom Server laden
            el.addEventListener('error', () => { if (this.remote && el.src !== this.remote) { el.src = this.remote; el.load() } });
            el.addEventListener('pause', () => { this.playing = false });
            el.addEventListener('ended', () => { this.playing = false });
            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => el.play());
                navigator.mediaSession.setActionHandler('pause', () => el.pause());
                navigator.mediaSession.setActionHandler('seekbackward', () => this.back());
            }
        },
        objectUrl: null,
        async load(guide) {
            if (this.key === guide.key && this.src) return;
            this.key = guide.key; this.label = guide.label || ''; this.title = guide.title || 'Audioguide'; this.artist = guide.artist || ''; this.href = guide.href || '';
            this.duration = guide.duration || 0; this.position = 0; this.playing = false; this.error = ''; this.remote = guide.src;
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({ title: (this.label ? this.label + ': ' : '') + this.title, artist: this.artist, album: 'Kunstbegleiter' });
            }
            let src = guide.src;
            const cached = await this.cached(guide.key);
            if (this.key !== guide.key) return; // inzwischen anderer Guide gewaehlt
            if (this.objectUrl) { URL.revokeObjectURL(this.objectUrl); this.objectUrl = null; }
            if (cached) { this.objectUrl = src = URL.createObjectURL(cached); } else { setTimeout(() => this.keep(guide), 4000); }
            this.src = src;
            if (this.el) { this.el.src = src; this.el.playbackRate = this.rate; this.el.load(); }
        },
        cacheKey(key) { return '/offline-audio/' + key; },
        async cached(key) {
            try {
                if (! ('caches' in window)) return null;
                const hit = await (await caches.open('kb-audio')).match(this.cacheKey(key));
                return hit ? await hit.blob() : null;
            } catch (e) { return null; }
        },
        async keep(guide) {
            try {
                if (! ('caches' in window) || ! navigator.onLine) return;
                const cache = await caches.open('kb-audio');
                if (await cache.match(this.cacheKey(guide.key))) return;
                const response = await fetch(guide.src, { credentials: 'same-origin' });
                if (! response.ok) return;
                await cache.put(this.cacheKey(guide.key), new Response(await response.blob(), { headers: { 'Content-Type': 'audio/mpeg' } }));
                const keys = await cache.keys();
                for (const old of keys.slice(0, Math.max(0, keys.length - 40))) await cache.delete(old);
            } catch (e) {}
        },
        // Spielt nicht (iOS lehnt ab, Datei noch nicht bereit): neu laden und einmal nochmal, sonst Hinweis im Player
        toggle() {
            if (! this.el) return;
            if (this.playing) { this.el.pause(); return; }
            this.error = '';
            this.el.play().catch(() => {
                if (this.remote) this.el.src = this.remote;
                this.el.load();
                this.el.play().catch(() => { this.error = 'Abspielen hat nicht geklappt. Bitte nochmal tippen.' });
            });
        },
        back() { if (this.el) this.el.currentTime = Math.max(0, this.el.currentTime - 15) },
        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; if (this.el) this.el.playbackRate = this.rate },
        route() { try { this.el.webkitShowPlaybackTargetPicker() } catch (e) {} },
        seek(value) { if (this.el) this.el.currentTime = value },
        close() { if (this.el) { this.el.pause(); this.el.removeAttribute('src'); this.el.load() } this.src = ''; this.key = null; this.playing = false },
        time(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') }
    });
});
