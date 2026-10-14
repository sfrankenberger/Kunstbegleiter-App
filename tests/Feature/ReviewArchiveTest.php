<?php

use App\Livewire\Pages\Archiv;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('the visit review lists the artworks with key statements and can be shared by a signed link', function () {
    $user = User::factory()->create();
    $museum = Museum::factory()->create(['name' => 'Belvedere']);
    $visit = Visit::factory()->for($user)->create(['museum_id' => $museum->getKey()]);
    $artwork = Artwork::factory()->create(['title' => 'Der Kuss', 'museum_id' => $museum->getKey()]);
    $capture = Capture::factory()->for($user)->for($visit)->done()->create(['artwork_id' => $artwork->getKey()]);
    $capture->factSheets()->create(['kind' => 'quick', 'key_statements' => ['Höhepunkt der Goldenen Periode.']]);
    $capture->questions()->create(['user_id' => $user->getKey(), 'question' => 'Wer kaufte das Bild?', 'answer' => 'Der Staat, 1908.']);

    $this->actingAs($user)->get('/rueckblick/'.$visit->getKey())->assertOk()
        ->assertSee('Belvedere')->assertSee('Der Kuss')->assertSee('Goldenen Periode')->assertSee('Wer kaufte das Bild?')->assertSee('Link teilen');
    $this->actingAs(User::factory()->create())->get('/rueckblick/'.$visit->getKey())->assertForbidden();

    auth()->logout();
    $this->get(URL::temporarySignedRoute('rueckblick.shared', now()->addDay(), ['visit' => $visit->getKey()]))->assertOk()->assertSee('Der Kuss')->assertDontSee('Link teilen');
    $this->get('/rueckblick/'.$visit->getKey().'/geteilt')->assertForbidden();
});

test('the archive filters by artist and lists visits with their review', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();
    $klimt = Artist::factory()->create(['name' => 'Gustav Klimt']);
    $schiele = Artist::factory()->create(['name' => 'Egon Schiele']);
    Capture::factory()->for($user)->for($visit)->done()->create(['artwork_id' => Artwork::factory()->create(['title' => 'Der Kuss', 'artist_id' => $klimt->getKey()])->getKey()]);
    Capture::factory()->for($user)->for($visit)->done()->create(['artwork_id' => Artwork::factory()->create(['title' => 'Die Umarmung', 'artist_id' => $schiele->getKey()])->getKey()]);

    Livewire::actingAs($user)->test(Archiv::class)
        ->assertSee('Besuche')->assertSee('Die Umarmung')
        ->set('artistId', $klimt->getKey())
        ->assertSee('Der Kuss')->assertDontSee('Die Umarmung')->assertSee('Filter zurücksetzen')
        ->call('resetFilters')
        ->assertSee('Die Umarmung');
});
