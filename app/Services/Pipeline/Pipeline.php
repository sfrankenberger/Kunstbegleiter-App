<?php

namespace App\Services\Pipeline;

use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PipelineStep;
use App\Jobs\QuickGuide;
use App\Jobs\QuickOverview;
use App\Jobs\RecognizeArtwork;
use App\Jobs\ResearchAndWrite;
use App\Jobs\SynthesizeAudio;
use App\Jobs\UpdateKnowledge;
use App\Models\AiCall;
use App\Models\Capture;
use App\Support\QueueKick;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

/**
 * Ablauf je Aufnahme (docs/konzept.md Abschnitte 10, 11, 13). Schnell: ein Aufruf, synchron in der Anfrage
 * (Ziel 10 Sekunden). Ausfuehrlich: Erkennung synchron, dann Recherche plus Skript, Stimme und Lernen ueber die
 * Queue (Ziel 45 Sekunden bis zur Stimme), der Worker wird sofort angestossen. `retry` setzt nach einem Fehler
 * beim letzten erreichten Stand fort, `upgrade` bestellt aus der Schnellstufe den ausfuehrlichen Guide.
 * Vorher wird jeweils das Monatslimit geprueft.
 */
class Pipeline
{
    public function start(Capture $capture): void
    {
        $this->assertBudget($capture);
        $capture->forceFill(['status' => CaptureStatus::Uploaded, 'step' => PipelineStep::Recognizing, 'error_message' => null, 'needs_confirmation' => false])->save();

        if ($capture->isPlace() && $capture->photos()->doesntExist()) {
            // Ort aus der Liste gewaehlt: nichts zu erkennen. Ueber die Queue, damit die Seite sofort wechselt
            // und den Fortschritt zeigt (Sebastian, 05.10.2026: "dauert ewig, keine Klick-Animation")
            $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => $capture->isQuick() ? PipelineStep::Writing : PipelineStep::Researching, 'needs_confirmation' => false])->save();

            if ($capture->isQuick()) {
                $this->queue([new QuickOverview($capture->getKey())]);
            } else {
                $this->queue([new ResearchAndWrite($capture->getKey()), new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())]);
            }

            return;
        }

        if ($capture->isQuick()) {
            Bus::dispatchSync(new QuickGuide($capture->getKey()));

            return;
        }

        Bus::dispatchSync(new RecognizeArtwork($capture->getKey()));
    }

    /**
     * Nach der Erkennung (oder der Bestaetigung): schnell ein Aufruf synchron, ausfuehrlich die Kette ueber die Queue.
     */
    public function continueAfterRecognition(Capture $capture): void
    {
        if ($capture->isQuick()) {
            $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing, 'needs_confirmation' => false, 'error_message' => null])->save();
            Bus::dispatchSync(new QuickOverview($capture->getKey()));

            return;
        }

        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Researching, 'needs_confirmation' => false, 'error_message' => null])->save();
        $this->queue([new ResearchAndWrite($capture->getKey()), new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())]);
    }

    /**
     * Aus der Schnellstufe den ausfuehrlichen Guide nachbestellen: Werk bleibt, Recherche plus Skript, Stimme, Lernen.
     */
    public function upgrade(Capture $capture): void
    {
        $this->assertBudget($capture);

        if ($capture->artwork_id === null && $capture->place_id === null) {
            throw new RuntimeException('Zuerst das Werk bestätigen.');
        }

        $capture->forceFill(['mode' => GuideMode::Full, 'finished_at' => null])->save();
        $this->continueAfterRecognition($capture);
    }

    /**
     * "Erneut versuchen" nach einem Fehler: ab dem letzten erreichten Stand weiter.
     */
    public function retry(Capture $capture): void
    {
        $this->assertBudget($capture);

        if ($capture->artwork_id === null && $capture->place_id === null) {
            $this->start($capture);

            return;
        }

        $capture->forceFill(['error_message' => null])->save();
        $guide = $capture->audioGuide;

        if (! $capture->isQuick() && $guide !== null && filled($guide->script) && ! $guide->hasAudio()) {
            $capture->forceFill(['status' => CaptureStatus::Scripted, 'step' => PipelineStep::Speaking])->save();
            $this->queue([new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())]);

            return;
        }

        $this->continueAfterRecognition($capture);
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
    private function queue(array $jobs): void
    {
        Bus::chain($jobs)->dispatch();
        QueueKick::now();
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
