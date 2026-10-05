<?php

namespace App\Jobs;

use App\Contracts\TtsProvider;
use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PipelineStep;
use App\Models\AiCall;
use App\Models\Capture;
use App\Services\Ai\Pricing;
use App\Services\Captures\CaptureService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Skript einsprechen: eine Stimme (narrator) fuer alle Segmente. Danach ist die Aufnahme fertig (das Lernen
 * laeuft noch still nach). Scheitert die Stimme, bleibt der Guide ohne Audio lesbar, die Aufnahme ist trotzdem fertig.
 */
class SynthesizeAudio extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $guide = $capture->audioGuide;

        if ($guide === null) {
            throw new \RuntimeException('Kein Skript zum Einsprechen.');
        }

        $capture->forceFill(['step' => PipelineStep::Speaking])->save();
        $text = collect($guide->script ?? [])->map(fn (mixed $s): string => is_array($s) ? (string) ($s['text'] ?? '') : '')->filter()->implode("\n\n");
        $provider = app(TtsProvider::class);
        $started = hrtime(true);

        try {
            $result = $provider->synthesize($text, 'narrator');
        } catch (Throwable $e) {
            AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $provider->name(), 'characters' => mb_strlen($text), 'succeeded' => false, 'error' => mb_substr($e->getMessage(), 0, 500), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);
            $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now(), 'error_message' => 'Die Stimme ist ausgefallen: '.mb_substr($e->getMessage(), 0, 200).' Text und Fakten sind trotzdem da.'])->save();

            return;
        }

        $path = 'captures/'.$capture->getKey().'/guide-'.$guide->getKey().'.mp3';
        Storage::disk(CaptureService::DISK)->put($path, $result->audio);
        $cost = Pricing::ttsCents($provider->name(), $result->characters);

        AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $provider->name(), 'characters' => $result->characters, 'cost_cents' => $cost, 'succeeded' => true, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

        $guide->forceFill([
            'audio_path' => $path,
            'duration_seconds' => $result->durationSeconds ?? (int) ceil(($guide->word_count ?? 400) / 150 * 60),
            'tts_provider' => $provider->name(),
            'tts_characters' => $result->characters,
            'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
        ])->save();

        $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
    }
}
