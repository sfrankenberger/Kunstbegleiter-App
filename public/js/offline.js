/**
 * Offline-Warteschlange fuer Aufnahmen (docs/konzept.md Abschnitt 25, Sebastian 14.10.2026): Fotos landen zuerst im
 * Handy-Speicher (IndexedDB) und gehen dann an POST /aufnahmen. Bricht das Netz ab, bleiben sie liegen und werden
 * nachgesendet, sobald das Handy wieder online ist (Ereignis "online", alle 20 Sekunden, beim Oeffnen jeder Seite).
 * Jede Aufnahme traegt eine Handy-ID, der Server legt sie hoechstens einmal an. Alpine-Store "net" fuer die Anzeige.
 */
(function () {
    const DB = 'kunstbegleiter', STORE = 'uploads';
    let flushing = null;

    function open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB, 1);
            req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' });
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function run(mode, fn) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, mode);
            const req = fn(tx.objectStore(STORE));
            tx.oncomplete = () => resolve(req ? req.result : undefined);
            tx.onerror = () => reject(tx.error);
        });
    }

    const all = () => run('readonly', s => s.getAll()).then(r => (r || []).sort((a, b) => a.createdAt - b.createdAt));
    const remove = id => run('readwrite', s => s.delete(id));

    function store() { return window.Alpine ? Alpine.store('net') : null; }

    async function refreshCount() {
        try { const n = (await all()).length; if (store()) store().pending = n; return n; } catch (e) { return 0; }
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    // Fotos als ArrayBuffer speichern (sicherer als Blobs in aelteren iOS-Versionen)
    async function add(data) {
        const photos = [];
        for (const blob of data.blobs) photos.push({ type: blob.type || 'image/jpeg', name: blob.name || 'foto.jpg', buffer: await blob.arrayBuffer() });
        const entry = { id: uuid(), createdAt: Date.now(), photos, full: !!data.full, roomTextId: data.roomTextId || null, visitId: data.visitId || null };
        await run('readwrite', s => s.put(entry));
        await refreshCount();
        return entry;
    }

    function token() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Ergebnis: { url } bei Erfolg, { error } bei endgueltiger Ablehnung (Eintrag weg), null = spaeter noch einmal
    async function send(entry) {
        const body = new FormData();
        body.append('client_id', entry.id);
        body.append('full', entry.full ? '1' : '0');
        if (entry.roomTextId) body.append('room_text_id', entry.roomTextId);
        if (entry.visitId) body.append('visit_id', entry.visitId);
        entry.photos.forEach((p, i) => body.append('photos[]', new File([p.buffer], p.name || ('foto-' + (i + 1) + '.jpg'), { type: p.type })));

        let response;
        try {
            response = await fetch('/aufnahmen', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' } });
        } catch (e) {
            return null; // kein Netz
        }

        if (response.ok) {
            const json = await response.json().catch(() => ({}));
            await remove(entry.id);
            return { url: json.url || '/jetzt' };
        }

        if (response.status === 422) {
            const json = await response.json().catch(() => ({}));
            await remove(entry.id);
            return { error: json.message || 'Die Aufnahme wurde abgelehnt.' };
        }

        return null; // 419 (Seite veraltet), 401, 5xx, Zeitueberschreitung: spaeter noch einmal
    }

    // Alles der Reihe nach senden. Liefert das Ergebnis fuer onlyId, falls angegeben.
    async function flush(onlyId) {
        if (flushing) { await flushing; }
        let result = null;
        flushing = (async () => {
            const net = store();
            if (net) net.sending = true;
            try {
                for (const entry of await all()) {
                    const r = await send(entry);
                    if (entry.id === onlyId) result = r;
                    if (r && r.url && entry.id !== onlyId && net) net.sent = r.url;
                    if (r && r.error && net) net.error = r.error;
                    if (r === null) break; // Netz weg: Rest spaeter
                }
            } finally {
                if (net) net.sending = false;
                await refreshCount();
            }
        })();
        await flushing;
        flushing = null;
        return result;
    }

    window.KunstOffline = { add, flush, all, refreshCount };

    document.addEventListener('alpine:init', () => {
        Alpine.store('net', { online: navigator.onLine, pending: 0, sending: false, sent: '', error: '' });
        window.addEventListener('online', () => { Alpine.store('net').online = true; flush(); });
        window.addEventListener('offline', () => { Alpine.store('net').online = false; });
        refreshCount().then(n => { if (n > 0 && navigator.onLine) flush(); });
        setInterval(() => { const net = Alpine.store('net'); if (net.pending > 0 && ! net.sending) flush(); }, 20000);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refreshCount().then(n => { if (n > 0) flush(); }); });
    });
})();
