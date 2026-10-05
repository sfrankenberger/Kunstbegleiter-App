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
 * Skript einsprechen und MP3 am Guide ablegen: schnell mit einer Stimme am Stueck, ausfuehrlich mit zwei Sprechern
 * je Rolle (narrator, second, quote), parallel angefragt, zusammengesetzt und mit Musikbett (AudioMixer, MusicBed). Wird von SynthesizeAudio (ausfuehrlich)
 * und von der Schnellstufe (QuickGuide, QuickOverview) genutzt. Liefert die Fehlermeldung oder null bei Erfolg;
 * jeder Versuch steht in ai_calls.
 */
class Voice
{
    public function __construct(private readonly TtsProvider $provider, private readonly AudioMixer $mixer, private readonly MusicBed $music) {}

    /**
     * Nur echte Anbieter sprechen in der Schnellstufe; der Fake (ohne Schluessel) laesst das Handy vorlesen.
     */
    public function isReal(): bool
    {
        return $this->provider->name() !== 'fake';
    }

    /**
     * @param  bool  $rich  Zwei Sprecher je Rolle und Musikbett (ausfuehrlich); sonst eine Stimme am Stueck (schnell).
     */
    public function synthesize(Capture $capture, AudioGuide $guide, bool $rich = false): ?string
    {
        $segments = collect($guide->script ?? [])
            ->filter(fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null))
            ->map(fn (array $s): array => ['text' => (string) $s['text'], 'voice' => self::voiceFor((string) ($s['role'] ?? 'narrator'))])
            ->values()
            ->all();
        $characters = array_sum(array_map(fn (array $s): int => mb_strlen($s['text']), $segments));
        $started = hrtime(true);

        try {
            if ($rich) {
                $results = $this->provider->synthesizeMany($segments);
                $audio = $this->mixer->mix(array_map(fn ($r) => $r->audio, $results), $this->music->pick($capture->artwork));
                $duration = array_sum(array_map(fn ($r) => (int) ($r->durationSeconds ?? 0), $results)) ?: null;
            } else {
                $result = $this->provider->synthesize(implode("\n\n", array_column($segments, 'text')), 'narrator');
                $audio = $result->audio;
                $duration = $result->durationSeconds;
            }
        } catch (Throwable $e) {
            AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $this->provider->name(), 'characters' => $characters, 'succeeded' => false, 'error' => mb_substr($e->getMessage(), 0, 500), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

            return mb_substr($e->getMessage(), 0, 200);
        }

        $path = 'captures/'.$capture->getKey().'/guide-'.$guide->getKey().'.mp3';
        Storage::disk(CaptureService::DISK)->put($path, $audio);

        AiCall::query()->create(['user_id' => $capture->user_id, 'capture_id' => $capture->getKey(), 'purpose' => AiPurpose::Tts, 'model' => $this->provider->name(), 'characters' => $characters, 'cost_cents' => Pricing::ttsCents($this->provider->name(), $characters), 'succeeded' => true, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

        $guide->forceFill([
            'audio_path' => $path,
            'duration_seconds' => $duration ?? (int) ceil(($guide->word_count ?? 400) / 150 * 60),
            'tts_provider' => $this->provider->name(),
            'tts_characters' => $characters,
            'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
        ])->save();

        return null;
    }

    /**
     * Rolle im Skript auf die Stimme beim Anbieter: narrator, second, quote.
     */
    public static function voiceFor(string $role): string
    {
        return in_array($role, ['narrator', 'second', 'quote'], true) ? $role : 'narrator';
    }
}
