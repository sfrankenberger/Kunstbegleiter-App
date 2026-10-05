{{-- Vier Reiter unten (docs/grundgeruest.md): Jetzt, Archiv, Kuenstler (statt Entdecken, Abschnitt 22), Profil. --}}
@php
    $tabs = [
        ['route' => 'jetzt', 'label' => 'Jetzt', 'icon' => 'M12 4.5v15m7.5-7.5h-15'],
        ['route' => 'archiv', 'label' => 'Archiv', 'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
        ['route' => 'kuenstler', 'label' => 'Künstler', 'icon' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
        ['route' => 'stadt', 'label' => 'Stadt', 'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 7.5l9-4.5 9 4.5'],
        ['route' => 'profil', 'label' => 'Profil', 'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-10 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur" aria-label="Hauptnavigation">
    <ul class="mx-auto grid max-w-lg grid-cols-5">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route'], $tab['route'].'.*'); @endphp
            <li>
                <a href="{{ route($tab['route']) }}" wire:navigate @class(['flex flex-col items-center gap-1 py-2 text-xs font-medium', 'text-accent' => $active, 'text-stone-500' => ! $active]) @if ($active) aria-current="page" @endif>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" /></svg>
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
