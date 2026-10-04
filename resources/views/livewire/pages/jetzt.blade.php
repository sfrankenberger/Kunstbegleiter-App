<div class="flex flex-col gap-4">
    @if ($visit)
        <div class="kb-card">
            <p class="text-xs uppercase tracking-wide text-stone-500">Aktueller Besuch</p>
            <p class="mt-1 text-lg font-semibold">{{ $visit->museum?->name ?? 'Museum noch nicht bestimmt' }}</p>
            <p class="text-sm text-stone-600">{{ $visit->city?->name }} · noch {{ $visit->remainingMinutes() }} Minuten</p>
        </div>
    @else
        <div class="kb-card">
            <p class="text-xs uppercase tracking-wide text-stone-500">Kein aktiver Besuch</p>
            <p class="mt-1 text-sm text-stone-600">Beim Start eines Besuchs fragt die App nach dem Standort und sucht das Museum in der Nähe. Das kommt in Etappe 2.</p>
        </div>
    @endif

    <button type="button" class="kb-button h-16 text-lg" disabled title="Kommt in Etappe 2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" /></svg>
        Audioguide erstellen
    </button>
    <p class="text-center text-xs text-stone-500">Kamera und Fotos kommen in Etappe 2, die KI-Pipeline in Etappe 3.</p>

    @if ($captures->isNotEmpty())
        <h2 class="mt-2 text-sm font-semibold text-stone-700">Werke dieses Besuchs</h2>
        <ul class="flex flex-col gap-2">
            @foreach ($captures as $capture)
                <li class="kb-card">
                    <p class="font-medium">{{ $capture->artwork?->title ?? 'Wird erkannt ...' }}</p>
                    <p class="text-sm text-stone-600">{{ $capture->artwork?->artist?->name }} · {{ $capture->status->label() }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
