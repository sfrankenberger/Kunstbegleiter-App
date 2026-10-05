<?php

namespace App\Services\Pipeline;

use App\Enums\CaptureStatus;
use App\Enums\PipelineStep;
use App\Jobs\CheckFacts;
use App\Jobs\RecognizeArtwork;
use App\Jobs\ResearchArtwork;
use App\Jobs\SynthesizeAudio;
use App\Jobs\UpdateKnowledge;
use App\Jobs\WriteScript;
use App\Models\AiCall;
use App\Models\Capture;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

/**
 * Die Kette der Queue-Jobs je Aufnahme (docs/grundgeruest.md, Pipeline): Erkennen, Recherchieren, Schreiben,
 * Pruefen, Einsprechen, Merken. `start` beginnt vorne, `resume` setzt nach der Bestaetigung der Erkennung fort,
 * `retry` wiederholt ab dem Schritt, an dem es gescheitert ist. Vorher wird das Monatslimit geprueft.
 */
class Pipeline
{
    public function start(Capture $capture): void
    {
        $this->assertBudget($capture);
        $capture->forceFill(['status' => CaptureStatus::Uploaded, 'step' => PipelineStep::Recognizing, 'error_message' => null, 'needs_confirmation' => false])->save();

        Bus::chain([new RecognizeArtwork($capture->getKey())])->dispatch();
    }

    /**
     * Nach der Erkennung (oder der Bestaetigung): Recherche bis Merken.
     */
    public function continueAfterRecognition(Capture $capture): void
    {
        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Researching, 'needs_confirmation' => false, 'error_message' => null])->save();

        Bus::chain([
            new ResearchArtwork($capture->getKey()),
            new WriteScript($capture->getKey()),
            new CheckFacts($capture->getKey()),
            new SynthesizeAudio($capture->getKey()),
            new UpdateKnowledge($capture->getKey()),
        ])->dispatch();
    }

    /**
     * "Erneut versuchen" nach einem Fehler: ab dem letzten erreichten Stand weiter.
     */
    public function retry(Capture $capture): void
    {
        $this->assertBudget($capture);

        if ($capture->artwork_id === null) {
            $this->start($capture);

            return;
        }

        $capture->forceFill(['error_message' => null])->save();

        match ($capture->status) {
            CaptureStatus::Recognized, CaptureStatus::Uploaded => $this->continueAfterRecognition($capture),
            CaptureStatus::Researched => $this->chainFrom($capture, PipelineStep::Writing, [new WriteScript($capture->getKey()), new CheckFacts($capture->getKey()), new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())]),
            CaptureStatus::Scripted => $this->chainFrom($capture, PipelineStep::Speaking, [new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())]),
            default => $this->start($capture),
        };
    }

    /**
     * Fehler eines Schritts festhalten (nach allen Wiederholungen des Jobs).
     */
    public static function fail(Capture $capture, string $message): void
    {
        $capture->forceFill(['status' => CaptureStatus::Failed, 'step' => null, 'error_message' => mb_substr($message, 0, 1000)])->save();
    }

    /**
     * @param  list<object>  $jobs
     */
    private function chainFrom(Capture $capture, PipelineStep $step, array $jobs): void
    {
        $capture->forceFill(['step' => $step, 'status' => $capture->status === CaptureStatus::Failed ? CaptureStatus::Researched : $capture->status])->save();
        Bus::chain($jobs)->dispatch();
    }

    private function assertBudget(Capture $capture): void
    {
        $user = $capture->user;
        $limit = $user->monthlyLimitCents();

        if ($limit > 0 && AiCall::monthCents($user) >= $limit) {
            throw new RuntimeException('Monatslimit erreicht ('.number_format($limit / 100, 2, ',', '.').' €). Im Admin erhöhen oder nächsten Monat weitermachen.');
        }
    }
}
