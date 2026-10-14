<?php

use App\Livewire\Pages\Aufnahme;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\CaptureQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config()->set('museumguide.anthropic.key', 'test-key');
    config()->set('museumguide.tts.provider', 'fake');
});

test('a question about the artwork gets a short answer with the context of the capture', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Links sitzt die Stifterin, erkennbar am Wappen.']], 'usage' => ['input_tokens' => 500, 'output_tokens' => 60], 'stop_reason' => 'end_turn'])]);
    $user = User::factory()->create();
    $artwork = Artwork::factory()->create(['title' => 'Der Kuss']);
    $capture = Capture::factory()->for($user)->done()->create(['artwork_id' => $artwork->getKey(), 'label_text' => 'Saal 7: Goldene Periode']);

    Livewire::actingAs($user)->test(Aufnahme::class, ['capture' => $capture])
        ->assertSee('Frag nach')
        ->set('question', 'Wer ist die Figur links?')
        ->call('ask')
        ->assertSet('questionError', '')
        ->assertSet('question', '')
        ->assertSee('Links sitzt die Stifterin');

    $entry = CaptureQuestion::query()->sole();
    expect($entry->question)->toBe('Wer ist die Figur links?')
        ->and($entry->audio_path)->toBeNull();
    Http::assertSent(fn ($r) => str_contains((string) $r['system'], 'Der Kuss') && str_contains((string) $r['system'], 'Saal 7') && $r['messages'][0]['content'] === 'Wer ist die Figur links?' && ! isset($r['tools']));
});
