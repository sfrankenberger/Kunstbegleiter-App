<div class="flex flex-col gap-4">
    <input type="search" wire:model.live.debounce.400ms="search" class="kb-input" placeholder="Künstlerin oder Künstler suchen" aria-label="Suche">

    @if ($artists->isEmpty())
        <div class="kb-card text-sm text-stone-600">
            @if (trim($search) !== '')
                Nichts gefunden für "{{ $search }}".
            @else
                Noch keine Künstler. Jede Aufnahme bringt ihren Künstler hierher, mit Leben, Werken und Rezeption.
            @endif
        </div>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($artists as $artist)
                <li>
                    <a href="{{ route('kuenstler.show', $artist) }}" wire:navigate class="kb-card flex items-center gap-3">
                        @if ($artist->portrait_url)
                            <img src="{{ $artist->portrait_url }}" alt="" class="h-16 w-14 shrink-0 rounded-lg bg-stone-100 object-cover" loading="lazy">
                        @else
                            <div class="flex h-16 w-14 shrink-0 items-center justify-center rounded-lg bg-stone-100"><x-kb-icon name="user" class="h-6 w-6 text-stone-400" /></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $artist->name }}</p>
                            <p class="text-sm text-stone-600">{{ $artist->born_year ?? '?' }} bis {{ $artist->died_year ?? '?' }} · {{ $artist->works_count }} {{ $artist->works_count === 1 ? 'Werk' : 'Werke' }}</p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-stone-400"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
