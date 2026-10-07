<?php

use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Livewire\Pages\Aufnahme;
use App\Livewire\Pages\Stadt;
use App\Models\Capture;
use App\Models\Place;
use App\Models\User;
use App\Services\Pipeline\PlaceMatcher;
use App\Services\Places\WikiPlaces;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Reiter Stadt (docs/konzept.md Abschnitt 23): Orte aus Wikidata, Guide ueber die bestehende Pipeline.
 * Wikidata und Anthropic sind gefaelscht, keine echten Aufrufe.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    config()->set('museumguide.anthropic.key', 'test-key');
    config()->set('museumguide.tts.provider', 'fake');
});

function sparqlNearby(): array
{
    $row = fn (string $id, string $name, string $desc, float $lng, float $lat, array $classes, ?string $architect = null, ?string $year = null) => [
        'item' => ['value' => 'http://www.wikidata.org/entity/'.$id], 'itemLabel' => ['value' => $name], 'itemDescription' => ['value' => $desc],
        'coord' => ['value' => "Point($lng $lat)"], 'classes' => ['value' => implode(',', array_map(fn ($c) => 'http://www.wikidata.org/entity/'.$c, $classes))],
        'sitelinks' => ['value' => '12'], 'image' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($name).'.jpg'],
    ] + ($architect ? ['architectLabel' => ['value' => $architect]] : []) + ($year ? ['inception' => ['value' => $year.'-01-01T00:00:00Z']] : []);

    return ['results' => ['bindings' => [
        $row('Q697214', 'Pestsäule', 'Barocke Säule am Graben', 16.3698, 48.2089, ['Q4989906'], 'Johann Bernhard Fischer von Erlach', '1693'),
        $row('Q1234', 'Graben', 'Straße in Wien', 16.3700, 48.2088, ['Q79007']),
        $row('Q871070', 'Peterskirche', 'Barockkirche', 16.3697, 48.2095, ['Q16970'], 'Johann Lucas von Hildebrandt', '1733'),
    ]]];
}

function osmFakes(): array
{
    return [
        'overpass-api.de/*' => Http::response(['elements' => [
            ['type' => 'node', 'id' => 11, 'lat' => 48.2088, 'lon' => 16.3697, 'tags' => ['name' => 'Pestsäule', 'historic' => 'monument', 'wikidata' => 'Q697214', 'addr:street' => 'Graben']],
            ['type' => 'node', 'id' => 12, 'lat' => 48.2090, 'lon' => 16.3700, 'tags' => ['name' => 'Leopoldsbrunnen', 'amenity' => 'fountain', 'artist_name' => 'Johann Martin Fischer', 'start_date' => '1804']],
            ['type' => 'way', 'id' => 13, 'center' => ['lat' => 48.2091, 'lon' => 16.3702], 'tags' => ['name' => 'Graben', 'highway' => 'pedestrian']],
        ]]),
        'nominatim.openstreetmap.org/*' => Http::response(['address' => ['city' => 'Wien', 'country_code' => 'at']]),
    ];
}

test('nearby places come from wikidata without streets, nearest first, and a tap starts the quick guide', function () {
    Http::fake(osmFakes() + [
        'query.wikidata.org/*' => Http::response(sparqlNearby()),
        'api.anthropic.com/*' => Http::response(claudeJson(scriptJson())),
    ]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Stadt::class)
        ->call('locate', 48.2089, 16.3698, 10)
        ->assertSee('Pestsäule')
        ->assertSee('Fischer von Erlach')
        ->assertSee('Peterskirche')
        ->assertSee('Leopoldsbrunnen')
        ->assertSee('Johann Martin Fischer')
        ->assertDontSee('Straße in Wien')
        ->assertSet('nearby.0.wikidata_id', 'Q697214')
        ->assertSet('nearby.0.osm_id', 'node/11')
        ->assertSet('nearby.1.wikidata_id', 'Q871070');

    $component->call('choose', 'Q697214')->assertRedirect();

    $place = Place::query()->firstWhere('wikidata_id', 'Q697214');
    $capture = Capture::query()->first();
    expect($place->kind)->toBe('monument')
        ->and($place->architect)->toBe('Johann Bernhard Fischer von Erlach')
        ->and($place->built)->toBe('1693')
        ->and($place->image_url)->toContain('Special:FilePath')
        ->and($place->osm_id)->toBe('node/11')
        ->and($place->address)->toBe('Graben')
        ->and($place->city->name)->toBe('Wien')
        ->and($capture->place_id)->toBe($place->getKey())
        ->and($capture->visit_id)->toBeNull()
        ->and($capture->mode)->toBe(GuideMode::Quick)
        ->and($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->audioGuide->script)->toHaveCount(3);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && str_contains((string) $request['system'], 'Pestsäule') && str_contains((string) $request['system'], 'Architekt oder Bildhauer Johann Bernhard Fischer von Erlach'));

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Pestsäule')
        ->assertSee('Denkmal, Wien')
        ->assertSee('Baugeschichte')
        ->assertDontSee('Mehr zum Künstler');

    Livewire::actingAs($user)->test(Stadt::class)->assertSee('Zuletzt angesehen')->assertSee('Pestsäule');
});

