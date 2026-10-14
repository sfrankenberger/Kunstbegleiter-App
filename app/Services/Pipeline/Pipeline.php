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
use App\Models\AudioGuide;
use App\Models\Capture;
use App\Models\FactSheet;
use App\Services\Captures\CaptureService;
use App\Support\QueueKick;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
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
            if (! $capture->isQuick() && $this->reuseFull($capture)) {
                // Vertiefender Guide schon vorhanden: nur noch die Schnellstufe schreiben, Seite pollt weiter
                $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing])->save();
                $this->queue([new QuickOverview($capture->getKey())]);

                return;
            }

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
    public function continueAfterRecognition(Capture $capture, bool $background = false): void
    {
        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing, 'needs_confirmation' => false, 'error_message' => null])->save();

        // Nach der Rueckfrage (Tipp auf ein Werk): alles im Hintergrund, die Seite zeigt sofort den Fortschritt
        // (Sebastian, 15.10.2026: "muss 5 Mal klicken und es passiert erst dann was")
        if ($background && $capture->quickGuide()->doesntExist()) {
            if (! $capture->isQuick() && $this->reuseFull($capture)) {
                $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Writing])->save();
                $this->queue([new QuickOverview($capture->getKey())]);

                return;
            }

            $this->queue(array_merge([new QuickOverview($capture->getKey())], $capture->isQuick() ? [] : self::fullChain($capture)));

            return;
        }

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
     * Vertiefender Guide ueber die Queue; die Schnellstufe bleibt waehrenddessen anhoerbar. Gibt es zum selben Werk
     * oder Ort schon einen fertigen vertiefenden Guide mit Audio, wird er kopiert (sofort, ohne Kosten, Abschnitt 25).
     */
    private function queueFull(Capture $capture, bool $fresh = false): void
    {
        if (! $fresh && $this->reuseFull($capture)) {
            return;
        }

        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Researching, 'error_message' => null])->save();
        $this->queue(self::fullChain($capture));
    }

    /**
     * "Persoenlich neu erstellen": vertiefenden Guide ohne Wiederverwendung neu recherchieren und schreiben.
     */
    public function regenerateFull(Capture $capture): void
    {
        $this->assertBudget($capture);
        $capture->forceFill(['mode' => GuideMode::Full])->save();
        $this->queueFull($capture, fresh: true);
    }

    /**
     * Fertigen vertiefenden Guide eines anderen Besuchs zum selben Werk oder Ort uebernehmen (Text, Kurzfakten, Audio
     * als eigene Kopie). Nicht fuer Premium-Aufnahmen und nicht aelter als `museumguide.reuse.days`.
     */
    private function reuseFull(Capture $capture): bool
    {
        if (! (bool) config('museumguide.reuse.enabled', true) || $capture->premium || ($capture->artwork_id === null && $capture->place_id === null)) {
            return false;
        }

        $donor = AudioGuide::query()
            ->where('kind', 'full')
            ->whereNotNull('audio_path')
            ->where('created_at', '>', now()->subDays((int) config('museumguide.reuse.days', 180)))
            ->whereHas('capture', fn ($q) => $q->whereKeyNot($capture->getKey())->where($capture->artwork_id !== null ? 'artwork_id' : 'place_id', $capture->artwork_id ?? $capture->place_id))
            ->latest('id')
            ->first();

        if ($donor === null || ! Storage::disk(CaptureService::DISK)->exists((string) $donor->audio_path)) {
            return false;
        }

        $guide = $capture->audioGuides()->create([
            'kind' => 'full',
            'source_guide_id' => $donor->source_guide_id ?? $donor->getKey(),
            'script' => $donor->script,
            'word_count' => $donor->word_count,
            'model' => $donor->model,
            'tts_provider' => $donor->tts_provider,
            'tts_characters' => 0,
            'duration_seconds' => $donor->duration_seconds,
            'cost_cents' => 0,
        ]);

        $path = 'captures/'.$capture->getKey().'/guide-'.$guide->getKey().'.mp3';
        Storage::disk(CaptureService::DISK)->copy((string) $donor->audio_path, $path);
        $guide->forceFill(['audio_path' => $path])->save();

        $sheet = FactSheet::query()->where('capture_id', $donor->capture_id)->where('kind', 'full')->latest('id')->first();

        if ($sheet !== null) {
            $capture->factSheets()->create(['kind' => 'full'] + $sheet->only(['key_facts', 'key_statements', 'cross_references', 'sections']));
        }

        $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now(), 'error_message' => null])->save();

        // Lerngedaechtnis trotzdem fortschreiben (Haiku, guenstig)
        $this->queue([new UpdateKnowledge($capture->getKey())]);

        return true;
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
            // Ohne Betrag fuer Testnutzer, nur der Admin sieht Zahlen (14.10.2026)
            throw new RuntimeException($user->is_admin
                ? 'Monatslimit erreicht ('.number_format($limit / 100, 2, ',', '.').' €). Im Admin erhöhen oder nächsten Monat weitermachen.'
                : 'Das Kontingent für diesen Monat ist aufgebraucht. Bitte Sebastian Bescheid geben, er schaltet mehr frei.');
        }
    }
}
