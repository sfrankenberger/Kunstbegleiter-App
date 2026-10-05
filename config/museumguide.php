<?php

/*
|--------------------------------------------------------------------------
| Kunstbegleiter: Modelle, Limits, Laengen (docs/grundgeruest.md)
|--------------------------------------------------------------------------
|
| Alle Modellnamen und Limits stehen hier, damit sie ohne Codeaenderung wechseln. Die Prompts liegen in
| resources/prompts/. Preise und das Monatslimit werden beim Bau der Pipeline (Etappe 3) mit aktuellen Werten
| befuellt, hier stehen nur Platzhalter.
|
*/

return [
    'name' => env('MUSEUMGUIDE_NAME', 'Kunstbegleiter'),

    // Modell je Aufgabe (App\Enums\AiPurpose)
    'models' => [
        'default' => env('MUSEUMGUIDE_MODEL_DEFAULT', 'claude-sonnet-5-5'),
        'recognize' => env('MUSEUMGUIDE_MODEL_RECOGNIZE', 'claude-sonnet-5-5'),
        'research' => env('MUSEUMGUIDE_MODEL_RESEARCH', 'claude-sonnet-5-5'),
        'quick' => env('MUSEUMGUIDE_MODEL_QUICK', 'claude-sonnet-5-5'),
        'script' => env('MUSEUMGUIDE_MODEL_SCRIPT', 'claude-sonnet-5-5'),
        'script_premium' => env('MUSEUMGUIDE_MODEL_SCRIPT_PREMIUM', 'claude-opus-5-5'),
        'check' => env('MUSEUMGUIDE_MODEL_CHECK', 'claude-sonnet-5-5'),
        'museum' => env('MUSEUMGUIDE_MODEL_MUSEUM', 'claude-sonnet-5-5'),
        'tips' => env('MUSEUMGUIDE_MODEL_TIPS', 'claude-sonnet-5-5'),
        'small' => env('MUSEUMGUIDE_MODEL_SMALL', 'claude-haiku-4-5'),
    ],

    // Preise (USD je Million Tokens, Stand 05.10.2026, platform.claude.com/docs/en/about-claude/pricing) und
    // Umrechnung in Euro. Bei neuen Modellen hier nachtragen, nicht aus dem Gedaechtnis.
    'pricing_eur_per_usd' => (float) env('MUSEUMGUIDE_EUR_PER_USD', 0.92),
    'pricing' => [
        'models' => [
            'claude-opus-5-5' => ['input_per_million' => 4, 'output_per_million' => 20],
            'claude-sonnet-5-5' => ['input_per_million' => 2, 'output_per_million' => 10],
            'claude-haiku-4-5' => ['input_per_million' => 1, 'output_per_million' => 5],
        ],
        'web_search_per_1000' => 10,
        // USD je 1000 Zeichen (ElevenLabs Creator-Tarif etwa 0.30 USD je 1000 Zeichen, bei Bedarf anpassen)
        'tts' => [
            'elevenlabs' => (float) env('MUSEUMGUIDE_TTS_USD_PER_1000', 0.30),
            'fake' => 0,
        ],
    ],

    // Pipeline (docs/grundgeruest.md): Schwellwert der Erkennung, Websuchen je Recherche, Wiederholungen je Job
    'pipeline' => [
        'confidence_threshold' => (float) env('MUSEUMGUIDE_CONFIDENCE', 0.7),
        'tries' => 3,
        'backoff_seconds' => 20,
    ],
    'research' => [
        'max_searches' => (int) env('MUSEUMGUIDE_MAX_SEARCHES', 4),
        'museum_max_searches' => 4,
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8192),
        // Server-seitiger Rueckfall bei Ablehnung durch die Sicherheitsfilter (nur Claude API)
        'fallbacks' => (bool) env('ANTHROPIC_FALLBACKS', true),
    ],

    // Stimmen: Anbieter hinter App\Contracts\TtsProvider (auto, fake, elevenlabs, openai). Etappe 3.
    // auto: elevenlabs, wenn ein Schluessel da ist (Admin > Zugaenge oder .env), sonst openai, sonst fake.
    'tts' => [
        'provider' => env('MUSEUMGUIDE_TTS', 'auto'),
        'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
        'openai_key' => env('OPENAI_API_KEY'),
        'elevenlabs' => [
            // eleven_flash_v2_5 ist deutlich schneller als eleven_multilingual_v2 (Ziel: Guide in 45 Sekunden)
            'model' => env('ELEVENLABS_MODEL', 'eleven_flash_v2_5'),
            // Gleichzeitige Anfragen (Tarif Free 4, Starter 6, Creator 10; wir bleiben darunter), Pause vor dem Wiederholen bei 429
            'concurrency' => (int) env('ELEVENLABS_CONCURRENCY', 5),
            'retry_seconds' => 3,
            // Ausdruck: niedrigere Stabilitaet und mehr Stil machen das Erzaehlen lebendiger (0.5/0.2 waere neutral)
            'stability' => (float) env('ELEVENLABS_STABILITY', 0.45),
            'style' => (float) env('ELEVENLABS_STYLE', 0.35),
            // Stimmen je Rolle (Voice-IDs aus Sebastians ElevenLabs-Bibliothek, 05.10.2026): immer Mann und Frau.
            // narrator "Christian, warm and captivating" (Mann, reif, gemuetlich), second und quote "Sabrina, authentic
            // and engaging" (Frau, reif, ruhig, artikuliert; Leonie wirkte daneben zu jung). Die Schnellstufe nutzt nur narrator.
            'voices' => [
                'narrator' => env('ELEVENLABS_VOICE_NARRATOR', 'NBqeXKdZHweef6y0B67V'),
                'second' => env('ELEVENLABS_VOICE_SECOND', 'cqPdIo76zSHFDcSZpFov'),
                'quote' => env('ELEVENLABS_VOICE_QUOTE', 'cqPdIo76zSHFDcSZpFov'),
            ],
        ],
    ],

    // Musikbett und zwei Sprecher im ausfuehrlichen Guide (docs/konzept.md Abschnitt 16). Dateien unter
    // storage/app/music/{epoche-slug}.mp3 oder default.mp3; verwandte Epochen hier auf eine Datei abbilden.
    'music' => [
        'enabled' => (bool) env('MUSEUMGUIDE_MUSIC', true),
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'dir' => storage_path('app/music'),
        'volume' => 0.12,
        'intro_seconds' => 2,
        'outro_seconds' => 3,
        'gap_seconds' => 0.5,
        'epochs' => [
            'antike' => 'barock', 'mittelalter' => 'barock', 'gotik' => 'barock', 'renaissance' => 'barock', 'manierismus' => 'barock',
            'rokoko' => 'barock', 'klassizismus' => 'klassik', 'biedermeier' => 'romantik', 'realismus' => 'romantik',
            'historismus' => 'romantik', 'impressionismus' => 'impressionismus', 'symbolismus' => 'impressionismus',
            'jugendstil' => 'impressionismus', 'expressionismus' => 'moderne', 'klassische-moderne' => 'moderne',
            'nachkriegsmoderne' => 'moderne',
        ],
    ],

    // Bilder von Wikidata und Wikimedia Commons (App\Services\Images\WikiImages): nur Links, keine Dateien
    'images' => [
        'enabled' => (bool) env('MUSEUMGUIDE_IMAGES', true),
        'width' => 640,
        'timeout' => 8,
        'max_related' => 6,
    ],

    // Ort: Google Places hinter App\Contracts\PlacesClient (auto, fake, google). Etappe 2.
    // auto: google, wenn ein Schluessel da ist (Admin > Zugaenge oder .env), sonst fake.
    'places' => [
        'provider' => env('MUSEUMGUIDE_PLACES', 'auto'),
        'key' => env('GOOGLE_PLACES_KEY'),
        'radius_m' => (int) env('MUSEUMGUIDE_PLACES_RADIUS', 300),
    ],

    // Besuch: wie lange ein Besuch ab der letzten Aufnahme gilt
    'visit' => [
        'minutes' => (int) env('MUSEUMGUIDE_VISIT_MINUTES', 30),
    ],

    // Fotos: lange Kante in Pixel (der Browser verkleinert vor dem Upload), hoechstens 3 je Aufnahme
    'photos' => [
        'max_edge' => (int) env('MUSEUMGUIDE_PHOTO_EDGE', 2000),
        'max_per_capture' => 3,
        'max_bytes' => 8 * 1024 * 1024,
    ],

    // Skript: Woerter je Laenge (App\Enums\GuideLength), Rueckbezuege je Guide
    // Queue-Worker sofort anstossen (App\Support\QueueKick), bis systemd laeuft. PHP-Binary am Server: /opt/plesk/php/8.5/bin/php
    'queue' => [
        'kick' => (bool) env('MUSEUMGUIDE_QUEUE_KICK', true),
        'php' => env('KUNST_PHP', 'php'),
    ],

    // Zielzeiten (Sebastian, 05.10.2026): schnell hoechstens 10 Sekunden, ausfuehrlich hoechstens 45 Sekunden
    'targets' => ['quick_seconds' => 10, 'full_seconds' => 45],

    // Bildkante fuer die KI (kleiner als das gespeicherte Foto, spart Zeit beim Hochladen und Lesen)
    'vision_edge' => (int) env('MUSEUMGUIDE_VISION_EDGE', 1024),

    // Schnellstufe: Kurztext ohne Websuche, vom Handy vorgelesen (docs/konzept.md Abschnitt 11)
    'quick' => [
        'words' => ['min' => 100, 'max' => 160],
    ],

    'script' => [
        'words' => [
            'short' => ['min' => 200, 'max' => 260],
            'normal' => ['min' => 350, 'max' => 450],
            'long' => ['min' => 480, 'max' => 550],
        ],
        'max_back_references' => 2,
    ],

    // Kosten: Monatslimit je Nutzer in Cent (Platzhalter 30 EUR, Sebastian 04.10.2026), Hinweis ab 80 %
    'costs' => [
        'monthly_limit_cents' => (int) env('MUSEUMGUIDE_MONTHLY_LIMIT_CENTS', 3000),
        'warn_at_percent' => 80,
    ],
];
