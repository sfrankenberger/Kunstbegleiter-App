<?php

use App\Contracts\PlacesClient;
use App\Livewire\Pages\Jetzt;
use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use App\Services\Places\GooglePlacesClient;
use App\Services\Visits\VisitService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('one museum nearby starts the visit right away', function () {
    config()->set('museumguide.places.radius_m', 150);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Jetzt::class)
        ->call('locate', 48.2037, 16.3616, 15)
        ->assertSee('Kunsthistorisches Museum Wien')
        ->assertSee('noch 30 Minuten');

    $visit = $user->activeVisit();
    expect($visit)->not->toBeNull()
        ->and($visit->museum->name)->toBe('Kunsthistorisches Museum Wien')
        ->and($visit->museum->place_id)->toBe('fake-khm')
        ->and($visit->city?->name)->toBe('Wien')
        ->and((float) $visit->lat)->toBe(48.2037);
});

test('several museums nearby ask which one and a tap chooses', function () {
    config()->set('museumguide.places.radius_m', 1500);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Jetzt::class)
        ->call('locate', 48.2037, 16.3616, 15)
        ->assertSee('Wo bist du?')
        ->assertSee('Leopold Museum')
        ->assertSee('Albertina');

    expect($user->activeVisit())->toBeNull();

    $component->call('chooseMuseum', 'fake-leopold');

    expect($user->activeVisit()?->museum?->name)->toBe('Leopold Museum')
        ->and(Museum::query()->count())->toBe(1);
});

test('without a hit the museum can be typed in or the visit starts without one', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Jetzt::class)
        ->call('locate', 47.0707, 15.4395, 20)
        ->assertSee('Ich bin im ...')
        ->set('manualName', 'Kunsthaus Graz')
        ->set('manualCity', 'Graz')
        ->call('chooseManual')
        ->assertHasNoErrors();

    expect($user->activeVisit()?->museum?->name)->toBe('Kunsthaus Graz')
        ->and($user->activeVisit()?->city?->name)->toBe('Graz');

    $other = User::factory()->create();
    Livewire::actingAs($other)->test(Jetzt::class)->call('locationFailed', 'kein GPS')->assertSee('kein GPS')->call('startWithoutMuseum');
    expect($other->activeVisit())->not->toBeNull()->and($other->activeVisit()->museum_id)->toBeNull();
});

test('a visit can get its museum later and be ended', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create(['museum_id' => null]);

    Livewire::actingAs($user)->test(Jetzt::class)
        ->assertSee('Museum eintragen')
        ->set('manualName', 'Belvedere')
        ->call('chooseManual');

    expect($visit->fresh()->museum?->name)->toBe('Belvedere');

    Livewire::actingAs($user)->test(Jetzt::class)->call('endVisit')->assertSee('Besuch starten');
    expect($user->activeVisit())->toBeNull();
});

test('a new visit ends the previous one and reuses a known museum', function () {
    $user = User::factory()->create();
    $service = app(VisitService::class);
    $first = $service->start($user, 48.2, 16.3, 10, $service->museumFromPlace(['place_id' => 'fake-khm', 'name' => 'KHM', 'address' => '1010 Wien']));
    $second = $service->start($user, 48.2, 16.3, 10, $service->museumFromPlace(['place_id' => 'fake-khm', 'name' => 'KHM', 'address' => '1010 Wien']));

    expect($first->fresh()->isActive())->toBeFalse()
        ->and($second->isActive())->toBeTrue()
        ->and(Museum::query()->count())->toBe(1)
        ->and($user->activeVisit()?->is($second))->toBeTrue();
});

test('the city is read from the address', function (string $address, ?string $city, ?string $country) {
    $found = app(VisitService::class)->cityFromAddress($address);
    expect($found?->name)->toBe($city)->and($found?->country_code)->toBe($country);
})->with([
    ['Maria-Theresien-Platz, 1010 Wien, Österreich', 'Wien', 'AT'],
    ['Museumsplatz 1, 1070 Wien', 'Wien', 'AT'],
    ['Kunstareal, 80333 München, Deutschland', 'München', 'DE'],
    ['Ohne Postleitzahl', null, null],
]);

test('the google places client maps the nearby search', function () {
    config()->set('museumguide.places.provider', 'google');
    config()->set('museumguide.places.key', 'test-key');
    Http::fake([GooglePlacesClient::ENDPOINT => Http::response(['places' => [
        ['id' => 'ChIJ1', 'displayName' => ['text' => 'Albertina', 'languageCode' => 'de'], 'formattedAddress' => 'Albertinaplatz 1, 1010 Wien', 'location' => ['latitude' => 48.2044, 'longitude' => 16.3683], 'websiteUri' => 'https://www.albertina.at'],
        ['id' => 'ChIJ2', 'displayName' => ['text' => 'KHM'], 'location' => ['latitude' => 48.2037, 'longitude' => 16.3616]],
    ]])]);

    $client = app(PlacesClient::class);
    expect($client)->toBeInstanceOf(GooglePlacesClient::class);

    $result = $client->nearbyMuseums(48.2037, 16.3616, 300);

    expect($result)->toHaveCount(2)
        ->and($result[0]['name'])->toBe('KHM')
        ->and($result[1]['place_id'])->toBe('ChIJ1')
        ->and($result[1]['website'])->toBe('https://www.albertina.at');
    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'test-key') && $request['locationRestriction']['circle']['radius'] === 300);
});

test('a failing museum search still lets the user type the museum', function () {
    $user = User::factory()->create();
    $this->mock(PlacesClient::class)->shouldReceive('nearbyMuseums')->andThrow(new RuntimeException('down'));

    Livewire::actingAs($user)->test(Jetzt::class)
        ->call('locate', 48.2, 16.3, 10)
        ->assertSee('Die Museumssuche hat nicht geantwortet')
        ->assertSee('Ich bin im ...');
});
