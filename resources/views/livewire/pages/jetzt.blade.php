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
                busy: false, previews: [], max: {{ $maxPhotos }}, edge: {{ $maxEdge }},
                async pick(event) {
                    const files = Array.from(event.target.files || []).slice(0, this.max);
                    event.target.value = '';
                    if (!files.length) return;
                    this.busy = true;
                    try {
                        const blobs = [];
                        for (const file of files) blobs.push(await this.shrink(file));
                        this.previews = blobs.map(b => URL.createObjectURL(b));
                        await new Promise((resolve, reject) => $wire.uploadMultiple('photos', blobs, resolve, reject));
                    } catch (e) {
                        $wire.set('uploadError', 'Das Foto konnte nicht verarbeitet werden.');
                    } finally {
                        this.busy = false;
                    }
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
                clear() { this.previews = []; $wire.set('photos', []); },
            }"
        >
            <input type="file" accept="image/*" capture="environment" multiple class="hidden" x-ref="camera" x-on:change="pick($event)">
            <input type="file" accept="image/*" multiple class="hidden" x-ref="library" x-on:change="pick($event)">

            <template x-if="previews.length">
                <div class="flex gap-2">
                    <template x-for="(src, i) in previews" :key="i">
                        <img :src="src" alt="" class="h-20 w-20 rounded-lg object-cover">
                    </template>
                </div>
            </template>

            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="kb-button-secondary" x-on:click="$refs.camera.click()" x-bind:disabled="busy">Foto aufnehmen</button>
                <button type="button" class="kb-button-secondary" x-on:click="$refs.library.click()" x-bind:disabled="busy">Aus Fotos wählen</button>
            </div>
            <p class="text-xs text-stone-500">1 bis {{ $maxPhotos }} Fotos: das Werk, dazu Werktext oder Raumtext.</p>

            <div wire:loading wire:target="photos" class="text-sm text-stone-600">Fotos werden hochgeladen ...</div>
            <p x-show="busy" x-cloak class="text-sm text-stone-600">Fotos werden verkleinert ...</p>

            @if ($uploadError !== '')
                <p class="text-sm text-red-700">{{ $uploadError }}</p>
            @endif

            @if ($photos !== [])
                <p class="text-sm text-stone-700">{{ count($photos) }} {{ count($photos) === 1 ? 'Foto' : 'Fotos' }} bereit.</p>
                <button type="button" class="kb-button h-14 text-lg" wire:click="createCapture" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createCapture">Audioguide erstellen</span>
                    <span wire:loading wire:target="createCapture">Einen Moment ...</span>
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
                                <p class="text-sm text-stone-600">{{ $capture->artwork?->artist?->name ?? $capture->photos->count().' '.($capture->photos->count() === 1 ? 'Foto' : 'Fotos') }} · {{ $capture->status->label() }}</p>
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
            <p class="text-sm text-stone-600">Die App sucht das Museum in deiner Nähe. Der Standort wird nur jetzt verwendet, nicht gespeichert als Spur.</p>
            <button type="button" class="kb-button" x-on:click="locate" x-bind:disabled="busy">
                <span x-show="!busy">Besuch starten</span>
                <span x-show="busy" x-cloak>Standort wird gesucht ...</span>
            </button>
            <div wire:loading wire:target="locate" class="text-sm text-stone-600">Museen in der Nähe werden gesucht ...</div>
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
