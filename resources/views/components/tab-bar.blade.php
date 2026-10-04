{{-- Vier Reiter unten (docs/grundgeruest.md): Jetzt, Archiv, Entdecken, Profil. --}}
@php
    $tabs = [
        ['route' => 'jetzt', 'label' => 'Jetzt', 'icon' => 'M12 4.5v15m7.5-7.5h-15'],
        ['route' => 'archiv', 'label' => 'Archiv', 'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
        ['route' => 'entdecken', 'label' => 'Entdecken', 'icon' => 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z'],
        ['route' => 'profil', 'label' => 'Profil', 'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-10 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur" aria-label="Hauptnavigation">
    <ul class="mx-auto grid max-w-lg grid-cols-4">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <li>
                <a href="{{ route($tab['route']) }}" wire:navigate @class(['flex flex-col items-center gap-1 py-2 text-xs font-medium', 'text-accent' => $active, 'text-stone-500' => ! $active]) @if ($active) aria-current="page" @endif>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" /></svg>
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
