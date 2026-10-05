<?php

use App\Contracts\PlacesClient;
use App\Contracts\TtsProvider;
use App\Enums\AiPurpose;
use App\Models\AiCall;
use App\Models\User;
use App\Services\Ai\ClaudeClient;
use App\Services\Ai\Pricing;
use App\Services\Places\FakePlacesClient;
use App\Services\Tts\FakeTtsProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('museumguide.anthropic.key', 'test-key');
});

test('the client sends the model of the purpose and logs the call', function () {
    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'text', 'text' => 'Hallo']],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ])]);
    config()->set('museumguide.models.small', 'claude-haiku-4-5-20251001');
    $user = User::factory()->create();

    $text = app(ClaudeClient::class)->text(AiPurpose::Small, [['role' => 'user', 'content' => 'Sag hallo']], [], $user);

    expect($text)->toBe('Hallo');
    Http::assertSent(fn ($request) => $request['model'] === 'claude-haiku-4-5-20251001' && $request->hasHeader('x-api-key', 'test-key'));
    expect(AiCall::query()->whereBelongsTo($user)->where('purpose', 'small')->where('input_tokens', 10)->where('succeeded', true)->exists())->toBeTrue();
});

test('json answers are parsed and a broken answer is asked again once', function () {
    Http::fake(['api.anthropic.com/v1/messages' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => 'Hier: {"titel": kaputt']], 'usage' => []])
        ->push(['content' => [['type' => 'text', 'text' => "```json\n{\"titel\": \"Der Kuss\", \"sicherheit\": 0.9}\n```"]], 'usage' => []]),
    ]);

    $data = app(ClaudeClient::class)->json(AiPurpose::Recognize, [['role' => 'user', 'content' => 'Welches Werk?']]);

    expect($data)->toBe(['titel' => 'Der Kuss', 'sicherheit' => 0.9]);
    Http::assertSentCount(2);
});

test('two broken json answers raise an exception', function () {
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(['content' => [['type' => 'text', 'text' => 'kein json']], 'usage' => []])]);

    expect(fn () => app(ClaudeClient::class)->json(AiPurpose::Recognize, [['role' => 'user', 'content' => 'x']]))->toThrow(RuntimeException::class);
});

test('a failed request is logged as failed', function () {
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(['error' => 'overloaded'], 529)]);

    expect(fn () => app(ClaudeClient::class)->text(AiPurpose::Script, [['role' => 'user', 'content' => 'x']]))->toThrow(RuntimeException::class);
    expect(AiCall::query()->where('succeeded', false)->exists())->toBeTrue();
});

test('without a key the client refuses before any request', function () {
    config()->set('museumguide.anthropic.key', '');
    Http::fake();

    expect(fn () => app(ClaudeClient::class)->text(AiPurpose::Script, []))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

test('pricing uses the configured rates', function () {
    config()->set('museumguide.pricing.models', ['claude-test' => ['input_per_million' => 3, 'output_per_million' => 15]]);
    config()->set('museumguide.pricing.web_search_per_1000', 10);
    config()->set('museumguide.pricing.tts.elevenlabs', 0.30);
    config()->set('museumguide.pricing_eur_per_usd', 1);

    expect(Pricing::cents('claude-test', 1_000_000, 100_000))->toBe(450)
        ->and(Pricing::cents('claude-test-20991231', 1_000_000, 0))->toBe(300)
        ->and(Pricing::cents('unbekannt', 1000, 1000))->toBe(0)
        ->and(Pricing::searchCents(5))->toBe(5)
        ->and(Pricing::ttsCents('elevenlabs', 2000))->toBe(60)
        ->and(Pricing::ttsCents('fake', 2000))->toBe(0);
});

test('the fake providers are bound by default', function () {
    config()->set('museumguide.places.key', '');
    expect(app(TtsProvider::class))->toBeInstanceOf(FakeTtsProvider::class)
        ->and(app(PlacesClient::class))->toBeInstanceOf(FakePlacesClient::class);

    $result = app(TtsProvider::class)->synthesize('Ein kurzer Satz mit sieben Wörtern drin.', 'narrator');
    expect($result->characters)->toBe(40)->and($result->durationSeconds)->toBeGreaterThan(0);

    $nearby = app(PlacesClient::class)->nearbyMuseums(48.2037, 16.3616, 300);
    expect($nearby[0]['name'])->toBe('Kunsthistorisches Museum Wien')->and($nearby[0]['distance_m'])->toBeLessThan(50);
});
