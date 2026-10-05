{{--
    Huelle der Handy-Oberflaeche (docs/grundgeruest.md, Bildschirme): Kopf mit Titel, Inhalt, vier Reiter unten
    (Jetzt, Archiv, Kuenstler, Profil), bedienbar mit einer Hand. Der Audio-Player liegt unter @persist und spielt
    beim Seitenwechsel weiter (public/js/player.js). PWA: Manifest, Icons, Service Worker.
    CSS kommt gebaut aus public/css/app.css (bin/build-css). Livewire und Alpine liefert @livewireScripts.
--}}
@props(['title' => null, 'tabs' => true])
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#8b3a2f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kunstbegleiter">
    <title>{{ $title ? $title.' · ' : '' }}Kunstbegleiter</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/branding/icon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/branding/icon-180.png">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: 1 }}">
    @livewireStyles
</head>
<body class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <header class="sticky top-0 z-10 border-b border-stone-200 bg-paper/95 px-4 pt-[env(safe-area-inset-top)] backdrop-blur">
            <div class="flex h-14 items-center justify-between">
                <h1 class="text-lg font-semibold tracking-tight">{{ $title ?? 'Kunstbegleiter' }}</h1>
                {{ $actions ?? '' }}
            </div>
        </header>

        <main class="flex-1 px-4 py-4 {{ $tabs ? 'pb-24' : '' }}">
            {{ $slot }}
            <div x-data x-show="$store.player.src" x-cloak class="h-24" aria-hidden="true"></div>
        </main>

        @if ($tabs)
            @persist('player')
                <div x-data x-show="$store.player.src" x-cloak class="fixed inset-x-0 z-10 border-t border-stone-200 bg-white/95 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur" style="bottom: calc(3.6rem + env(safe-area-inset-bottom))">
                    <div class="mx-auto flex max-w-lg flex-col gap-1 px-4 py-2">
                        <audio x-ref="audio" x-init="$store.player.attach($refs.audio)" preload="metadata" x-webkit-airplay="allow"></audio>
                        <div class="flex items-center justify-between gap-2 text-xs text-stone-500">
                            <a :href="$store.player.href" wire:navigate class="min-w-0 truncate"><span class="font-medium text-stone-700" x-text="$store.player.title"></span><span x-show="$store.player.artist" x-text="', ' + $store.player.artist"></span></a>
                            <button type="button" class="shrink-0 px-1" @click="$store.player.close()" aria-label="Player schließen">✕</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="kb-button w-auto flex-1 py-2" @click="$store.player.toggle()" x-text="$store.player.playing ? 'Pause' : 'Anhören'"></button>
                            <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="$store.player.back()">15 s</button>
                            <button type="button" class="kb-button-secondary w-auto px-3 py-2 text-sm" @click="$store.player.speed()" x-text="$store.player.rate + '×'"></button>
                            <button type="button" class="kb-button-secondary w-auto px-3 py-2" @click="$store.player.route()" x-show="$store.player.canRoute" title="Ausgabe wählen (AirPods, Lautsprecher)" aria-label="Ausgabe wählen">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-stone-500">
                            <span x-text="$store.player.time($store.player.position)" class="w-9"></span>
                            <input type="range" min="0" :max="$store.player.duration" step="1" :value="$store.player.position" @input="$store.player.seek($event.target.value)" class="flex-1">
                            <span x-text="$store.player.time($store.player.duration)" class="w-9 text-right"></span>
                        </div>
                    </div>
                </div>
            @endpersist
            <x-tab-bar />
        @endif
    </div>

    <script src="/js/player.js?v={{ @filemtime(public_path('js/player.js')) ?: 1 }}"></script>
    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js?v={{ @filemtime(public_path('sw.js')) ?: 1 }}', { scope: '/' }).catch(function () {});
        }
    </script>
</body>
</html>
