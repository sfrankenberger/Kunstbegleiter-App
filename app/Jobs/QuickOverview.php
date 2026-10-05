<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PhotoType;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schnellstufe (docs/konzept.md Abschnitt 11): ein einziger Aufruf ohne Websuche liefert Kurztext und Fact Sheet.
 * Kein MP3, das Handy liest vor (Web Speech API). Danach ist die Aufnahme fertig; "Ausfuehrlichen Guide
 * erstellen" haengt die volle Kette an (Pipeline::upgrade).
 */
class QuickOverview extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $artwork = $capture->artwork;

        if ($artwork === null) {
            throw new \RuntimeException('Kein Werk an der Aufnahme.');
        }

        $capture->forceFill(['step' => PipelineStep::Writing])->save();
        $museum = $capture->visit?->museum ?? $artwork->museum;
        $labelText = $capture->photos->filter(fn ($p) => $p->type !== PhotoType::Artwork)->pluck('ocr_text')->filter()->implode("\n");
        $words = (array) config('museumguide.quick.words', ['min' => 150, 'max' => 250]);

        $result = app(ClaudeClient::class)->structured(AiPurpose::Quick, [['role' => 'user', 'content' => 'Schreibe jetzt Kurztext und Fact Sheet als JSON.']], Schemas::script(), [
            'system' => Prompts::render('quick', [
                'knowledge_profile' => filled($capture->user->knowledge_profile) ? (string) $capture->user->knowledge_profile : 'Austria Guide in Wien, breites Vorwissen zur Kunstgeschichte.',
                'title' => $artwork->title,
                'artist' => $artwork->artist?->name ?? 'unbekannt',
                'dating' => $artwork->dating ?? 'ohne Datierung',
                'technique' => $artwork->technique ?? '',
                'museum' => $museum?->name ?? '',
                'city' => $museum?->city?->name ?? '',
                'label_text' => $labelText !== '' ? $labelText : 'keine',
                'museum_notes' => RecognizeArtwork::museumNotes($museum?->research),
                'words_min' => $words['min'] ?? 150,
                'words_max' => $words['max'] ?? 250,
            ]),
            'effort' => 'low',
            'max_tokens' => 4096,
        ], $capture->user, $capture);

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));
        $call = $capture->aiCalls()->latest('id')->first();

        $guide = $capture->audioGuides()->create([
            'script' => $segments,
            'word_count' => str_word_count(strip_tags(implode(' ', array_column($segments, 'text')))),
            'model' => AiPurpose::Quick->model(),
            'tts_provider' => 'browser',
            'input_tokens' => (int) ($call?->input_tokens ?? 0),
            'output_tokens' => (int) ($call?->output_tokens ?? 0),
            'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
        ]);

        $sheet = (array) ($result['fact_sheet'] ?? []);
        $capture->factSheets()->create([
            'key_facts' => (array) ($sheet['key_facts'] ?? []),
            'key_statements' => (array) ($sheet['key_statements'] ?? []),
            'guest_ideas' => (array) ($sheet['guest_ideas'] ?? []),
            'cross_references' => (array) ($sheet['cross_references'] ?? []),
        ]);

        $capture->setRelation('audioGuide', $guide);
        $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
    }
}
