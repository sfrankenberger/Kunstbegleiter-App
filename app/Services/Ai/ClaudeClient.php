<?php

namespace App\Services\Ai;

use App\Enums\AiPurpose;
use App\Models\AiCall;
use App\Models\Capture;
use App\Models\User;
use App\Services\Pipeline\Schemas;
use App\Support\Secrets;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

/**
 * Huelle um die Anthropic Messages API (docs/grundgeruest.md, docs/konzept.md Abschnitt 10): ein Aufruf je Zweck
 * (AiPurpose) mit dem Modell aus config/museumguide.php, Antwort als Text oder als geprueftes JSON nach Schema
 * (strukturierte Ausgabe ueber output_config.format, bei kaputtem JSON eine Wiederholung), optional mit der
 * Websuche als Server-Tool (pause_turn wird fortgesetzt). Jeder Aufruf landet mit Tokens, Suchen und Kosten in
 * ai_calls. Schluessel ueber App\Support\Secrets. Tests fangen alles mit Http::fake() ab.
 */
class ClaudeClient
{
    public const WEB_SEARCH_TOOL = ['type' => 'web_search_20260209', 'name' => 'web_search'];

    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /**
     * Einen Prompt schicken und den Text der Antwort bekommen.
     *
     * @param  list<array<string, mixed>>  $messages  Nachrichten im Format der Messages API
     * @param  array<string, mixed>  $options  system, max_tokens, effort, tools, web_search (bool), max_searches, model
     */
    public function text(AiPurpose $purpose, array $messages, array $options = [], ?User $user = null, ?Capture $capture = null): string
    {
        $response = $this->send($purpose, $messages, $options, $user, $capture);

        return self::textOf($response);
    }

    /**
     * Antwort als JSON-Objekt nach Schema (strukturierte Ausgabe). Kaputtes JSON wird einmal neu angefragt,
     * danach RuntimeException.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $schema  JSON-Schema des Objekts (additionalProperties false, required)
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function json(AiPurpose $purpose, array $messages, array $options = [], ?User $user = null, ?Capture $capture = null, array $schema = []): array
    {
        if ($schema !== []) {
            $options['schema'] = $schema;
        } else {
            $options['system'] = trim(($options['system'] ?? '')."\nAntworte ausschließlich mit einem gültigen JSON-Objekt, ohne Erklärung und ohne Markdown.");
        }

        foreach ([1, 2] as $attempt) {
            $text = $this->text($purpose, $messages, $options, $user, $capture);
            $decoded = self::decodeJson($text);

            if ($decoded !== null) {
                return $decoded;
            }

            if ($attempt === 1) {
                $messages[] = ['role' => 'assistant', 'content' => $text !== '' ? $text : '{}'];
                $messages[] = ['role' => 'user', 'content' => 'Das war kein gültiges JSON. Bitte nur das JSON-Objekt, nichts davor und nichts danach.'];
            }
        }

        throw new RuntimeException('Die KI-Antwort war zweimal kein gültiges JSON.');
    }

    /**
     * JSON nach Schema, Kurzform fuer die Pipeline.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function structured(AiPurpose $purpose, array $messages, array $schema, array $options = [], ?User $user = null, ?Capture $capture = null): array
    {
        return Schemas::normalize($this->json($purpose, $messages, $options, $user, $capture, $schema));
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
     * Alle Textbloecke einer Antwort.
     *
     * @param  array<string, mixed>  $response
     */
    public static function textOf(array $response): string
    {
        return collect($response['content'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? '') === 'text')
            ->map(fn (array $block): string => (string) ($block['text'] ?? ''))
            ->implode("\n");
    }

