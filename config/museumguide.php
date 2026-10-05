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
        'script' => env('MUSEUMGUIDE_MODEL_SCRIPT', 'claude-sonnet-5-5'),
        'script_premium' => env('MUSEUMGUIDE_MODEL_SCRIPT_PREMIUM', 'claude-opus-5-5'),
        'check' => env('MUSEUMGUIDE_MODEL_CHECK', 'claude-sonnet-5-5'),
        'museum' => env('MUSEUMGUIDE_MODEL_MUSEUM', 'claude-haiku-4-5-20251001'),
        'tips' => env('MUSEUMGUIDE_MODEL_TIPS', 'claude-sonnet-5-5'),
        'small' => env('MUSEUMGUIDE_MODEL_SMALL', 'claude-haiku-4-5-20251001'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 120),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
    ],

    // Stimmen: Anbieter hinter App\Contracts\TtsProvider (auto, fake, elevenlabs, openai). Etappe 3.
    // auto: elevenlabs, wenn ein Schluessel da ist (Admin > Zugaenge oder .env), sonst openai, sonst fake.
    'tts' => [
        'provider' => env('MUSEUMGUIDE_TTS', 'auto'),
        'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
        'openai_key' => env('OPENAI_API_KEY'),
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
