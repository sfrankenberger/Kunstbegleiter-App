<?php

namespace App\Services\Pipeline;

use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PipelineStep;
use App\Jobs\QuickGuide;
use App\Jobs\QuickOverview;
use App\Jobs\ResearchAndWrite;
use App\Jobs\SynthesizeAudio;
use App\Jobs\UpdateKnowledge;
use App\Models\AiCall;
use App\Models\Capture;
use App\Support\QueueKick;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

/**
 * Ablauf je Aufnahme (docs/konzept.md Abschnitte 10, 11, 13, 25). Immer zuerst die Schnellstufe: ein Aufruf,
 * synchron in der Anfrage (Ziel 10 Sekunden). Ausfuehrlich: danach Recherche plus Skript, Stimme und Lernen ueber
 * die Queue (Ziel 45 Sekunden bis zur Stimme), der Worker wird sofort angestossen; beide Guides bleiben. `retry` setzt nach einem Fehler
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
            $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing, 'needs_confirmation' => false])->save();
            $this->queue(array_merge([new QuickOverview($capture->getKey())], $capture->isQuick() ? [] : self::fullChain($capture)));

            return;
        }

        // Immer zuerst die Schnellstufe (Erkennung plus Kurztext in einem Aufruf, synchron), ausfuehrlich dann
        // im Hintergrund dazu (Sebastian, 07.10.2026: "dass immer auch der schnelle erstellt wird")
        Bus::dispatchSync(new QuickGuide($capture->getKey()));
        $capture->refresh();

        if (! $capture->isQuick() && ! $capture->needs_confirmation && $capture->isDone()) {
            $this->queueFull($capture);
        }
    }

    /**
     * Nach der Erkennung (oder der Bestaetigung): Schnellstufe synchron, falls sie noch fehlt, ausfuehrlich
     * danach ueber die Queue.
     */
    public function continueAfterRecognition(Capture $capture): void
    {
        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing, 'needs_confirmation' => false, 'error_message' => null])->save();

        if ($capture->quickGuide()->doesntExist()) {
            Bus::dispatchSync(new QuickOverview($capture->getKey()));
            $capture->refresh();
        }

        if ($capture->isQuick()) {
            if (! $capture->isDone()) {
                $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
            }

            return;
        }

        $this->queueFull($capture);
    }

    /**
     * Aus der Schnellstufe den vertiefenden Guide nachbestellen: Werk und Schnellstufe bleiben, Recherche plus
     * Skript, Stimme, Lernen laufen dazu.
     */
    public function upgrade(Capture $capture): void
    {
        $this->assertBudget($capture);

        if ($capture->artwork_id === null && $capture->place_id === null) {
            throw new RuntimeException('Zuerst das Werk bestätigen.');
        }

        $capture->forceFill(['mode' => GuideMode::Full])->save();
        $this->continueAfterRecognition($capture);
    }

    /**
     * Vertiefender Guide ueber die Queue; die Schnellstufe bleibt waehrenddessen anhoerbar.
     */
    private function queueFull(Capture $capture): void
    {
        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Researching, 'error_message' => null])->save();
        $this->queue(self::fullChain($capture));
    }

    /** @return list<object> */
    private static function fullChain(Capture $capture): array
    {
        return [new ResearchAndWrite($capture->getKey()), new SynthesizeAudio($capture->getKey()), new UpdateKnowledge($capture->getKey())];
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
        $guide = $capture->fullGuide;

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
