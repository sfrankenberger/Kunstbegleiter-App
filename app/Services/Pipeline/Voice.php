<?php

namespace App\Services\Pipeline;

use App\Contracts\TtsProvider;
use App\Enums\AiPurpose;
use App\Models\AiCall;
use App\Models\AudioGuide;
use App\Models\Capture;
use App\Services\Ai\Pricing;
use App\Services\Captures\CaptureService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Skript einsprechen (eine Stimme, narrator) und MP3 am Guide ablegen. Wird von SynthesizeAudio (ausfuehrlich)
 * und von der Schnellstufe (QuickGuide, QuickOverview) genutzt. Liefert die Fehlermeldung oder null bei Erfolg;
 * jeder Versuch steht in ai_calls.
 */
class Voice
{
    public function __construct(private readonly TtsProvider $provider) {}

    /**
     * Nur echte Anbieter sprechen in der Schnellstufe; der Fake (ohne Schluessel) laesst das Handy vorlesen.
     */
    public function isReal(): bool
    {
        return $this->provider->name() !== 'fake';
    }

    public function synthesize(Capture $capture, AudioGuide $guide): ?string
    {
        $text = collect($guide->script ?? [])->map(fn (mixed $s): string => is_array($s) ? (string) ($s['text'] ?? '') : '')->filter()->implode("\n\n");
        $started = hrtime(true);

        try {
            $result = $this->provider->synthesize($text, 'narrator');
        } catch (Throwable $e) {
            AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $this->provider->name(), 'characters' => mb_strlen($text), 'succeeded' => false, 'error' => mb_substr($e->getMessage(), 0, 500), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

            return mb_substr($e->getMessage(), 0, 200);
        }

        $path = 'captures/'.$capture->getKey().'/guide-'.$guide->getKey().'.mp3';
        Storage::disk(CaptureService::DISK)->put($path, $result->audio);

        AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $this->provider->name(), 'characters' => $result->characters, 'cost_cents' => Pricing::ttsCents($this->provider->name(), $result->characters), 'succeeded' => true, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

        $guide->forceFill([
            'audio_path' => $path,
            'duration_seconds' => $result->durationSeconds ?? (int) ceil(($guide->word_count ?? 400) / 150 * 60),
            'tts_provider' => $this->provider->name(),
            'tts_characters' => $result->characters,
            'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
        ])->save();

        return null;
    }
}
