<?php

namespace App\Services\Tts;

use App\Contracts\TtsProvider;
use App\Contracts\TtsResult;
use App\Support\Secrets;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

        $response = Http::withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
            ->timeout(180)
            ->post(self::BASE.'/text-to-speech/'.$this->voiceId($voice).'?output_format=mp3_44100_128', $this->body($text));

        if (! $response->successful()) {
            throw new RuntimeException('ElevenLabs antwortet mit HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200));
        }

        return new TtsResult(audio: (string) $response->body(), characters: mb_strlen($text));
    }

    /**
     * Alle Segmente gleichzeitig anfragen (Http::pool), damit zwei Sprecher nicht laenger dauern als einer.
     */
    public function synthesizeMany(array $segments): array
    {
        $key = (string) Secrets::get('elevenlabs_key');

        if ($key === '') {
            throw new RuntimeException('Kein ElevenLabs-Schlüssel: unter Admin > Einstellungen > Zugänge eintragen.');
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (array $s, int $i) => $pool->as((string) $i)
                ->withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
                ->timeout(180)
                ->post(self::BASE.'/text-to-speech/'.$this->voiceId($s['voice']).'?output_format=mp3_44100_128', $this->body($s['text'])),
            $segments,
            array_keys($segments),
        ));

        $results = [];

        foreach ($segments as $i => $segment) {
            $response = $responses[(string) $i] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $status = $response instanceof Response ? 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200) : 'keine Antwort';

                throw new RuntimeException('ElevenLabs antwortet mit '.$status);
            }

            $results[] = new TtsResult(audio: (string) $response->body(), characters: mb_strlen($segment['text']));
        }

        return $results;
    }

    private function voiceId(string $voice): string
    {
        return (string) (config('museumguide.tts.elevenlabs.voices.'.$voice) ?? config('museumguide.tts.elevenlabs.voices.narrator'));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $text): array
    {
        return [
            'text' => $text,
            'model_id' => (string) config('museumguide.tts.elevenlabs.model', 'eleven_flash_v2_5'),
            'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'style' => 0.2],
        ];
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
