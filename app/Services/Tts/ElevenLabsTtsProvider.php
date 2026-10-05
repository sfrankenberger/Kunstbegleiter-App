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
     * Segmente parallel anfragen, aber hoechstens `concurrency` auf einmal (ElevenLabs erlaubt je Tarif 4 bis 10
     * gleichzeitige Anfragen, sonst HTTP 429 concurrent_limit_exceeded). Bei 429 wird die Gruppe nach kurzer
     * Pause einmal wiederholt.
     */
    public function synthesizeMany(array $segments): array
    {
        $key = (string) Secrets::get('elevenlabs_key');

        if ($key === '') {
            throw new RuntimeException('Kein ElevenLabs-Schlüssel: unter Admin > Einstellungen > Zugänge eintragen.');
        }

        $results = [];

        foreach (array_chunk($segments, max(1, (int) config('museumguide.tts.elevenlabs.concurrency', 5)), true) as $chunk) {
            foreach ($this->chunk($key, $chunk) as $i => $result) {
                $results[$i] = $result;
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @param  array<int, array{text: string, voice: string}>  $chunk
     * @return array<int, TtsResult>
     */
    private function chunk(string $key, array $chunk): array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $s, int $i) => $pool->as((string) $i)
                    ->withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])
                    ->timeout(180)
                    ->post(self::BASE.'/text-to-speech/'.$this->voiceId($s['voice']).'?output_format=mp3_44100_128', $this->body($s['text'])),
                $chunk,
                array_keys($chunk),
            ));

            $results = [];
            $rateLimited = false;
            $status = '';

            foreach ($chunk as $i => $segment) {
                $response = $responses[(string) $i] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    $results[$i] = new TtsResult(audio: (string) $response->body(), characters: mb_strlen($segment['text']));

                    continue;
                }

                $status = $response instanceof Response ? 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200) : 'keine Antwort';
                $rateLimited = $rateLimited || ($response instanceof Response && $response->status() === 429);
            }

            if (count($results) === count($chunk)) {
                return $results;
            }

            if ($rateLimited && $attempt === 1) {
                sleep((int) config('museumguide.tts.elevenlabs.retry_seconds', 3));

                continue;
            }

            throw new RuntimeException('ElevenLabs antwortet mit '.$status);
        }

        throw new RuntimeException('ElevenLabs antwortet nicht.');
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
            'voice_settings' => ['stability' => (float) config('museumguide.tts.elevenlabs.stability', 0.45), 'similarity_boost' => 0.75, 'style' => (float) config('museumguide.tts.elevenlabs.style', 0.35)],
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
