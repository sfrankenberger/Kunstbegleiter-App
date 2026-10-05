<?php

use App\Contracts\PlacesClient;
use App\Filament\Pages\Zugaenge;
use App\Models\Setting;
use App\Models\User;
use App\Services\Places\FakePlacesClient;
use App\Services\Places\GooglePlacesClient;
use App\Support\Secrets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('secrets come from the admin first and from the env as fallback', function () {
    config()->set('museumguide.anthropic.key', 'env-key');
    expect(Secrets::get('anthropic_key'))->toBe('env-key')->and(Secrets::source('anthropic_key'))->toBe('env');

    Secrets::set('anthropic_key', 'admin-key', User::factory()->create());
    expect(Secrets::get('anthropic_key'))->toBe('admin-key')
        ->and(Secrets::source('anthropic_key'))->toBe('admin')
        ->and(Secrets::hint('anthropic_key'))->toBe('••••-key');

    Secrets::set('anthropic_key', null);
    expect(Secrets::get('anthropic_key'))->toBe('env-key');

    config()->set('museumguide.anthropic.key', '');
    expect(Secrets::get('anthropic_key'))->toBeNull()->and(Secrets::source('anthropic_key'))->toBeNull();
});

test('stored secrets are encrypted at rest', function () {
    Secrets::set('google_places_key', 'AIza-geheim');

    $raw = DB::table('settings')->where('key', 'google_places_key')->value('value');
    expect($raw)->not->toContain('AIza-geheim')
        ->and(Setting::query()->firstWhere('key', 'google_places_key')?->value)->toBe('AIza-geheim');
});

test('the places client switches to google once a key is stored', function () {
    config()->set('museumguide.places.provider', 'auto');
    config()->set('museumguide.places.key', '');
    expect(app(PlacesClient::class))->toBeInstanceOf(FakePlacesClient::class);

    Secrets::set('google_places_key', 'AIza-test');
    expect(app(PlacesClient::class))->toBeInstanceOf(GooglePlacesClient::class);

    config()->set('museumguide.places.provider', 'fake');
    expect(app(PlacesClient::class))->toBeInstanceOf(FakePlacesClient::class);
});

test('the admin page saves keys without ever showing them', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/zugaenge')->assertOk()->assertSee('Zugänge')->assertSee('fehlt');

    Livewire::actingAs($admin)->test(Zugaenge::class)
        ->fillForm(['anthropic_key' => 'sk-ant-neu-1234', 'google_places_key' => ''])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Gespeichert');

    expect(Secrets::get('anthropic_key'))->toBe('sk-ant-neu-1234')
        ->and(Setting::query()->firstWhere('key', 'anthropic_key')?->updated_by_id)->toBe($admin->getKey())
        ->and(Secrets::get('google_places_key'))->toBeNull();

    $this->actingAs($admin)->get('/admin/zugaenge')->assertOk()->assertSee('••••1234')->assertDontSee('sk-ant-neu-1234');

    Livewire::actingAs($admin)->test(Zugaenge::class)->callAction('forget', arguments: ['name' => 'anthropic_key']);
    expect(Secrets::get('anthropic_key'))->toBeNull();
});

test('the connection checks use the stored keys', function () {
    config()->set('museumguide.places.provider', 'auto');
    $admin = User::factory()->admin()->create();
    Secrets::set('anthropic_key', 'sk-ant-ok');
    Secrets::set('google_places_key', 'AIza-ok');
    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response(['data' => []]),
        GooglePlacesClient::ENDPOINT => Http::response(['places' => [['id' => 'x', 'displayName' => ['text' => 'KHM'], 'location' => ['latitude' => 48.2037, 'longitude' => 16.3616]]]]),
    ]);

    Livewire::actingAs($admin)->test(Zugaenge::class)
        ->callAction('checkAnthropic')->assertNotified('Anthropic antwortet')
        ->callAction('checkPlaces')->assertNotified('Google Places antwortet');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic') && $request->hasHeader('x-api-key', 'sk-ant-ok'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'places.googleapis') && $request->hasHeader('X-Goog-Api-Key', 'AIza-ok'));
});

test('non admins cannot open the page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/zugaenge')->assertForbidden();
});
