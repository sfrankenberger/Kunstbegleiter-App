@php($title = 'Rückblick')
<x-layouts.app :title="$title" :tabs="! $shared">
    <div class="flex flex-col gap-4 print:gap-3">
        <div class="kb-card">
            <p class="text-xs uppercase tracking-wide text-stone-500">Besuchsrückblick · {{ $visit->created_at->translatedFormat('d. F Y') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ $visit->museum?->name ?? 'Besuch ohne Museum' }}</p>
            <p class="text-sm text-stone-600">{{ $visit->museum?->city?->name ?? $visit->city?->name }}{{ ($visit->museum?->city ?? $visit->city) ? ' · ' : '' }}{{ $captures->count() }} {{ $captures->count() === 1 ? 'Werk' : 'Werke' }}@if ($shared) · von {{ $visit->user->name }}@endif</p>
            @if (! $shared)
                <div class="mt-3 flex flex-wrap gap-2 print:hidden" x-data="{ copied: false }">
                    <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" x-on:click="window.print()">Als PDF sichern</button>
                    <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" x-on:click="navigator.share ? navigator.share({ title: 'Rückblick: {{ addslashes($visit->museum?->name ?? 'Besuch') }}', url: @js($shareUrl) }).catch(() => {}) : navigator.clipboard.writeText(@js($shareUrl)).then(() => copied = true)">Link teilen</button>
                    <span class="self-center text-xs text-stone-500" x-show="copied" x-cloak>Link kopiert, 30 Tage gültig.</span>
                </div>
            @endif
        </div>

        @forelse ($captures as $capture)
            @php($sheet = $capture->factSheets->sortByDesc('id')->firstWhere('kind', 'full') ?? $capture->factSheets->sortByDesc('id')->first())
            @php($image = $shared ? ($capture->artwork?->image_url ?? $capture->place?->image_url) : ($capture->photos->first()?->url() ?? $capture->artwork?->image_url ?? $capture->place?->image_url))
            <div class="kb-card flex flex-col gap-2 break-inside-avoid">
                <div class="flex gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $capture->artwork?->title ?? $capture->place?->name }}</p>
                        <p class="text-sm text-stone-600">
                            @if ($capture->artwork)
                                {{ $capture->artwork->artist?->name }}{{ $capture->artwork->dating ? ', '.$capture->artwork->dating : '' }}
                            @else
                                {{ $capture->place?->architect }}{{ $capture->place?->architect && $capture->place?->built ? ', ' : '' }}{{ $capture->place?->built }}
                            @endif
                        </p>
                        @if (! $shared)
                            <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="text-xs font-medium text-accent print:hidden">Zum Guide</a>
                        @endif
                    </div>
                    @if ($image)
                        <img src="{{ $image }}" alt="" class="h-20 w-20 shrink-0 rounded-lg bg-stone-100 object-cover">
                    @endif
                </div>
                @if (filled($sheet?->key_statements))
                    <ul class="list-disc pl-5 text-sm leading-relaxed text-stone-700">
                        @foreach (array_slice((array) $sheet->key_statements, 0, 4) as $statement)
                            <li>{{ is_array($statement) ? ($statement['text'] ?? '') : $statement }}</li>
                        @endforeach
                    </ul>
                @endif
                @foreach ($capture->questions as $q)
                    <div class="border-l-2 border-accent pl-3 text-sm">
                        <p class="font-semibold text-stone-800">{{ $q->question }}</p>
                        <p class="text-stone-700">{{ $q->answer }}</p>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="kb-card text-sm text-stone-600">In diesem Besuch gibt es noch keine erkannten Werke.</div>
        @endforelse

        @if ($visit->roomTexts->isNotEmpty())
            <h2 class="text-sm font-semibold text-stone-700">Raumtexte</h2>
            @foreach ($visit->roomTexts as $roomText)
                <details class="kb-card text-sm break-inside-avoid" open>
                    <summary class="cursor-pointer font-semibold">{{ $roomText->label() }}</summary>
                    <p class="mt-2 whitespace-pre-line leading-relaxed text-stone-700">{{ $roomText->text }}</p>
                </details>
            @endforeach
        @endif

        <p class="text-center text-xs text-stone-400">Erstellt mit dem Kunstbegleiter · art.tourtool.app</p>
    </div>
</x-layouts.app>
