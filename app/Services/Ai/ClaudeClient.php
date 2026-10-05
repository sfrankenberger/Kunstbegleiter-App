<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiCall;
use App\Models\Capture;
use App\Models\User;
use App\Support\Secrets;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

/**
 * Huelle um die Anthropic Messages API (docs/grundgeruest.md): ein Aufruf je Zweck (AiPurpose) mit dem Modell aus
 * config/museumguide.php, Antwort als Text oder als geprueftes JSON (eine Wiederholung bei kaputtem JSON),
 * Kosten und Tokens in ai_calls. Schluessel nur aus der .env. Tests fangen alles mit Http::fake() ab.
 */
class ClaudeClient
{
    /**
     * Einen Prompt schicken und den Text der Antwort bekommen.
     *
     * @param  list<array<string, mixed>>  $messages  Nachrichten im Format der Messages API
     * @param  array<string, mixed>  $options  system, max_tokens, temperature, tools
     */
    public function text(AiPurpose $purpose, array $messages, array $options = [], ?User $user = null, ?Capture $capture = null): string
    {
        $response = $this->send($purpose, $messages, $options, $user, $capture);

        return collect($response['content'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? '') === 'text')
            ->map(fn (array $block): string => (string) ($block['text'] ?? ''))
            ->implode("\n");
    }

    /**
     * Antwort als JSON-Objekt. Kaputtes JSON wird einmal neu angefragt, danach RuntimeException.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function json(AiPurpose $purpose, array $messages, array $options = [], ?User $user = null, ?Capture $capture = null): array
    {
        $options['system'] = trim(($options['system'] ?? '')."\nAntworte ausschließlich mit einem gültigen JSON-Objekt, ohne Erklärung und ohne Markdown.");

        foreach ([1, 2] as $attempt) {
            $text = $this->text($purpose, $messages, $options, $user, $capture);
            $decoded = self::decodeJson($text);

            if ($decoded !== null) {
                return $decoded;
            }

            if ($attempt === 1) {
                $messages[] = ['role' => 'assistant', 'content' => $text];
                $messages[] = ['role' => 'user', 'content' => 'Das war kein gültiges JSON. Bitte nur das JSON-Objekt, nichts davor und nichts danach.'];
            }
        }

        throw new RuntimeException('Die KI-Antwort war zweimal kein gültiges JSON.');
    }

    /**
     * JSON aus einer Antwort lesen, auch wenn es in ```json ... ``` steht.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeJson(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $m) === 1) {
            $text = trim($m[1]);
        }

        if ($text === '' || $text[0] !== '{') {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            $text = $start !== false && $end !== false && $end > $start ? substr($text, $start, $end - $start + 1) : $text;
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function send(AiPurpose $purpose, array $messages, array $options, ?User $user, ?Capture $capture): array
    {
        $key = (string) Secrets::get('anthropic_key');

        if ($key === '') {
            throw new RuntimeException('Kein Anthropic-Schlüssel: unter Admin > Einstellungen > Zugänge eintragen (oder ANTHROPIC_API_KEY in der .env).');
        }

        $model = (string) ($options['model'] ?? $purpose->model());
        $payload = array_filter([
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? config('museumguide.anthropic.max_tokens', 4096)),
            'system' => $options['system'] ?? null,
            'temperature' => $options['temperature'] ?? null,
            'tools' => $options['tools'] ?? null,
            'messages' => $messages,
        ], fn (mixed $value): bool => $value !== null);

        $started = hrtime(true);

        try {
            $response = Http::baseUrl((string) config('museumguide.anthropic.base_url'))
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => (string) config('museumguide.anthropic.version'),
                ])
                ->timeout((int) config('museumguide.anthropic.timeout', 120))
                ->post('/v1/messages', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $this->log($purpose, $model, [], $started, $user, $capture, $e->getMessage());

            throw new RuntimeException('Anthropic-Aufruf fehlgeschlagen: '.$e->response->status());
        }

        $response = is_array($response) ? $response : [];
        $this->log($purpose, $model, (array) ($response['usage'] ?? []), $started, $user, $capture);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    protected function log(AiPurpose $purpose, string $model, array $usage, int $started, ?User $user, ?Capture $capture, ?string $error = null): void
    {
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        AiCall::query()->create([
            'user_id' => $user?->getKey(),
            'capture_id' => $capture?->getKey(),
            'purpose' => $purpose,
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost_cents' => Pricing::cents($model, $input, $output),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'succeeded' => $error === null,
            'error' => $error,
        ]);
    }
}
