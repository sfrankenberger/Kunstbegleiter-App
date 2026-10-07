<div class="flex flex-col gap-4">
    <input type="search" wire:model.live.debounce.400ms="search" class="kb-input" placeholder="Werk oder Künstler suchen" aria-label="Suche">

    @if ($captures->isEmpty() && $roomTexts->isEmpty())
        <div class="kb-card text-sm text-stone-600">
            @if (trim($search) !== '')
                Nichts gefunden für "{{ $search }}".
            @else
                Noch keine Werke im Archiv. Jede fertige Aufnahme landet hier, mit Filtern nach Künstler, Stadt, Museum und Epoche (Etappe 4).
            @endif
        </div>
    @elseif ($captures->isNotEmpty())
        <ul class="flex flex-col gap-2">
            @foreach ($captures as $capture)
                <li>
                    <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="kb-card flex items-center gap-3">
                        @if ($capture->photos->first())
                            <img src="{{ $capture->photos->first()->url() }}" alt="" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $capture->artwork->title }}</p>
                            <p class="truncate text-sm text-stone-600">{{ $capture->artwork->artist?->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}</p>
                            <p class="mt-1 truncate text-xs text-stone-500">{{ $capture->artwork->museum?->name }} · {{ $capture->created_at->translatedFormat('d. F Y') }} · {{ $capture->mode?->short() ?? 'Schnell' }}{{ $capture->isDone() ? '' : ' · '.$capture->progressLabel() }}</p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-stone-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($roomTexts->isNotEmpty())
        <h2 class="mt-2 text-sm font-semibold text-stone-700">Raumtexte</h2>
        <ul class="flex flex-col gap-2">
            @foreach ($roomTexts as $roomText)
                <li>
                    <a href="{{ route('raumtext', $roomText) }}" wire:navigate class="kb-card flex items-center gap-3">
                        <img src="{{ $roomText->url() }}" alt="" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $roomText->label() }}</p>
                            <p class="truncate text-sm text-stone-600">{{ $roomText->excerpt() }}</p>
                            <p class="mt-1 truncate text-xs text-stone-500">{{ $roomText->museum?->name ?? 'Ohne Museum' }} · {{ $roomText->created_at->translatedFormat('d. F Y') }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
