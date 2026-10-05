<?php

use App\Contracts\TtsProvider;
use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PipelineStep;
use App\Jobs\SynthesizeAudio;
use App\Livewire\Pages\Aufnahme;
use App\Livewire\Pages\Jetzt;
use App\Livewire\Pages\Profil;
use App\Models\AiCall;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\KnowledgeItem;
use App\Models\Museum;
use App\Models\Research;
use App\Models\User;
use App\Models\Visit;
use App\Services\Ai\ClaudeClient;
use App\Services\Captures\CaptureService;
use App\Services\Pipeline\Pipeline;
use App\Services\Tts\ElevenLabsTtsProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Pipeline-Tests mit gefaelschten API-Antworten (nie echte Kosten). Die Queue laeuft in Tests synchron, eine Kette
 * wird also sofort und der Reihe nach abgearbeitet: Erkennen, Recherche, Skript, Pruefen, Stimme (Fake), Merken.
 */

beforeEach(function () {
    Storage::fake('local');
    config()->set('museumguide.anthropic.key', 'test-key');
    config()->set('museumguide.tts.provider', 'fake');
});

/** @param array<string, mixed> $json */
function claudeJson(array $json, array $usage = ['input_tokens' => 1000, 'output_tokens' => 200], string $stop = 'end_turn'): array
{
    return ['content' => [['type' => 'text', 'text' => json_encode($json, JSON_UNESCAPED_UNICODE)]], 'usage' => $usage, 'stop_reason' => $stop];
}

function recognitionJson(float $confidence = 0.95): array
{
    return [
        'photos' => [['index' => 0, 'type' => 'artwork', 'text' => null], ['index' => 1, 'type' => 'label', 'text' => 'Gustav Klimt, Der Kuss, 1908/09']],
        'title' => 'Der Kuss', 'artist' => 'Gustav Klimt', 'artist_life_dates' => '1862 bis 1918', 'dating' => '1908/09',
        'technique' => 'Öl auf Leinwand', 'dimensions' => '180 x 180 cm', 'inventory_number' => 'Inv. 912', 'epoch' => 'Jugendstil',
        'confidence' => $confidence, 'alternatives' => [['title' => 'Die Umarmung', 'artist' => 'Gustav Klimt', 'reason' => 'Ähnliches Motiv']], 'notes' => null,
    ];
}

function researchJson(): array
{
    return [
        'summary' => 'Der Kuss entstand 1908/09 und hängt im Belvedere.',
        'facts' => [['statement' => 'Der Kuss wurde 1908 vom Staat gekauft.', 'source_url' => 'https://www.belvedere.at/kuss']],
        'quotes' => [['text' => 'Alle Kunst ist erotisch.', 'original' => null, 'speaker' => 'Gustav Klimt', 'context' => 'Zugeschrieben', 'source_url' => 'https://example.org/zitat']],
        'sources' => [['url' => 'https://www.belvedere.at/kuss', 'title' => 'Belvedere: Der Kuss', 'kind' => 'museum']],
        'existing_guides' => [], 'vienna_links' => [['title' => 'Secession', 'reason' => 'Klimt war Gründungsmitglied']],
        'artist_born' => 1862, 'artist_died' => 1918, 'wikidata_id' => 'Q698487',
    ];
}

function scriptJson(): array
{
    return [
        'segments' => [
            ['role' => 'narrator', 'text' => 'Vor dir hängt das wohl bekannteste Bild Wiens.'],
            ['role' => 'quote', 'text' => 'Alle Kunst ist erotisch.'],
            ['role' => 'narrator', 'text' => 'Das Blattgold stammt aus der Werkstatt seines Vaters.'],
        ],
        'fact_sheet' => [
            'key_facts' => [['label' => 'Entstanden', 'value' => '1908/09'], ['label' => 'Technik', 'value' => 'Öl und Blattgold auf Leinwand']],
            'key_statements' => ['Höhepunkt der Goldenen Periode.'],
            'guest_ideas' => ['opener' => 'Wer kennt das Bild von Postkarten?', 'question' => 'Wo endet der Kuss?', 'anecdote' => 'Der Staat kaufte es noch unfertig.', 'vienna_link' => 'Secession, nur zehn Gehminuten entfernt.'],
            'cross_references' => ['Egon Schiele, Umarmung'],
            'sections' => [
                'artist' => 'Klimt führte die Wiener Secession an.', 'provenance' => 'Vom Staat 1908 gekauft, seit 1908 im Belvedere.',
                'interpretation' => 'Verschmelzung zweier Menschen in Gold.', 'epoch' => 'Jugendstil um 1900.', 'look' => 'Die Füße am Rand der Wiese.',
                'quote_text' => 'Alle Kunst ist erotisch.', 'quote_speaker' => 'Gustav Klimt', 'quote_context' => 'zugeschrieben',
                'anecdote' => 'Die Kaufsumme war die höchste je für ein lebendes Werk.', 'curator_text' => 'Das Bild ist ein Versprechen.', 'curator_name' => 'Kuratorin Belvedere', 'more' => null,
            ],
        ],
    ];
}

