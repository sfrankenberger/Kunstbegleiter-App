<div class="flex flex-col gap-4">
    <div class="kb-card">
        <p class="text-xs uppercase tracking-wide text-stone-500">{{ $capture->visit?->museum?->name ?? 'Ohne Museum' }} · {{ $capture->created_at->format('d.m.Y H:i') }}</p>
        <p class="mt-1 text-lg font-semibold">{{ $capture->artwork?->title ?? 'Werk wird erkannt' }}</p>
        @if ($capture->artwork?->artist)
            <p class="text-sm text-stone-600">{{ $capture->artwork->artist->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}</p>
        @endif
        <p class="mt-2 inline-block rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent">{{ $capture->status->label() }}</p>
        @if ($capture->error_message)
            <p class="mt-2 text-sm text-red-700">{{ $capture->error_message }}</p>
        @endif
    </div>

    <div class="kb-card">
        <p class="text-sm text-stone-600">Erkennung, Recherche, Skript und Stimme kommen mit Etappe 3. Die Fotos sind gespeichert, der Typ lässt sich hier korrigieren.</p>
    </div>

    <ul class="flex flex-col gap-3">
        @foreach ($capture->photos as $photo)
            <li class="kb-card flex flex-col gap-2" wire:key="photo-{{ $photo->getKey() }}">
                <img src="{{ $photo->url() }}" alt="" class="w-full rounded-xl object-contain" style="max-height: 60vh">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-stone-500">{{ $photo->width }} × {{ $photo->height }} px</span>
                    <select class="kb-input w-auto py-2 text-sm" wire:change="setType({{ $photo->getKey() }}, $event.target.value)">
                        @foreach ($photoTypes as $type)
                            <option value="{{ $type->value }}" @selected($photo->type === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </li>
        @endforeach
    </ul>

    <button type="button" class="kb-button-secondary text-red-700" wire:click="delete" wire:confirm="Aufnahme in den Papierkorb legen?">Aufnahme löschen</button>
</div>
