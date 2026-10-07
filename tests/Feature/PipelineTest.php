<?php

use App\Contracts\TtsProvider;
use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PipelineStep;
use App\Jobs\FetchImages;
use App\Jobs\ProfileArtist;
use App\Jobs\SynthesizeAudio;
use App\Livewire\Pages\Aufnahme;
use App\Livewire\Pages\Jetzt;
use App\Livewire\Pages\Profil;
use App\Models\AiCall;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\Epoch;
use App\Models\KnowledgeItem;
use App\Models\Museum;
use App\Models\Research;
use App\Models\User;
use App\Models\Visit;
use App\Services\Ai\ClaudeClient;
use App\Services\Images\WikiImages;
use App\Services\Pipeline\AudioMixer;
use App\Services\Pipeline\MusicBed;
use App\Services\Pipeline\Pipeline;
use App\Services\Pipeline\Schemas;
use App\Services\Tts\ElevenLabsTtsProvider;
use App\Services\Tts\FakeTtsProvider;
use App\Support\Secrets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

/*
 * Pipeline-Tests mit gefaelschten API-Antworten (nie echte Kosten). Die Queue laeuft in Tests synchron, eine Kette
 * wird also sofort und der Reihe nach abgearbeitet: Erkennen, Recherche, Skript, Pruefen, Stimme (Fake), Merken.
 */

beforeEach(function () {
    Storage::fake('local');
    config()->set('museumguide.anthropic.key', 'test-key');
    config()->set('museumguide.tts.provider', 'fake');
});

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
        ->assertSee('Vorlesen')
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

    // Beide Stufen bleiben waehlbar, Standard ist der fertige vertiefende Guide
    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSeeInOrder(['Schnell', 'Vertiefend'])
        ->assertSet('guideKind', '')
        ->call('selectKind', 'quick')
        ->assertSee('Vorlesen');
});