    /**
     * Anfrage schicken. Mit Websuche laeuft die Server-Schleife; bei pause_turn wird fortgesetzt (hoechstens 4 Mal).
     * Eine Ablehnung (stop_reason refusal) wird zur RuntimeException. Jeder HTTP-Aufruf wird einzeln protokolliert.
     *
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
        $tools = (array) ($options['tools'] ?? []);

        if (($options['web_search'] ?? false) === true) {
            $tools[] = self::WEB_SEARCH_TOOL + ['max_uses' => (int) ($options['max_searches'] ?? config('museumguide.research.max_searches', 8))];
        }

        $payload = array_filter([
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? config('museumguide.anthropic.max_tokens', 8192)),
            'system' => $options['system'] ?? null,
            'tools' => $tools !== [] ? $tools : null,
            'output_config' => array_filter([
                'effort' => $options['effort'] ?? null,
                'format' => isset($options['schema']) ? ['type' => 'json_schema', 'schema' => $options['schema']] : null,
            ]) ?: null,
            'fallbacks' => self::supportsFallbacks($model) ? 'default' : null,
            'messages' => $messages,
        ], fn (mixed $value): bool => $value !== null);

        $response = [];
        $content = [];

        for ($round = 0; $round < 5; $round++) {
            $response = $this->post($key, $model, $payload, $purpose, $user, $capture);
            $content = array_merge($content, (array) ($response['content'] ?? []));

            if (($response['stop_reason'] ?? '') === 'refusal') {
                throw new RuntimeException('Die KI hat die Anfrage abgelehnt'.(isset($response['stop_details']['category']) ? ' ('.$response['stop_details']['category'].')' : '').'.');
            }

            if (($response['stop_reason'] ?? '') !== 'pause_turn') {
                break;
            }

            // Server-Schleife (Websuche) fortsetzen: Antwort anhaengen, keine neue Nutzer-Nachricht
            $payload['messages'][] = ['role' => 'assistant', 'content' => $response['content'] ?? []];
        }

        $response['content'] = $content;

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $key, string $model, array $payload, AiPurpose $purpose, ?User $user, ?Capture $capture): array
    {
        $started = hrtime(true);
        $headers = ['x-api-key' => $key, 'anthropic-version' => (string) config('museumguide.anthropic.version')];

        if (isset($payload['fallbacks'])) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
        }

        try {
            $response = Http::baseUrl((string) config('museumguide.anthropic.base_url'))
                ->withHeaders($headers)
                ->timeout((int) config('museumguide.anthropic.timeout', 180))
                ->post('/v1/messages', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = (string) ($e->response->json('error.message') ?? $e->getMessage());
            $this->log($purpose, $model, [], $started, $user, $capture, 'HTTP '.$e->response->status().': '.mb_substr($message, 0, 500));

            throw new RuntimeException('Anthropic-Aufruf fehlgeschlagen (HTTP '.$e->response->status().'): '.mb_substr($message, 0, 200));
        }

        $response = is_array($response) ? $response : [];
        $this->log($purpose, $model, (array) ($response['usage'] ?? []), $started, $user, $capture);

        return $response;
    }

    /**
     * Server-seitige Rueckfalloption bei Ablehnung (nur Claude API, nur aktuelle Modelle).
     */
    public static function supportsFallbacks(string $model): bool
    {
        return (bool) config('museumguide.anthropic.fallbacks', true)
            && (str_starts_with($model, 'claude-sonnet-5-5') || str_starts_with($model, 'claude-opus-5'));
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    protected function log(AiPurpose $purpose, string $model, array $usage, int $started, ?User $user, ?Capture $capture, ?string $error = null): void
    {
        $input = (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $searches = (int) ($usage['server_tool_use']['web_search_requests'] ?? 0);

        AiCall::query()->create([
            'user_id' => $user?->getKey(),
            'capture_id' => $capture?->getKey(),
            'purpose' => $purpose,
            'model' => $model,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'characters' => $searches,
            'cost_cents' => Pricing::cents($model, $input, $output) + Pricing::searchCents($searches),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'succeeded' => $error === null,
            'error' => $error,
        ]);
    }
}
