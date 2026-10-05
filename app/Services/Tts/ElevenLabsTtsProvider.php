<?php

namespace App\Services\Tts;

use App\Contracts\TtsProvider;
use App\Contracts\TtsResult;
use App\Support\Secrets;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stimmen ueber ElevenLabs (Text-to-Speech, Modell eleven_multilingual_v2, MP3). Stimmen je Rolle in
 * config/museumguide.php. Schluessel ueber Secrets (Admin > Zugaenge oder ELEVENLABS_API_KEY).
 */
class ElevenLabsTtsProvider implements TtsProvider
{
    public const BASE = 'https://api.elevenlabs.io/v1';

    public function synthesize(string $text, string $voice): TtsResult
    {
        $key = (string) Secrets::get('elevenlabs_key');

        if ($key === '') {
            throw new RuntimeException('Kein ElevenLabs-Schlüssel: unter Admin > Einstellungen > Zugänge eintragen.');
        }

        $voiceId = (string) (config('museumguide.tts.elevenlabs.voices.'.$voice) ?? config('museumguide.tts.elevenlabs.voices.narrator'));

        $response = Http::withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
            ->timeout(180)
            ->post(self::BASE.'/text-to-speech/'.$voiceId.'?output_format=mp3_44100_128', [
                'text' => $text,
                'model_id' => (string) config('museumguide.tts.elevenlabs.model', 'eleven_multilingual_v2'),
                'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'style' => 0.2],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ElevenLabs antwortet mit HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200));
        }

        return new TtsResult(audio: (string) $response->body(), characters: mb_strlen($text));
    }

    public function voices(): array
    {
        return [
            'narrator' => 'Erzählerin',
            'second' => 'Zweite Stimme',
            'quote' => 'Zitat-Stimme',
        ];
    }

    public function name(): string
    {
        return 'elevenlabs';
    }
}
