<?php

use App\Enums\GuideLength;
use App\Livewire\Pages\Profil;
use App\Models\AiCall;
use App\Models\Capture;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

test('every tab loads for a signed-in user', function (string $path, string $text) {
    $this->actingAs(User::factory()->create())->get($path)->assertOk()->assertSee($text);
})->with([
    ['/jetzt', 'Museum in der Nähe suchen'],
    ['/archiv', 'Noch keine Werke im Archiv'],
    ['/entdecken', 'Tipps in der Stadt'],
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
