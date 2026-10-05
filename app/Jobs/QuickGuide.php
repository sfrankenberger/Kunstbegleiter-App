<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Models\Epoch;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Pipeline;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schnellstufe in einem Aufruf (Ziel 10 Sekunden, docs/konzept.md Abschnitt 13): Fotos rein, Erkennung,
 * Kurztext und Fact Sheet raus. Laeuft synchron in der Anfrage (Pipeline::start), nicht ueber die Queue.
 * Ist die Erkennung unsicher, bleibt nur die Erkennung mit Rueckfrage; nach der Bestaetigung schreibt
 * QuickOverview den Kurztext.
 */
class QuickGuide extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $capture->forceFill(['step' => PipelineStep::Recognizing])->save();
        $museum = $capture->visit?->museum;
        $content = [];

        foreach ($capture->photos as $index => $photo) {
            $content[] = ['type' => 'text', 'text' => 'Foto '.($index + 1).':'];
            $content[] = RecognizeArtwork::imageBlock($photo);
        }

        $content[] = ['type' => 'text', 'text' => 'Erkenne das Werk (Foto-Index beginnt bei 0) und schreibe gleich den Überblick als JSON.'];
        $words = (array) config('museumguide.quick.words', ['min' => 100, 'max' => 160]);

        $result = app(ClaudeClient::class)->structured(AiPurpose::Quick, [['role' => 'user', 'content' => $content]], Schemas::quickGuide(), [
            'system' => Prompts::render('quick', [
                'museum' => $museum?->name ?? 'unbekannt',
                'city' => $capture->visit?->city?->name ?? $museum?->city?->name ?? 'unbekannt',
                'museum_notes' => RecognizeArtwork::museumNotes($museum?->research),
                'knowledge_profile' => filled($capture->user->knowledge_profile) ? (string) $capture->user->knowledge_profile : 'Austria Guide in Wien, breites Vorwissen zur Kunstgeschichte.',
                'epochs' => Epoch::query()->orderBy('sort_order')->pluck('name')->implode(', '),
                'words_min' => $words['min'] ?? 100,
                'words_max' => $words['max'] ?? 160,
            ]),
            'effort' => 'low',
            'max_tokens' => 3000,
        ], $capture->user, $capture);

        if (! RecognizeArtwork::store($capture, $result)) {
            return;
        }

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));

        if ($segments === []) {
            // Das Modell hat nur erkannt, aber nichts geschrieben: Kurztext nachholen
            app(Pipeline::class)->continueAfterRecognition($capture);

            return;
        }

        QuickOverview::storeGuide($capture, $segments, (array) ($result['fact_sheet'] ?? []));
        $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
    }
}
