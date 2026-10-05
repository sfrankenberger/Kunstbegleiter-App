<?php

use App\Enums\GuideLength;
use App\Livewire\Pages\Profil;
use App\Models\AiCall;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

test('every tab loads for a signed-in user', function (string $path, string $text) {
    $this->actingAs(User::factory()->create())->get($path)->assertOk()->assertSee($text);
})->with([
    ['/jetzt', 'Museum in der Nähe suchen'],
    ['/archiv', 'Noch keine Werke im Archiv'],
    ['/kuenstler', 'Noch keine Künstler'],
    ['/profil', 'Vorwissen'],
]);

test('jetzt shows the active visit and its captures', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();
    Capture::factory()->for($visit)->for($user)->done()->create();
    Visit::factory()->for($user)->expired()->create();

    $this->actingAs($user)->get('/jetzt')
        ->assertSee($visit->museum->name)
        ->assertSee('Werke dieses Besuchs');
});

test('archiv lists finished captures and searches by artist', function () {
    $user = User::factory()->create();
    $capture = Capture::factory()->for($user)->done()->create();
    $other = Capture::factory()->done()->create();

    $this->actingAs($user)->get('/archiv')
        ->assertSee($capture->artwork->title)
        ->assertSee(route('aufnahme', $capture))
        ->assertDontSee($other->artwork->title);

    $this->actingAs($user)->get('/archiv?q='.urlencode($capture->artwork->artist->name))->assertSee($capture->artwork->title);
    $this->actingAs($user)->get('/archiv?q=gibtesnicht')->assertSee('Nichts gefunden');
});

test('profile saves knowledge profile and length', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profil::class)
        ->set('knowledge_profile', 'Austria Guide, Schwerpunkt Wien um 1900')
        ->set('preferred_length', 'long')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    expect($user->fresh()->knowledge_profile)->toBe('Austria Guide, Schwerpunkt Wien um 1900')
        ->and($user->fresh()->preferred_length)->toBe(GuideLength::Long);
});

test('profile shows the monthly costs against the limit', function () {
    $user = User::factory()->create(['monthly_budget_cents' => 1000]);
    AiCall::factory()->for($user)->create(['cost_cents' => 850]);

    $this->actingAs($user)->get('/profil')->assertSee('8,50 € von 10,00 €')->assertSee('85 %');
});

test('the artist tab lists artists of own captures and the artist page shows the profile', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create(['name' => 'Egon Schiele', 'born_year' => 1890, 'died_year' => 1918, 'profile' => ['born' => '12. Juni 1890, Tulln', 'died' => '31. Oktober 1918, Wien', 'life' => ['Schüler von Klimt.'], 'key_works' => [['title' => 'Die Familie', 'year' => '1918', 'location' => 'Belvedere']], 'style' => [], 'reception' => ['Leopold Museum als Zentrum der Schiele-Forschung.'], 'sources' => []]]);
    $capture = Capture::factory()->for($user)->done()->create(['artwork_id' => Artwork::factory()->create(['artist_id' => $artist->getKey(), 'title' => 'Sitzende Frau'])->getKey()]);
    Artist::factory()->create(['name' => 'Fremder Maler']);

    $this->actingAs($user)->get('/kuenstler')->assertOk()->assertSee('Egon Schiele')->assertSee('1 Werk')->assertDontSee('Fremder Maler');
    $this->actingAs($user)->get(route('kuenstler.show', $artist))->assertOk()
        ->assertSee('Tulln')->assertSee('Schüler von Klimt')->assertSee('Die Familie')->assertSee('Schiele-Forschung')->assertSee('Sitzende Frau');
    $this->actingAs($user)->get(route('aufnahme', $capture))->assertOk()->assertSee('player.js');
});
