<div class="flex flex-col gap-4">
    <input type="search" wire:model.live.debounce.400ms="search" class="kb-input" placeholder="Werk oder Künstler suchen" aria-label="Suche">

    @if ($captures->isEmpty())
        <div class="kb-card text-sm text-stone-600">
            @if (trim($search) !== '')
                Nichts gefunden für "{{ $search }}".
            @else
                Noch keine Werke im Archiv. Jede fertige Aufnahme landet hier, mit Filtern nach Künstler, Stadt, Museum und Epoche (Etappe 4).
            @endif
        </div>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($captures as $capture)
                <li class="kb-card">
                    <p class="font-medium">{{ $capture->artwork->title }}</p>
                    <p class="text-sm text-stone-600">{{ $capture->artwork->artist?->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $capture->artwork->museum?->name }} · {{ $capture->created_at->translatedFormat('d. F Y') }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
