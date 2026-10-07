<div class="flex flex-col gap-4">
    @if ($visit)
        <div class="kb-card">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-stone-500">Aktueller Besuch</p>
                    <p class="mt-1 text-lg font-semibold">{{ $visit->museum?->name ?? 'Museum noch nicht bestimmt' }}</p>
                    <p class="text-sm text-stone-600">{{ $visit->city?->name ? $visit->city->name.' · ' : '' }}noch {{ $visit->remainingMinutes() }} Minuten</p>
                </div>
                <button type="button" class="text-sm font-medium text-stone-500" wire:click="endVisit" wire:confirm="Besuch beenden?">Beenden</button>
            </div>
            @if (! $visit->museum)
                <button type="button" class="mt-3 text-sm font-medium text-accent" wire:click="$set('showManual', true)">Museum eintragen</button>
            @endif
        </div>

        {{-- Aufnahme: 1 bis 3 Fotos, im Browser verkleinert (lange Kante {{ $maxEdge }} px), dann Livewire-Upload --}}
        <div
            class="kb-card flex flex-col gap-3"
            x-data="{
                busy: false, previews: [], blobs: [], max: {{ $maxPhotos }}, edge: {{ $maxEdge }},
                // Die Kamera liefert je Ausloesung ein Foto: neue Fotos kommen zu den bisherigen dazu (bis max)
                async pick(event) {
                    const files = Array.from(event.target.files || []).slice(0, this.max - this.blobs.length);
                    event.target.value = '';
                    if (!files.length) return;
                    this.busy = true;
                    try {
                        const fresh = [];
                        for (const file of files) fresh.push(await this.shrink(file));
                        this.blobs.push(...fresh);
                        this.refreshPreviews();
                        // uploadMultiple haengt an (Livewire append = true): nur die neuen Dateien schicken, sonst
                        // landet jedes Foto doppelt (Sebastian, 07.10.2026: "Bild immer 2 mal angezeigt")
                        await new Promise((resolve, reject) => $wire.uploadMultiple('photos', fresh, resolve, reject));
                    } catch (e) {
                        $wire.set('uploadError', 'Das Foto konnte nicht verarbeitet werden.');
                    } finally {
                        this.busy = false;
                    }
                },
                async remove(i) {
                    this.blobs.splice(i, 1);
                    this.refreshPreviews();
                    this.busy = true;
                    try {
                        // Liste am Server neu aufbauen: leeren, dann die verbliebenen Fotos wieder hochladen
                        await $wire.set('photos', []);
                        if (this.blobs.length) await new Promise((resolve, reject) => $wire.uploadMultiple('photos', this.blobs, resolve, reject));
                    } finally {
                        this.busy = false;
                    }
                },
                refreshPreviews() {
                    this.previews.forEach(u => URL.revokeObjectURL(u));
                    this.previews = this.blobs.map(b => URL.createObjectURL(b));
                },
                shrink(file) {
                    return new Promise((resolve, reject) => {
                        const img = new Image();
                        const url = URL.createObjectURL(file);
                        img.onload = () => {
                            const scale = Math.min(1, this.edge / Math.max(img.width, img.height));
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.round(img.width * scale);
                            canvas.height = Math.round(img.height * scale);
                            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                            URL.revokeObjectURL(url);
                            canvas.toBlob(blob => blob ? resolve(new File([blob], (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })) : reject(new Error('toBlob')), 'image/jpeg', 0.85);
                        };
                        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
                        img.src = url;
                    });
                },
                clear() { this.blobs = []; this.previews = []; $wire.set('photos', []); },
                async pickRoom(event) {
                    const file = (event.target.files || [])[0];
                    event.target.value = '';
                    if (!file) return;
                    this.busy = true;
                    try {
                        const blob = await this.shrink(file);
                        await new Promise((resolve, reject) => $wire.upload('roomPhoto', blob, resolve, reject));
                        await $wire.scanRoomText();
                    } catch (e) {
                        $wire.set('roomTextError', 'Das Foto konnte nicht verarbeitet werden.');
                    } finally {
                        this.busy = false;
                    }
                },
            }"
        >
            <input type="file" accept="image/*" capture="environment" multiple class="hidden" x-ref="camera" x-on:change="pick($event)">
            <input type="file" accept="image/*" multiple class="hidden" x-ref="library" x-on:change="pick($event)">

            <template x-if="previews.length">
                <div class="flex gap-2">
                    <template x-for="(src, i) in previews" :key="src">
                        <div class="relative">
                            <img :src="src" alt="" class="h-20 w-20 rounded-lg object-cover">
                            <button type="button" class="absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-stone-800 text-xs text-white" x-on:click="remove(i)" x-bind:disabled="busy" aria-label="Foto entfernen">×</button>
                        </div>
                    </template>
                </div>
            </template>

            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="kb-button-secondary" x-on:click="$refs.camera.click()" x-bind:disabled="busy || blobs.length >= max" x-text="blobs.length ? 'Weiteres Foto' : 'Foto aufnehmen'"></button>
                <button type="button" class="kb-button-secondary" x-on:click="$refs.library.click()" x-bind:disabled="busy || blobs.length >= max">Aus Fotos wählen</button>
            </div>
            <p class="text-xs text-stone-500">1 bis {{ $maxPhotos }} Fotos: das Werk, dazu der Werktext. Den Raumtext einmal eigens scannen, er gilt dann für die nächsten Werke.</p>

            {{-- Raumtext: nur ablesen und merken (Sebastian, 07.10.2026), Auswahl als Kontext fuer die naechsten Aufnahmen --}}
            <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="room" x-on:change="pickRoom($event)">
            <div class="flex flex-wrap items-center gap-2 border-t border-stone-200 pt-3">
                <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" x-on:click="$refs.room.click()" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="scanRoomText, roomPhoto">Raumtext scannen</button>
                <span class="text-xs text-stone-500" wire:loading wire:target="scanRoomText, roomPhoto">Raumtext wird abgelesen ...</span>
                @foreach ($roomTexts as $roomText)
                    <button type="button" class="rounded-full px-3 py-1 text-xs {{ $roomTextId === $roomText->getKey() ? 'bg-accent text-white' : 'bg-stone-100 text-stone-700' }}" wire:click="toggleRoomText({{ $roomText->getKey() }})" wire:key="room-{{ $roomText->getKey() }}" title="{{ $roomText->excerpt() }}">{{ $roomTextId === $roomText->getKey() ? '✓ ' : '' }}{{ \Illuminate\Support\Str::limit($roomText->label(), 28) }}</button>
                @endforeach
            </div>
            @if ($roomTexts->isNotEmpty())
                <p class="text-xs text-stone-500">{{ $roomTextId ? 'Der markierte Raumtext fließt in die nächsten Aufnahmen ein.' : 'Raumtext antippen, damit er in die nächsten Aufnahmen einfließt.' }} Nachlesen im <a href="{{ route('archiv') }}" wire:navigate class="text-accent">Archiv</a>.</p>
            @endif
            @if ($roomTextError !== '')
                <p class="text-sm text-red-700">{{ $roomTextError }}</p>
            @endif

            <div wire:loading wire:target="photos" class="text-sm text-stone-600">Fotos werden hochgeladen ...</div>
            <p x-show="busy" x-cloak class="text-sm text-stone-600">Fotos werden verkleinert ...</p>

            @if ($uploadError !== '')
                <p class="text-sm text-red-700">{{ $uploadError }}</p>
            @endif

            @if ($photos !== [])
                <p class="text-sm text-stone-700">{{ count($photos) }} {{ count($photos) === 1 ? 'Foto' : 'Fotos' }} bereit.</p>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="full" class="h-5 w-5 rounded border-stone-300">
                    <span>Gleich ausführlich (Recherche und Studio-Stimme, dauert einige Minuten)</span>
                </label>
                <button type="button" class="kb-button h-14 text-lg" wire:click="createCapture" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createCapture">{{ $full ? 'Ausführlichen Guide erstellen' : 'Schnellen Überblick erstellen' }}</span>
                    <span wire:loading wire:target="createCapture">{{ $full ? 'Erkenne das Werk ...' : 'Erkenne das Werk und schreibe den Überblick ...' }}</span>
                </button>
                <button type="button" class="text-sm text-stone-500" x-on:click="clear">Fotos verwerfen</button>
            @endif
        </div>

        @if ($captures->isNotEmpty())
            <h2 class="mt-2 text-sm font-semibold text-stone-700">Werke dieses Besuchs</h2>
            <ul class="flex flex-col gap-2">
                @foreach ($captures as $capture)
                    <li>
                        <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="kb-card flex items-center gap-3">
                            @if ($capture->photos->first())
                                <img src="{{ $capture->photos->first()->url() }}" alt="" class="h-14 w-14 rounded-lg object-cover">
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $capture->artwork?->title ?? 'Aufnahme von '.$capture->created_at->format('H:i') }}</p>
                                <p class="text-sm text-stone-600">{{ $capture->artwork?->artist?->name ?? $capture->photos->count().' '.($capture->photos->count() === 1 ? 'Foto' : 'Fotos') }} · {{ $capture->progressLabel() }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <div
            class="kb-card flex flex-col gap-3"
            x-data="{
                busy: false,
                locate() {
                    if (!navigator.geolocation) { $wire.locationFailed('Dieses Gerät kennt keinen Standort.'); return; }
                    this.busy = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => { this.busy = false; $wire.locate(pos.coords.latitude, pos.coords.longitude, Math.round(pos.coords.accuracy || 0)); },
                        err => { this.busy = false; $wire.locationFailed(err.code === 1 ? 'Standort nicht erlaubt. Du kannst das Museum unten eintippen.' : 'Standort nicht gefunden. Du kannst das Museum unten eintippen.'); },
                        { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
                    );
                },
            }"
        >
            <p class="text-xs uppercase tracking-wide text-stone-500">Kein aktiver Besuch</p>
            <p class="text-sm text-stone-600">Wo bist du? Der Standort wird nur für die Suche verwendet, nicht als Spur gespeichert.</p>
            <button type="button" class="kb-button" x-on:click="locate" x-bind:disabled="busy">
                <span x-show="!busy">Museum in der Nähe suchen</span>
                <span x-show="busy" x-cloak>Standort wird gesucht ...</span>
            </button>
            <div wire:loading wire:target="locate" class="text-sm text-stone-600">Museen in der Nähe werden gesucht ...</div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="kb-button-secondary" wire:click="$set('showManual', true)">Museum eingeben</button>
                <button type="button" class="kb-button-secondary" wire:click="startWithoutMuseum" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="startWithoutMuseum">Ohne Museum</span>
                    <span wire:loading wire:target="startWithoutMuseum">Einen Moment ...</span>
                </button>
            </div>
        </div>
    @endif

    @if ($searched && $nearby !== [] && (! $visit || $showManual))
        <div class="kb-card flex flex-col gap-2">
            <p class="font-semibold">Wo bist du?</p>
            @foreach ($nearby as $place)
                <button type="button" class="kb-button-secondary justify-start text-left" wire:click="chooseMuseum('{{ $place['place_id'] }}')">
                    <span class="flex-1">{{ $place['name'] }}<span class="block text-xs font-normal text-stone-500">{{ $place['distance_m'] }} m{{ $place['address'] ? ' · '.$place['address'] : '' }}</span></span>
                </button>
            @endforeach
            <button type="button" class="text-sm font-medium text-accent" wire:click="$set('showManual', true)">Keines davon, ich bin im ...</button>
        </div>
    @endif

    @if ($locationError !== '')
        <p class="text-sm text-red-700">{{ $locationError }}</p>
    @endif

    @if (($searched && $nearby === []) || $showManual)
        <form wire:submit="chooseManual" class="kb-card flex flex-col gap-3">
            <p class="font-semibold">Ich bin im ...</p>
            <input type="text" wire:model="manualName" class="kb-input" placeholder="Name des Museums" required>
            @error('manualName') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <input type="text" wire:model="manualCity" class="kb-input" placeholder="Stadt (optional)">
            <button type="submit" class="kb-button">{{ $visit ? 'Museum setzen' : 'Besuch starten' }}</button>
            @if (! $visit)
                <button type="button" class="text-sm text-stone-500" wire:click="startWithoutMuseum">Ohne Museum starten</button>
            @endif
        </form>
    @endif
</div>
