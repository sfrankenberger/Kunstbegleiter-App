{{--
    Huelle der Handy-Oberflaeche (docs/grundgeruest.md, Bildschirme): Kopf mit Titel, Inhalt, vier Reiter unten
    (Jetzt, Archiv, Entdecken, Profil), bedienbar mit einer Hand. PWA: Manifest, Icons, Service Worker.
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
        </main>

        @if ($tabs)
            <x-tab-bar />
        @endif
    </div>

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js?v={{ @filemtime(public_path('sw.js')) ?: 1 }}', { scope: '/' }).catch(function () {});
        }
    </script>
</body>
</html>
