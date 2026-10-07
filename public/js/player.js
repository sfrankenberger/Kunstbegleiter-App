/**
 * Globaler Audio-Player (docs/konzept.md Abschnitt 22): Alpine-Store, Markup im Layout unter @persist, bleibt bei
 * wire:navigate erhalten und spielt beim Seitenwechsel (Werk, Kuenstler, Archiv) weiter. Die Werk-Seite ruft
 * $store.player.load(...) auf; derselbe Guide wird nicht neu geladen. Sperrbildschirm ueber die Media Session API,
 * Ausgabe (AirPods, Lautsprecher) ueber den iOS-Routenwaehler des Audio-Elements.
 */
document.addEventListener('alpine:init', () => {
    // Lightbox: ein Foto gross ueber allem (Archiv, Aufnahme), Tipp schliesst
    Alpine.store('lightbox', {
        src: '',
        open(src) { this.src = src; },
        close() { this.src = ''; },
    });

    Alpine.store('player', {
        el: null, src: '', key: null, title: '', artist: '', href: '', playing: false, rate: 1, position: 0, duration: 0, canRoute: false,
        attach(el) {
            if (this.el === el) return;
            this.el = el;
            this.canRoute = typeof el.webkitShowPlaybackTargetPicker === 'function';
            el.addEventListener('timeupdate', () => { this.position = el.currentTime });
            el.addEventListener('loadedmetadata', () => { if (isFinite(el.duration)) this.duration = el.duration });
            el.addEventListener('play', () => { this.playing = true });
            el.addEventListener('pause', () => { this.playing = false });
            el.addEventListener('ended', () => { this.playing = false });
            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => el.play());
                navigator.mediaSession.setActionHandler('pause', () => el.pause());
                navigator.mediaSession.setActionHandler('seekbackward', () => this.back());
            }
        },
        load(guide) {
            if (this.key === guide.key && this.src) return;
            this.key = guide.key; this.title = guide.title || 'Audioguide'; this.artist = guide.artist || ''; this.href = guide.href || '';
            this.duration = guide.duration || 0; this.position = 0; this.playing = false; this.src = guide.src;
            if (this.el) { this.el.src = guide.src; this.el.playbackRate = this.rate; this.el.load(); }
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({ title: this.title, artist: this.artist, album: 'Kunstbegleiter' });
            }
        },
        toggle() { if (! this.el) return; this.playing ? this.el.pause() : this.el.play() },
        back() { if (this.el) this.el.currentTime = Math.max(0, this.el.currentTime - 15) },
        speed() { const rates = [0.8, 1, 1.2, 1.5]; this.rate = rates[(rates.indexOf(this.rate) + 1) % rates.length]; if (this.el) this.el.playbackRate = this.rate },
        route() { try { this.el.webkitShowPlaybackTargetPicker() } catch (e) {} },
        seek(value) { if (this.el) this.el.currentTime = value },
        close() { if (this.el) { this.el.pause(); this.el.removeAttribute('src'); this.el.load() } this.src = ''; this.key = null; this.playing = false },
        time(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') }
    });
});
