<?php

use App\Enums\CaptureStatus;
use App\Enums\PhotoType;
use App\Livewire\Pages\Aufnahme;
use App\Livewire\Pages\Jetzt;
use App\Models\Capture;
use App\Models\User;
use App\Models\Visit;
use App\Services\Captures\CaptureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('photos become a capture, the visit is extended and the user lands on the capture page', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create(['valid_until' => now()->addMinutes(5)]);

    Livewire::actingAs($user)->test(Jetzt::class)
        ->set('photos', [UploadedFile::fake()->image('werk.jpg', 1200, 900), UploadedFile::fake()->image('schild.jpg', 800, 600)])
        ->assertSet('uploadError', '')
        ->assertSee('2 Fotos bereit')
        ->call('createCapture')
        ->assertRedirect();

    $capture = Capture::query()->first();
    expect($capture)->not->toBeNull()
        ->and($capture->status)->toBe(CaptureStatus::Uploaded)
        ->and($capture->user_id)->toBe($user->getKey())
        ->and($capture->photos)->toHaveCount(2)
        ->and($capture->photos[0]->type)->toBe(PhotoType::Artwork)
        ->and($capture->photos[1]->type)->toBe(PhotoType::Label)
        ->and($capture->photos[0]->width)->toBe(1200)
        ->and($visit->fresh()->remainingMinutes())->toBeGreaterThanOrEqual(29);

    Storage::disk('local')->assertExists($capture->photos[0]->path);
});

test('more than three photos or non images are refused', function () {
    $user = User::factory()->create();
    Visit::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Jetzt::class)
        ->set('photos', [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg'), UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->image('4.jpg')])
        ->assertSet('uploadError', 'Höchstens 3 Fotos je Aufnahme.')
        ->assertSet('photos', []);

    Livewire::actingAs($user)->test(Jetzt::class)
        ->set('photos', [UploadedFile::fake()->create('notiz.pdf', 10, 'application/pdf')])
        ->assertSet('uploadError', 'Nur Bilder sind erlaubt.');
});

test('without an active visit no capture is created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Jetzt::class)
        ->set('photos', [UploadedFile::fake()->image('werk.jpg')])
        ->call('createCapture')
        ->assertSet('uploadError', 'Bitte zuerst einen Besuch starten.');

    expect(Capture::query()->count())->toBe(0);
});

test('the capture page shows photos via signed urls and lets the owner change the type', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();
    $capture = app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('werk.jpg', 400, 300)]);
    $photo = $capture->photos->first();

    $this->actingAs($user)->get(route('aufnahme', $capture))->assertOk()->assertSee('Werk wird erkannt')->assertSee('/fotos/'.$photo->getKey());

    $this->actingAs($user)->get($photo->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($user)->get('/fotos/'.$photo->getKey())->assertForbidden();

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])->call('setType', $photo->getKey(), 'room_text');
    expect($photo->fresh()->type)->toBe(PhotoType::RoomText);
});

test('other users see neither the capture nor its photos, a shared partner sees both', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $stranger = User::factory()->create();
    $visit = Visit::factory()->for($owner)->create(['shared_with_user_id' => $partner->getKey()]);
    $capture = app(CaptureService::class)->create($owner, $visit, [UploadedFile::fake()->image('werk.jpg')]);
    $url = $capture->photos->first()->url();

    $this->actingAs($stranger)->get(route('aufnahme', $capture))->assertForbidden();
    $this->actingAs($stranger)->get($url)->assertForbidden();
    $this->actingAs($partner)->get(route('aufnahme', $capture))->assertOk();
    $this->actingAs($partner)->get($url)->assertOk();

    Livewire::actingAs($partner)->test(Aufnahme::class, ['capture' => $capture])->call('delete')->assertForbidden();
});

test('deleting a capture puts it and its photos in the trash', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();
    $capture = app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('werk.jpg')]);

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])->call('delete')->assertRedirect(route('jetzt'));

    expect($capture->fresh()->trashed())->toBeTrue()
        ->and($capture->photos()->withTrashed()->first()->trashed())->toBeTrue();
});

test('jetzt lists the captures of the visit with a thumbnail', function () {
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();
    app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('werk.jpg')]);

    $this->actingAs($user)->get('/jetzt')->assertOk()->assertSee('Werke dieses Besuchs')->assertSee('1 Foto')->assertSee('/fotos/');
});