function knowledgeJson(): array
{
    return ['artist_summary' => 'Goldene Periode, Blattgold, Staatskauf 1908.', 'epoch_summary' => 'Jugendstil als Gesamtkunstwerk.'];
}

function captureWithPhotos(User $user, ?Museum $museum = null, GuideMode $mode = GuideMode::Full): Capture
{
    $visit = Visit::factory()->for($user)->create(['museum_id' => $museum?->getKey()]);

    return app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('werk.jpg', 600, 400), UploadedFile::fake()->image('schild.jpg', 400, 300)], $mode);
}

test('the quick mode is one call with the photos, no web search, no mp3, and can be upgraded', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(recognitionJson() + scriptJson()))
        ->push(claudeJson(researchJson() + scriptJson()))
        ->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create();

    $capture = captureWithPhotos($user, null, GuideMode::Quick)->fresh();

    expect($capture->mode)->toBe(GuideMode::Quick)
        ->and($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->artwork->title)->toBe('Der Kuss')
        ->and($capture->artwork->research)->toBeNull()
        ->and($capture->audioGuide->audio_path)->toBeNull()
        ->and($capture->audioGuide->tts_provider)->toBe('browser')
        ->and($capture->factSheet->key_statements)->toHaveCount(1)
        ->and(AiCall::query()->where('capture_id', $capture->getKey())->count())->toBe(1)
        ->and(AiCall::query()->where('purpose', AiPurpose::Quick)->exists())->toBeTrue();
    Http::assertSent(fn ($request) => ! isset($request['tools']) && str_contains((string) $request['system'], 'schnellen Überblick') && ($request['messages'][0]['content'][1]['type'] ?? '') === 'image');

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Vorlesen lassen')
        ->assertSee('Ausführlichen Guide erstellen')
        ->assertSee('Vor dir hängt')
        ->call('upgrade');

    $capture->refresh();
    expect($capture->mode)->toBe(GuideMode::Full)
        ->and($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->artwork->research)->not->toBeNull()
        ->and($capture->audioGuides()->count())->toBe(2)
        ->and($capture->audioGuide->audio_path)->not->toBeNull()
        ->and($capture->audioGuide->tts_provider)->toBe('fake');
});

test('the profile default and the switch on jetzt decide the mode', function () {
    Bus::fake();
    $user = User::factory()->create(['default_mode' => GuideMode::Full]);
    Visit::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Profil::class)
        ->assertSet('default_mode', 'full')
        ->set('default_mode', 'quick')
        ->call('save');
    expect($user->fresh()->default_mode)->toBe(GuideMode::Quick);

    Livewire::actingAs($user)->test(Jetzt::class)
        ->assertSet('full', false)
        ->set('full', true)
        ->set('photos', [UploadedFile::fake()->image('werk.jpg')])
        ->assertSee('Ausführlichen Guide erstellen')
        ->call('createCapture');

    expect(Capture::query()->first()->mode)->toBe(GuideMode::Full);
});

test('a capture runs through the whole chain to a finished guide', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(recognitionJson()))
        ->push(claudeJson(researchJson() + scriptJson(), ['input_tokens' => 5000, 'output_tokens' => 1500, 'server_tool_use' => ['web_search_requests' => 3]]))
        ->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create();
    $museum = Museum::factory()->create(['name' => 'Belvedere']);

    $capture = captureWithPhotos($user, $museum)->fresh();

    expect($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->step)->toBeNull()
        ->and($capture->finished_at)->not->toBeNull()
        ->and($capture->artwork->title)->toBe('Der Kuss')
        ->and($capture->artwork->artist->name)->toBe('Gustav Klimt')
        ->and($capture->artwork->artist->born_year)->toBe(1862)
        ->and($capture->artwork->museum_id)->toBe($museum->getKey())
        ->and($capture->artwork->research->summary)->toContain('Belvedere')
        ->and($capture->photos[1]->ocr_text)->toContain('Der Kuss')
        ->and($capture->audioGuide->script)->toHaveCount(3)
        ->and($capture->audioGuide->audio_path)->toBe('captures/'.$capture->getKey().'/guide-'.$capture->audioGuide->getKey().'.mp3')
        ->and($capture->audioGuide->tts_provider)->toBe('fake')
        ->and($capture->factSheet->guest_ideas['question'])->toBe('Wo endet der Kuss?')
        ->and(KnowledgeItem::query()->whereBelongsTo($user)->count())->toBe(1)
        ->and(AiCall::query()->where('capture_id', $capture->getKey())->where('succeeded', true)->count())->toBe(4)
        ->and(AiCall::query()->where('purpose', AiPurpose::Script)->value('characters'))->toBe(3)
        ->and(AiCall::query()->where('purpose', AiPurpose::Script)->value('cost_cents'))->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($capture->audioGuide->audio_path);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && isset($request['tools'][0]['name']) && $request['tools'][0]['name'] === 'web_search' && $request['tools'][0]['max_uses'] === 4);
    Http::assertSent(fn ($request) => isset($request['output_config']['format']['type']) && $request['output_config']['format']['type'] === 'json_schema' && ($request['messages'][0]['content'][1]['type'] ?? '') === 'image');
});

