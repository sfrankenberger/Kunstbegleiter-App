<div class="flex flex-col gap-4">
    <div class="kb-card kb-title-card">
        <p class="text-xs uppercase tracking-wide text-stone-500">Raumtext{{ $roomText->museum ? ' · '.$roomText->museum->name : '' }} · {{ $roomText->created_at->format('d.m.Y H:i') }}</p>
        <p class="mt-1 text-lg font-semibold">{{ $roomText->label() }}</p>
        @if ($roomText->error)
            <p class="mt-2 text-sm text-red-700">Ablesen fehlgeschlagen: {{ $roomText->error }}</p>
        @endif
    </div>

    @if (filled($roomText->text))
        <div class="kb-card whitespace-pre-line text-sm leading-relaxed">{{ $roomText->text }}</div>
    @endif

    @if ($roomText->hasPhoto())
        <img src="{{ $roomText->url() }}" alt="" class="w-full rounded-2xl object-contain" style="max-height: 70vh">
    @else
        <p class="text-xs text-stone-500">Das Foto wurde nach dem Ablesen gelöscht, der Text bleibt.</p>
    @endif

    @if ($captures->isNotEmpty())
        <h2 class="text-sm font-semibold text-stone-700">Werke mit diesem Raumtext</h2>
        <ul class="flex flex-col gap-2">
            @foreach ($captures as $capture)
                <li>
                    <a href="{{ route('aufnahme', $capture) }}" wire:navigate class="kb-card flex items-center gap-3">
                        @if ($capture->photos->first())
                            <img src="{{ $capture->photos->first()->url() }}" alt="" class="h-14 w-14 rounded-lg object-cover">
                        @endif
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $capture->artwork?->title ?? 'Aufnahme von '.$capture->created_at->format('H:i') }}</p>
                            <p class="text-sm text-stone-600">{{ $capture->artwork?->artist?->name ?? $capture->progressLabel() }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <button type="button" class="kb-button-secondary text-red-700" wire:click="delete" wire:confirm="Raumtext in den Papierkorb legen?">Raumtext löschen</button>
</div>