test('a typed place is looked up at wikidata and the full guide runs through research and voice', function () {
    Http::fake(osmFakes() + [
        'www.wikidata.org/*' => Http::response(['search' => [['id' => 'Q871070', 'label' => 'Peterskirche', 'description' => 'Barockkirche in Wien']]]),
        'query.wikidata.org/*' => Http::response(['results' => ['bindings' => [[
            'coord' => ['value' => 'Point(16.3697 48.2095)'], 'classes' => ['value' => 'http://www.wikidata.org/entity/Q16970'],
            'image' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Peterskirche.jpg'], 'architectLabel' => ['value' => 'Johann Lucas von Hildebrandt'], 'inception' => ['value' => '1733-01-01T00:00:00Z'],
        ]]]]),
        'api.anthropic.com/*' => Http::sequence()->push(claudeJson(scriptJson()))->push(claudeJson(researchJson() + scriptJson()))->push(claudeJson(knowledgeJson())),
    ]);
    $user = User::factory()->create(['default_mode' => GuideMode::Full]);

    Livewire::actingAs($user)->test(Stadt::class)
        ->set('manualName', 'Peterskirche')
        ->call('chooseManual')
        ->assertRedirect();

    $place = Place::query()->firstWhere('wikidata_id', 'Q871070');
    $capture = Capture::query()->first();
    expect($place->kind)->toBe('church')
        ->and($place->lat)->toEqualWithDelta(48.2095, 0.0001)
        ->and($capture->mode)->toBe(GuideMode::Full)
        ->and($capture->status)->toBe(CaptureStatus::Done)
        ->and($place->fresh()->research)->not->toBeNull()
        ->and($place->fresh()->research->summary)->toContain('Belvedere')
        ->and($capture->quickGuide)->not->toBeNull()
        ->and($capture->fullGuide->audio_path)->not->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && isset($request['tools'][0]) && str_contains((string) $request['system'], 'Wien Geschichte Wiki'));
});

test('a photo in the city is recognised and matched to the nearest wikidata place', function () {
    Http::fake(osmFakes() + [
        'query.wikidata.org/*' => Http::response(sparqlNearby()),
        'api.anthropic.com/*' => Http::response(claudeJson(array_merge(recognitionJson(), scriptJson(), ['title' => 'Pestsäule am Graben', 'artist' => 'Fischer von Erlach', 'inventory_number' => '']))),
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Stadt::class)
        ->call('locate', 48.2089, 16.3698, 10)
        ->set('showPhoto', true)
        ->set('photos', [UploadedFile::fake()->image('saeule.jpg', 800, 600)])
        ->call('createFromPhotos')
        ->assertRedirect();

    $capture = Capture::query()->first();
    expect($capture->visit_id)->toBeNull()
        ->and($capture->place->wikidata_id)->toBe('Q697214')
        ->and($capture->place->name)->toBe('Pestsäule')
        ->and($capture->status)->toBe(CaptureStatus::Done)
        ->and($capture->photos)->toHaveCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && str_contains((string) $request['system'], 'In der Nähe laut Wikidata: Pestsäule (0 m') && str_contains((string) $request['system'], 'Stadt Wien'));
});

test('the similarity check and the distance are forgiving but not sloppy', function () {
    expect(PlaceMatcher::similar('Pestsäule', 'Pestsaeule am Graben'))->toBeTrue()
        ->and(PlaceMatcher::similar('Peterskirche', 'Pestsäule'))->toBeFalse()
        ->and((int) round(WikiPlaces::distance(48.2089, 16.3698, 48.2095, 16.3697)))->toBeBetween(60, 75);
});

test('an osm-only place gets its own record keyed by osm id and the city from geocoding', function () {
    Http::fake(osmFakes() + [
        'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
        'api.anthropic.com/*' => Http::response(claudeJson(scriptJson())),
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Stadt::class)
        ->call('locate', 48.2089, 16.3698, 10)
        ->assertSee('Leopoldsbrunnen')
        ->assertDontSee('Graben')
        ->call('choose', 'node/12')
        ->assertRedirect();

    $place = Place::query()->firstWhere('osm_id', 'node/12');
    expect($place)->not->toBeNull()
        ->and($place->kind)->toBe('fountain')
        ->and($place->architect)->toBe('Johann Martin Fischer')
        ->and($place->built)->toBe('1804')
        ->and($place->wikidata_id)->toBeNull()
        ->and($place->city->country_code)->toBe('AT');
});