test('an unsure recognition waits for confirmation and continues after a tap', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(recognitionJson(0.4)))
        ->push(claudeJson(researchJson() + scriptJson()))
        ->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create();

    $capture = captureWithPhotos($user)->fresh();

    expect($capture->needs_confirmation)->toBeTrue()
        ->and($capture->step)->toBe(PipelineStep::Confirming)
        ->and($capture->isRunning())->toBeFalse()
        ->and($capture->artwork_id)->toBeNull();

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Welches Werk ist es?')
        ->assertSee('Die Umarmung')
        ->call('confirm', 0)
        ->assertSee('Fertig');

    $capture->refresh();
    expect($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->needs_confirmation)->toBeFalse()
        ->and($capture->artwork->title)->toBe('Die Umarmung');
});

test('a typed title is used when nothing was recognised', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(array_merge(recognitionJson(0.1), ['title' => null, 'artist' => null, 'alternatives' => []])))
        ->push(claudeJson(researchJson() + scriptJson()))
        ->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create();
    $capture = captureWithPhotos($user)->fresh();

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->call('confirmManual')
        ->assertSet('notice', 'Bitte den Titel eintippen.')
        ->set('manualTitle', 'Judith')
        ->set('manualArtist', 'Gustav Klimt')
        ->call('confirmManual');

    expect($capture->fresh()->artwork->title)->toBe('Judith')
        ->and($capture->fresh()->status)->toBe(CaptureStatus::Done);
});

test('an api failure marks the capture and retry finishes it', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Overloaded']], 529)
        ->push(claudeJson(recognitionJson()))
        ->push(claudeJson(researchJson() + scriptJson()))
        ->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create();
    $capture = captureWithPhotos($user)->fresh();

    expect($capture->status)->toBe(CaptureStatus::Failed)
        ->and($capture->error_message)->toContain('529')
        ->and(AiCall::query()->where('succeeded', false)->count())->toBe(1);

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Erneut versuchen')
        ->call('retry');

    expect($capture->fresh()->status)->toBe(CaptureStatus::Done);
});

test('a known artwork reuses its research and a refusal is reported', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->create();
    $artwork = Artwork::factory()->create(['title' => 'Der Kuss', 'museum_id' => $museum->getKey(), 'inventory_number' => 'Inv. 912']);
    $artwork->research()->create(['summary' => 'Schon recherchiert.', 'sources' => [['url' => 'https://example.org', 'title' => 'Quelle', 'kind' => 'web']], 'existing_guides' => []]);

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(recognitionJson()))
        ->push(['content' => [], 'stop_reason' => 'refusal', 'stop_details' => ['category' => 'test'], 'usage' => []]),
    ]);

    $capture = captureWithPhotos($user, $museum)->fresh();

    expect($capture->artwork_id)->toBe($artwork->getKey())
        ->and(Research::query()->count())->toBe(1)
        ->and($capture->status)->toBe(CaptureStatus::Failed)
        ->and($capture->error_message)->toContain('abgelehnt');
    Http::assertSent(fn ($request) => str_contains((string) $request['system'], 'Schon recherchiert.') && ! isset($request['tools']));
});

test('the monthly limit stops a new capture with a clear message', function () {
    Http::fake();
    $user = User::factory()->create(['monthly_budget_cents' => 100]);
    AiCall::factory()->for($user)->create(['cost_cents' => 150, 'created_at' => now()]);

    $capture = captureWithPhotos($user)->fresh();

    expect($capture->status)->toBe(CaptureStatus::Failed)
        ->and($capture->error_message)->toContain('Monatslimit');
    Http::assertNothingSent();
});

