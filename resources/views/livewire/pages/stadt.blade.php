<div class="flex flex-col gap-4">
    <div
        class="kb-card flex flex-col gap-3"
        x-data="{
            busy: false,
            locate() {
                if (!navigator.geolocation) { $wire.locationFailed('Dieses Gerät kennt keinen Standort.'); return; }
                this.busy = true;
                navigator.geolocation.getCurrentPosition(
                    pos => { this.busy = false; $wire.locate(pos.coords.latitude, pos.coords.longitude, Math.round(pos.coords.accuracy || 0)); },
                    err => { this.busy = false; $wire.locationFailed(err.code === 1 ? 'Standort nicht erlaubt. Ort eingeben oder Foto machen.' : 'Standort nicht gefunden. Ort eingeben oder Foto machen.'); },
                    { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
                );
            },
        }"
    >
        <p class="text-xs uppercase tracking-wide text-stone-500">Unterwegs in der Stadt</p>
        <p class="text-sm text-stone-600">Statue, Gebäude, Platz, Kirche, Denkmal: was ist das hier? Der Standort wird nur für die Suche verwendet.</p>
        <button type="button" class="kb-button" x-on:click="locate" x-bind:disabled="busy">
            <span x-show="!busy">Orte in der Nähe suchen</span>
            <span x-show="busy" x-cloak>Standort wird gesucht ...</span>
        </button>
        <div wire:loading wire:target="locate" class="text-sm text-stone-600">Wikidata wird befragt ...</div>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="kb-button-secondary" wire:click="$set('showManual', true)">Ort eingeben</button>
            <button type="button" class="kb-button-secondary" wire:click="$set('showPhoto', true)">Foto machen</button>
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input type="checkbox" wire:model="full" class="h-5 w-5 rounded border-stone-300">
            <span>Gleich ausführlich (Recherche und Studio-Stimmen)</span>
        </label>
    </div>

    @if ($locationError !== '')
        <p class="text-sm text-red-700">{{ $locationError }}</p>
    @endif

    @if ($nearby !== [])
        <div class="kb-card flex flex-col gap-2">
            <p class="font-semibold">In der Nähe</p>
            @foreach ($nearby as $place)
                <button type="button" class="flex items-center gap-3 rounded-xl border border-stone-200 p-2 text-left" wire:click="choose('{{ $place['wikidata_id'] }}')" wire:loading.attr="disabled">
                    @if ($place['image'])
                        <img src="{{ $place['image'] }}" alt="" class="h-16 w-16 shrink-0 rounded-lg bg-stone-100 object-cover" loading="lazy">
                    @else
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-xs text-stone-400">{{ \App\Models\Place::KINDS[$place['kind']] ?? 'Ort' }}</div>
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $place['name'] }}</span>
                        <span class="block truncate text-xs text-stone-500">{{ $place['distance_m'] }} m · {{ \App\Models\Place::KINDS[$place['kind']] ?? 'Ort' }}{{ $place['architect'] ? ' · '.$place['architect'] : '' }}{{ $place['built'] ? ' · '.$place['built'] : '' }}</span>
                        @if ($place['description'])<span class="block truncate text-xs text-stone-500">{{ $place['description'] }}</span>@endif
                    </span>
                </button>
            @endforeach
            <div wire:loading wire:target="choose" class="text-sm text-stone-600">{{ $full ? 'Erkenne den Ort ...' : 'Schreibe den Überblick ...' }}</div>
        </div>
    @endif

    @if ($showManual)
        <form wire:submit="chooseManual" class="kb-card flex flex-col gap-3">
            <p class="font-semibold">Welcher Ort?</p>
            <input type="text" wire:model="manualName" class="kb-input" placeholder="z. B. Pestsäule, Palais Ferstel, Karlskirche" required>
            @error('manualName') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="submit" class="kb-button" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="chooseManual">Guide erstellen</span>
                <span wire:loading wire:target="chooseManual">Suche bei Wikidata und schreibe ...</span>
            </button>
        </form>
    @endif

    @if ($showPhoto)
        <div
            class="kb-card flex flex-col gap-3"
            x-data="{
                busy: false,
                async pick(event) {
                    const files = Array.from(event.target.files || []).slice(0, {{ (int) config('museumguide.photos.max_per_capture', 3) }});
                    if (files.length === 0) return;
                    this.busy = true;
                    const shrunk = [];
                    for (const file of files) shrunk.push(await this.shrink(file));
                    $wire.uploadMultiple('photos', shrunk, () => { this.busy = false }, () => { this.busy = false });
                    event.target.value = '';
                },
                shrink(file) {
                    const max = {{ (int) config('museumguide.photos.max_edge', 2000) }};
                    return new Promise(resolve => {
                        const img = new Image();
                        img.onload = () => {
                            const scale = Math.min(1, max / Math.max(img.width, img.height));
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.round(img.width * scale); canvas.height = Math.round(img.height * scale);
                            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                            canvas.toBlob(blob => resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.85);
                        };
                        img.onerror = () => resolve(file);
                        img.src = URL.createObjectURL(file);
                    });
                },
            }"
        >
            <p class="font-semibold">Foto von Gebäude, Statue oder Tafel</p>
            <label class="kb-button cursor-pointer">
                <span x-show="!busy">Kamera öffnen</span>
                <span x-show="busy" x-cloak>Foto wird vorbereitet ...</span>
                <input type="file" accept="image/*" capture="environment" multiple class="hidden" x-on:change="pick">
            </label>
            @if ($uploadError !== '')
                <p class="text-sm text-red-700">{{ $uploadError }}</p>
            @endif
            @if ($photos !== [])
                <p class="text-sm text-stone-700">{{ count($photos) }} {{ count($photos) === 1 ? 'Foto' : 'Fotos' }} bereit.</p>
                <button type="button" class="kb-button" wire:click="createFromPhotos" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createFromPhotos">Ort erkennen</span>
                    <span wire:loading wire:target="createFromPhotos">Erkenne den Ort und schreibe den Überblick ...</span>
                </button>
            @endif
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <h2 class="mt-2 text-sm font-semibold text-stone-700">Zuletzt angesehen</h2>
        <ul class="flex flex-col gap-2">
            @foreach ($recent as $capture)
                <li>
                    <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="kb-card flex items-center gap-3">
                        @if ($capture->place?->image_url)
                            <img src="{{ $capture->place->image_url }}" alt="" class="h-12 w-12 shrink-0 rounded-lg bg-stone-100 object-cover" loading="lazy">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $capture->place?->name }}</p>
                            <p class="truncate text-xs text-stone-500">{{ $capture->place?->kindLabel() }} · {{ $capture->created_at->translatedFormat('d. F Y') }} · {{ $capture->mode?->short() ?? 'Schnell' }}{{ $capture->isDone() ? '' : ' · '.$capture->progressLabel() }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