test('the quick mode speaks with the studio voice when a real provider is bound', function () {
    config()->set('museumguide.tts.elevenlabs_key', 'el-key');
    $this->app->instance(TtsProvider::class, new ElevenLabsTtsProvider);
    Http::fake([
        'api.anthropic.com/*' => Http::response(claudeJson(recognitionJson() + scriptJson())),
        'api.elevenlabs.io/*' => Http::response('MP3BYTES', 200, ['Content-Type' => 'audio/mpeg']),
    ]);
    $user = User::factory()->create();

    $capture = captureWithPhotos($user, null, GuideMode::Quick)->fresh();

    expect($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->audioGuide->tts_provider)->toBe('elevenlabs')
        ->and($capture->audioGuide->audio_path)->not->toBeNull()
        ->and(AiCall::query()->where('purpose', AiPurpose::Tts)->where('succeeded', true)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($capture->audioGuide->audio_path);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/text-to-speech/NBqeXKdZHweef6y0B67V'));
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
        ->push(claudeJson(recognitionJson() + scriptJson()))
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
        // Das Schildfoto ist nach dem Ablesen weg, der Text bleibt an der Aufnahme (07.10.2026)
        ->and($capture->photos)->toHaveCount(1)
        ->and($capture->label_text)->toContain('Der Kuss')
        // Beide Stufen bleiben: Schnellstufe zuerst, vertiefend danach (07.10.2026)
        ->and($capture->audioGuides()->count())->toBe(2)
        ->and($capture->quickGuide->kind)->toBe('quick')
        ->and($capture->fullGuide->script)->toHaveCount(3)
        ->and($capture->audioGuide->audio_path)->toBe('captures/'.$capture->getKey().'/guide-'.$capture->audioGuide->getKey().'.mp3')
        ->and($capture->audioGuide->tts_provider)->toBe('fake')
        ->and($capture->factSheets()->where('kind', 'full')->count())->toBe(1)
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
        ->push(claudeJson(recognitionJson() + scriptJson()))
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
        ->push(claudeJson(recognitionJson() + scriptJson()))
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
        ->assertSee('$store.player.load')
        ->assertSee('Mehr zum Künstler')
        ->assertSee('War der Guide gut?')
        ->assertSee('Belvedere: Der Kuss')
        ->assertSee('Provenienz')
        ->assertSee('seit 1908 im Belvedere')
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

test('no schema uses union types, the api allows only a few', function () {
    $hasUnion = function (array $node) use (&$hasUnion): bool {
        foreach ($node as $key => $value) {
            if ($key === 'type' && is_array($value) && array_is_list($value)) {
                return true;
            }

            if (is_array($value) && $hasUnion($value)) {
                return true;
            }
        }

        return false;
    };

    foreach (['recognition', 'quickGuide', 'guide', 'research', 'script', 'museum', 'knowledge'] as $name) {
        expect($hasUnion(Schemas::$name()))->toBeFalse($name);
    }

    expect(Schemas::normalize(['title' => ' ', 'artist_born' => 0, 'sections' => ['quote_text' => '', 'artist' => 'x']]))
        ->toBe(['title' => null, 'artist_born' => null, 'sections' => ['quote_text' => null, 'artist' => 'x']]);
});

test('the full guide speaks every role with its own voice and mixes the pieces', function () {
    $recorder = new class extends FakeTtsProvider
    {
        /** @var list<array{text: string, voice: string}> */
        public array $many = [];

        public function synthesizeMany(array $segments): array
        {
            $this->many = $segments;

            return parent::synthesizeMany($segments);
        }
    };
    $this->app->instance(TtsProvider::class, $recorder);
    $user = User::factory()->create();
    $capture = Capture::factory()->done()->for($user)->create(['status' => CaptureStatus::Scripted]);
    $guide = $capture->audioGuides()->create(['script' => scriptJson()['segments'], 'word_count' => 20]);

    (new SynthesizeAudio($capture->getKey()))->handle();

    expect(array_column($recorder->many, 'voice'))->toBe(['narrator', 'quote', 'narrator'])
        ->and($guide->fresh()->tts_characters)->toBe(array_sum(array_map(fn ($s) => mb_strlen($s['text']), scriptJson()['segments'])))
        ->and($capture->fresh()->status)->toBe(CaptureStatus::Done);
    Storage::disk('local')->assertExists($guide->fresh()->audio_path);
});

test('the mixer joins segments with ffmpeg and lays music underneath, the bed is picked by epoch', function () {
    $mixer = app(AudioMixer::class);

    if (! $mixer->hasFfmpeg()) {
        $this->markTestSkipped('ffmpeg fehlt lokal');
    }

    $dir = sys_get_temp_dir().'/kb-music-'.uniqid();
    mkdir($dir);
    $tone = fn (string $file, int $hz, float $seconds) => (new Process(['ffmpeg', '-nostdin', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', "sine=frequency=$hz:duration=$seconds", '-codec:a', 'libmp3lame', '-b:a', '64k', $file]))->mustRun();
    $tone("$dir/a.mp3", 440, 1);
    $tone("$dir/b.mp3", 660, 1);
    $tone("$dir/barock.mp3", 220, 2);
    config()->set('museumguide.music.dir', $dir);
    config()->set('museumguide.music.epochs.rokoko', 'barock');

    $music = app(MusicBed::class);
    $epoch = Epoch::factory()->create(['slug' => 'rokoko', 'name' => 'Rokoko']);
    $artwork = Artwork::factory()->create(['epoch_id' => $epoch->getKey()]);
    expect($music->pick($artwork))->toBe("$dir/barock.mp3")
        ->and($music->pick(Artwork::factory()->create(['epoch_id' => null])))->toBeNull();

    $mixed = $mixer->mix([file_get_contents("$dir/a.mp3"), file_get_contents("$dir/b.mp3")], "$dir/barock.mp3");
    file_put_contents("$dir/out.mp3", $mixed);
    $probe = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', "$dir/out.mp3"]);
    $probe->run();

    // 2 s Intro + 2 x (1 s Ton + 0,5 s Pause) + 3 s Ausklang = etwa 8 Sekunden
    expect(strlen($mixed))->toBeGreaterThan(1000)
        ->and((float) trim($probe->getOutput()))->toBeGreaterThan(6.5)->toBeLessThan(9.5);

    File::deleteDirectory($dir);
});

test('elevenlabs synthesizes in groups below the concurrency limit and retries once on 429', function () {
    config()->set('museumguide.tts.elevenlabs_key', 'el-key');
    config()->set('museumguide.tts.elevenlabs.concurrency', 5);
    config()->set('museumguide.tts.elevenlabs.retry_seconds', 0);
    $calls = 0;
    Http::fake(['api.elevenlabs.io/*' => function () use (&$calls) {
        $calls++;

        return $calls === 3 ? Http::response(['detail' => ['code' => 'concurrent_limit_exceeded']], 429) : Http::response('MP3'.$calls, 200);
    }]);

    $segments = array_map(fn (int $i) => ['text' => 'Satz '.$i, 'voice' => $i % 2 ? 'second' : 'narrator'], range(1, 12));
    $results = (new ElevenLabsTtsProvider)->synthesizeMany($segments);

    expect($results)->toHaveCount(12)
        ->and($calls)->toBe(17)
        ->and($results[0]->characters)->toBe(6);
});

test('images for artist, artwork and related works come from wikidata and commons and are stored once', function () {
    Http::fake([
        'www.wikidata.org/w/api.php*' => function ($request) {
            if ($request['action'] === 'wbsearchentities') {
                return Http::response(['search' => [['id' => $request['search'] === 'Gustav Klimt' ? 'Q34661' : 'Q698487', 'label' => $request['search'], 'description' => 'Gemälde von Gustav Klimt']]]);
            }

            return Http::response(['claims' => ['P18' => [['mainsnak' => ['datavalue' => ['value' => $request['entity'] === 'Q34661' ? 'Klimt Portrait.jpg' : 'Der Kuss.jpg']]]]]]);
        },
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => ['1' => ['imageinfo' => [['extmetadata' => ['Artist' => ['value' => '<a>Moriz Nähr</a>'], 'LicenseShortName' => ['value' => 'Public domain']]]]]]]]),
    ]);
    $artist = Artist::factory()->create(['name' => 'Gustav Klimt', 'wikidata_id' => null]);
    $artwork = Artwork::factory()->create(['title' => 'Der Kuss', 'artist_id' => $artist->getKey(), 'wikidata_id' => null]);

    (new FetchImages($artwork->getKey(), [['title' => 'Judith', 'artist' => 'Gustav Klimt', 'year' => '1901', 'reason' => 'Gleiche Goldtechnik']]))->handle(app(WikiImages::class));

    $artist->refresh();
    $artwork->refresh();
    expect($artist->portrait_url)->toContain('Special:FilePath/Klimt_Portrait.jpg')
        ->and($artist->portrait_credit)->toBe('Moriz Nähr, Public domain, Wikimedia Commons')
        ->and($artist->wikidata_id)->toBe('Q34661')
        ->and($artwork->image_url)->toContain('Der_Kuss.jpg')
        ->and($artwork->relatedWorks)->toHaveCount(1)
        ->and($artwork->relatedWorks[0]->image_url)->not->toBeNull()
        ->and($artwork->relatedWorks[0]->reason)->toBe('Gleiche Goldtechnik');

    $calls = count(Http::recorded());
    (new FetchImages($artwork->getKey()))->handle(app(WikiImages::class));
    expect(count(Http::recorded()))->toBe($calls);

    $user = User::factory()->create();
    $capture = Capture::factory()->done()->for($user)->create(['artwork_id' => $artwork->getKey()]);
    $capture->factSheets()->create(['sections' => ['artist' => ['Goldene Periode.']]]);
    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Klimt_Portrait.jpg')
        ->assertSee('Vergleichswerke')
        ->assertSee('Judith');
});

test('an artist profile is researched once and shown with life, works and reception', function () {
    config()->set('museumguide.artist_profile.enabled', true);
    Http::fake(['api.anthropic.com/*' => Http::response(claudeJson([
        'born' => '21. Mai 1471, Nürnberg', 'died' => '6. April 1528, Nürnberg',
        'life' => ['Lehre beim Vater, dann bei Michael Wolgemut.', 'Zwei Italienreisen 1494 und 1505.'],
        'key_works' => [['title' => 'Selbstbildnis im Pelzrock', 'year' => '1500', 'location' => 'Alte Pinakothek München']],
        'style' => ['Kupferstich und Holzschnitt auf neuem Niveau.'], 'reception' => ['Dürer-Jahr 1828 als Beginn des Dürer-Kults.'], 'sources' => [],
    ]))]);
    $user = User::factory()->create();
    $artist = Artist::factory()->create(['name' => 'Albrecht Dürer', 'born_year' => null, 'died_year' => null, 'profile' => null]);
    $artwork = Artwork::factory()->create(['artist_id' => $artist->getKey()]);

    (new ProfileArtist($artist->getKey(), $user->getKey(), $artwork->title))->handle();
    $calls = count(Http::recorded());
    (new ProfileArtist($artist->getKey(), $user->getKey()))->handle();

    $artist->refresh();
    expect($artist->profile['life'])->toHaveCount(2)
        ->and($artist->born_year)->toBe(1471)
        ->and($artist->died_year)->toBe(1528)
        ->and(count(Http::recorded()))->toBe($calls)
        ->and(AiCall::query()->where('purpose', AiPurpose::Artist)->whereBelongsTo($user)->exists())->toBeTrue();
    Http::assertSent(fn ($request) => isset($request['tools'][0]) && $request['tools'][0]['max_uses'] === 3 && str_contains((string) $request['system'], 'Albrecht Dürer'));

    $capture = Capture::factory()->done()->for($user)->create(['artwork_id' => $artwork->getKey()]);
    $capture->factSheets()->create(['sections' => ['artist' => ['Zu diesem Blatt.']]]);
    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Michael Wolgemut')
        ->assertSee('Mehr zum Künstler')
        ->assertSee('Zu diesem Werk')
        ->assertDontSee('Selbstbildnis im Pelzrock');
});

test('a schema the api rejects as too large falls back to plain json in the text', function () {
    Secrets::set('anthropic_key', 'sk-test');
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::sequence()
            ->push(['error' => ['message' => 'The compiled grammar is too large, which would cause performance issues.']], 400)
            ->push(['content' => [['type' => 'text', 'text' => "```json\n{\"artist_summary\": \"Maler\", \"epoch_summary\": \"\"}\n```"]], 'stop_reason' => 'end_turn', 'usage' => []]),
    ]);

    $result = app(ClaudeClient::class)->structured(AiPurpose::Small, [['role' => 'user', 'content' => 'x']], Schemas::knowledge(), ['system' => 'Test']);

    expect($result)->toBe(['artist_summary' => 'Maler', 'epoch_summary' => null]);
    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => ! isset($r['output_config']['format']) && str_contains((string) $r['system'], 'JSON-Schema'));
});
