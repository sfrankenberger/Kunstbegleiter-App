<?php

use App\Enums\GuideMode;
use App\Livewire\Pages\Jetzt;
use App\Models\RoomText;
use App\Models\User;
use App\Models\Visit;
use App\Services\Captures\CaptureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config()->set('museumguide.anthropic.key', 'test-key');
    config()->set('museumguide.tts.provider', 'fake');
});

test('a room text is scanned, kept in the archive and used as context for the next capture', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeJson(['title' => 'Saal 12: Die Goldene Periode', 'text' => "Klimt arbeitete ab 1903 mit Blattgold.\nDer Saal zeigt Leihgaben."]))
        ->push(claudeJson(recognitionJson() + scriptJson())),
    ]);
    $user = User::factory()->create();
    $visit = Visit::factory()->for($user)->create();

    $component = Livewire::actingAs($user)->test(Jetzt::class)
        ->set('roomPhoto', UploadedFile::fake()->image('saal.jpg', 800, 600))
        ->call('scanRoomText')
        ->assertSet('roomTextError', '')
        ->assertSee('Saal 12: Die Goldene Periode');

    $roomText = RoomText::query()->sole();
    expect($roomText->text)->toContain('Blattgold')
        ->and($roomText->visit_id)->toBe($visit->getKey())
        ->and($component->get('roomTextId'))->toBe($roomText->getKey());
    expect($roomText->hasPhoto())->toBeFalse();
    Storage::disk('local')->assertMissing('room-texts/'.$roomText->getKey().'.jpg');
    Http::assertSent(fn ($r) => str_contains((string) ($r['system'] ?? ''), 'Saaltext') && ($r['messages'][0]['content'][0]['type'] ?? '') === 'image');

    $component->set('photos', [UploadedFile::fake()->image('werk.jpg', 600, 400)])->call('createCapture')->assertRedirect();

    $capture = $visit->captures()->sole();
    expect($capture->room_text_id)->toBe($roomText->getKey())
        ->and($capture->labelText())->toContain('Raumtext aus dem Saal')->toContain('Blattgold');
    Http::assertSent(fn ($r) => str_contains(json_encode($r['messages'], JSON_UNESCAPED_UNICODE), 'Blattgold') && str_contains((string) $r['system'], 'schnellen Überblick'));

    $this->actingAs($user)->get('/archiv')->assertOk()->assertSee('Raumtexte')->assertSee('Saal 12');
    $this->actingAs($user)->get('/raumtext/'.$roomText->getKey())->assertOk()->assertSee('Blattgold')->assertSee('Werke mit diesem Raumtext');
    $this->actingAs(User::factory()->create())->get('/raumtext/'.$roomText->getKey())->assertForbidden();

    // Der Raumtext gehoert zum Nutzer: fremde werden beim Anlegen ignoriert
    $foreign = RoomText::factory()->create(['text' => 'fremd']);
    $other = app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('w.jpg')], GuideMode::Quick, $foreign);
    expect($other->room_text_id)->toBeNull();
});