test('a paused web search turn is continued and the audio is served signed', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'server_tool_use', 'id' => 'x', 'name' => 'web_search', 'input' => ['query' => 'Klimt']]], 'stop_reason' => 'pause_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])
        ->push(claudeJson(['summary' => 'fertig'])),
    ]);
    $user = User::factory()->create();

    $data = app(ClaudeClient::class)->structured(AiPurpose::Research, [['role' => 'user', 'content' => 'Suche']], ['type' => 'object'], ['web_search' => true], $user);

    expect($data['summary'])->toBe('fertig')
        ->and(AiCall::query()->count())->toBe(2);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => count($request['messages']) === 2 && $request['messages'][1]['role'] === 'assistant');

    $capture = Capture::factory()->done()->for($user)->create();
    $guide = $capture->audioGuides()->create(['script' => [['role' => 'narrator', 'text' => 'Hallo']], 'audio_path' => 'captures/'.$capture->getKey().'/guide.mp3']);
    Storage::disk('local')->put($guide->audio_path, 'ID3');

    $this->actingAs($user)->get($guide->url())->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    $this->actingAs(User::factory()->create())->get($guide->url())->assertForbidden();
    $this->actingAs($user)->get(route('audio.show', $guide))->assertForbidden();
});

test('the finished capture page shows player, facts, guests, sources and takes feedback', function () {
    $user = User::factory()->create();
    $capture = Capture::factory()->done()->for($user)->create();
    $capture->artwork->research()->create(['summary' => 'x', 'sources' => [['url' => 'https://www.belvedere.at/kuss', 'title' => 'Belvedere: Der Kuss', 'kind' => 'museum'], ['_facts' => []]], 'existing_guides' => []]);
    $capture->audioGuides()->create(['script' => scriptJson()['segments'], 'audio_path' => 'captures/1/guide.mp3', 'duration_seconds' => 95]);
    $capture->factSheets()->create(scriptJson()['fact_sheet']);

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Anhören')
        ->assertSee('15 s zurück')
        ->assertSee('Für deine Gäste')
        ->assertSee('Wo endet der Kuss?')
        ->assertSee('Belvedere: Der Kuss')
        ->assertSee('Provenienz')
        ->assertSee('Vom Staat 1908 gekauft')
        ->assertSee('Kuratorenstimme')
        ->assertSee('Blattgold stammt')
        ->call('feedback', 'up')
        ->call('difficulty', 'too_hard');

    expect($capture->audioGuide->fresh()->feedback)->toBe('up')
        ->and($capture->audioGuide->fresh()->difficulty_feedback)->toBe('too_hard');
});

test('elevenlabs sends key and voice and keeps the guide readable when the voice fails', function () {
    config()->set('museumguide.tts.elevenlabs_key', 'el-key');
    config()->set('museumguide.tts.elevenlabs.voices.narrator', 'voice-1');
    Http::fake(['api.elevenlabs.io/*' => Http::sequence()->push('MP3BYTES', 200, ['Content-Type' => 'audio/mpeg'])->push('quota', 401)]);

    $provider = new ElevenLabsTtsProvider;
    $result = $provider->synthesize('Hallo Welt', 'narrator');

    expect($result->audio)->toBe('MP3BYTES')->and($result->characters)->toBe(10)->and($provider->name())->toBe('elevenlabs');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/text-to-speech/voice-1') && $request->hasHeader('xi-api-key', 'el-key') && $request['text'] === 'Hallo Welt');

    $this->app->instance(TtsProvider::class, $provider);
    $user = User::factory()->create();
    $capture = Capture::factory()->done()->for($user)->create(['status' => CaptureStatus::Scripted]);
    $capture->artwork->research()->create(['summary' => 'x', 'sources' => [], 'existing_guides' => []]);
    $capture->audioGuides()->create(['script' => scriptJson()['segments'], 'word_count' => 20]);

    (new SynthesizeAudio($capture->getKey()))->handle();

    $capture->refresh();
    expect($capture->audioGuide->audio_path)->toBeNull()
        ->and($capture->error_message)->toContain('Stimme ist ausgefallen')
        ->and(AiCall::query()->where('purpose', AiPurpose::Tts)->where('succeeded', false)->exists())->toBeTrue();
});

test('the elevenlabs provider is bound when a key exists', function () {
    config()->set('museumguide.tts.provider', 'auto');
    config()->set('museumguide.tts.elevenlabs_key', 'el-key');

    expect(app(TtsProvider::class))->toBeInstanceOf(ElevenLabsTtsProvider::class);
});
