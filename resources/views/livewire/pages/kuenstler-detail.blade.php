<div class="flex flex-col gap-4">
    @php($bullets = fn (mixed $v): array => is_array($v) ? array_values(array_filter(array_map(fn ($x) => is_array($x) ? ($x['text'] ?? '') : (string) $x, $v), 'filled')) : array_values(array_filter(preg_split('/(?<=[.!?])\s+/u', (string) $v) ?: [], 'filled')))
    <div class="kb-card kb-title-card flex gap-3">
        @if ($artist->portrait_url)
            <img src="{{ $artist->portrait_url }}" alt="" class="h-28 w-24 shrink-0 rounded-xl bg-stone-100 object-cover" title="{{ $artist->portrait_credit }}">
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-lg font-semibold">{{ $artist->name }}</p>
            @if (filled($profile['born'] ?? null) || filled($profile['died'] ?? null))
                <p class="text-sm text-stone-600">{{ filled($profile['born'] ?? null) ? '* '.$profile['born'] : '' }}{{ filled($profile['born'] ?? null) && filled($profile['died'] ?? null) ? ' · ' : '' }}{{ filled($profile['died'] ?? null) ? '† '.$profile['died'] : '' }}</p>
            @elseif ($artist->born_year || $artist->died_year)
                <p class="text-sm text-stone-600">{{ $artist->born_year ?? '?' }} bis {{ $artist->died_year ?? '?' }}</p>
            @endif
            @if ($profile === [])
                <p class="mt-2 text-sm text-stone-600">Das Profil wird gerade recherchiert oder ist noch nicht angefordert.</p>
            @endif
        </div>
    </div>

    @foreach (['life' => ['Leben', 'user'], 'style' => ['Stil und Technik', 'eye'], 'reception' => ['Rezeption', 'academic']] as $key => [$label, $icon])
        @if (filled($profile[$key] ?? null))
            <div class="kb-card flex gap-3">
                <x-kb-icon :name="$icon" class="mt-0.5 h-5 w-5 text-accent" />
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-stone-700">{{ $label }}</h2>
                    <ul class="mt-1 list-disc pl-4 text-sm leading-relaxed">
                        @foreach ($bullets($profile[$key]) as $point)<li>{{ $point }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endforeach

    @if (filled($profile['key_works'] ?? null))
        <div class="kb-card flex gap-3">
            <x-kb-icon name="sparkles" class="mt-0.5 h-5 w-5 text-accent" />
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-stone-700">Wichtige Werke</h2>
                <ul class="mt-1 list-disc pl-4 text-sm leading-relaxed">
                    @foreach ($profile['key_works'] as $kw)
                        @if (is_array($kw) && filled($kw['title'] ?? null))<li>{{ $kw['title'] }}{{ filled($kw['year'] ?? null) ? ', '.$kw['year'] : '' }}{{ filled($kw['location'] ?? null) ? ' ('.$kw['location'].')' : '' }}</li>@endif
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($captures->isNotEmpty())
        <div class="kb-card">
            <h2 class="text-sm font-semibold text-stone-700">Deine Aufnahmen</h2>
            <ul class="mt-2 flex flex-col gap-2">
                @foreach ($captures as $capture)
                    <li>
                        <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="flex items-center gap-3">
                            @if ($capture->photos->first())
                                <img src="{{ $capture->photos->first()->url() }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $capture->artwork->title }}</p>
                                <p class="truncate text-xs text-stone-500">{{ $capture->artwork->museum?->name }} · {{ $capture->created_at->translatedFormat('d. F Y') }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (filled($profile['sources'] ?? null))
        <div class="kb-card">
            <h2 class="text-sm font-semibold text-stone-700">Quellen</h2>
            <ul class="mt-2 flex flex-col gap-1 text-sm">
                @foreach ($profile['sources'] as $source)
                    @if (is_array($source) && filled($source['url'] ?? null))
                        <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener" class="break-all text-accent underline">{{ $source['title'] ?? $source['url'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif
</div>
