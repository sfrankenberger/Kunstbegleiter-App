<?php

use App\Enums\GuideMode;
use App\Jobs\StartCapture;
use App\Models\Capture;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('a queued upload creates the capture once, even when the phone sends it again', function () {
    Bus::fake();
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->expired()->create();
    $payload = fn () => [
        'client_id' => 'abc-123',
        'full' => '1',
        'visit_id' => $visit->getKey(),
        'photos' => [UploadedFile::fake()->image('schild.jpg', 400, 300), UploadedFile::fake()->image('werk.jpg', 800, 600)],
        'types' => ['label', 'artwork'],
    ];

    $first = $this->actingAs($user)->post('/aufnahmen', $payload(), ['Accept' => 'application/json'])->assertCreated();
    $again = $this->actingAs($user)->post('/aufnahmen', $payload(), ['Accept' => 'application/json'])->assertOk();

    $capture = Capture::query()->sole();
    expect($first->json('url'))->toBe(route('aufnahme', $capture))
        ->and($again->json('id'))->toBe($capture->getKey())
        ->and($capture->visit_id)->toBe($visit->getKey())
        ->and($capture->mode)->toBe(GuideMode::Full)
        ->and($capture->photos)->toHaveCount(2)
        ->and($capture->photos->pluck('type.value')->all())->toBe(['label', 'artwork']);
    Bus::assertDispatched(StartCapture::class);
});

test('without any visit the upload is refused with a message', function () {
    $this->actingAs(User::factory()->create())
        ->post('/aufnahmen', ['client_id' => 'x', 'photos' => [UploadedFile::fake()->image('w.jpg')]], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJson(['message' => 'Bitte zuerst einen Besuch starten.']);
});
