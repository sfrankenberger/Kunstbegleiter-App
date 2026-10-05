<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PhotoType;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Models\Research;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schritt 3: Recherche mit Websuche, einmal je Werk. Gibt es schon eine Recherche (auch von Martha), wird sie
 * wiederverwendet und nichts aufgerufen.
 */
class ResearchArtwork extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $artwork = $capture->artwork;

        if ($artwork === null) {
            throw new \RuntimeException('Kein Werk an der Aufnahme.');
        }

        if ($artwork->research()->exists()) {
            $capture->forceFill(['status' => CaptureStatus::Researched, 'step' => PipelineStep::Writing])->save();

            return;
        }

        $capture->forceFill(['step' => PipelineStep::Researching])->save();
        $museum = $capture->visit?->museum ?? $artwork->museum;
        $labelText = $capture->photos->filter(fn ($p) => $p->type !== PhotoType::Artwork)->pluck('ocr_text')->filter()->implode("\n");

        $result = app(ClaudeClient::class)->structured(AiPurpose::Research, [['role' => 'user', 'content' => 'Recherchiere dieses Werk und liefere das JSON.']], Schemas::research(), [
            'system' => Prompts::render('research', [
                'title' => $artwork->title,
                'artist' => $artwork->artist?->name ?? 'unbekannt',
                'dating' => $artwork->dating ?? 'unbekannt',
                'museum' => $museum?->name ?? 'unbekannt',
                'city' => $museum?->city?->name ?? '',
                'label_text' => $labelText !== '' ? $labelText : 'nichts',
                'museum_notes' => RecognizeArtwork::museumNotes($museum?->research),
            ]),
            'web_search' => true,
            'effort' => 'medium',
            'max_tokens' => 12000,
        ], $capture->user, $capture);

        $call = $capture->aiCalls()->latest('id')->first();

        Research::query()->create([
            'artwork_id' => $artwork->getKey(),
            'summary' => (string) ($result['summary'] ?? ''),
            'sources' => array_values(array_merge((array) ($result['sources'] ?? []), [['_facts' => $result['facts'] ?? []], ['_quotes' => $result['quotes'] ?? []], ['_vienna' => $result['vienna_links'] ?? []]])),
            'existing_guides' => (array) ($result['existing_guides'] ?? []),
            'model' => AiPurpose::Research->model(),
            'input_tokens' => (int) ($call?->input_tokens ?? 0),
            'output_tokens' => (int) ($call?->output_tokens ?? 0),
            'cost_cents' => (int) ($call?->cost_cents ?? 0),
        ]);

        $artwork->fill(array_filter([
            'facts' => (array) ($result['facts'] ?? []),
            'sources' => (array) ($result['sources'] ?? []),
            'wikidata_id' => $result['wikidata_id'] ?? null,
        ], fn (mixed $v): bool => $v !== null))->save();

        if ($artwork->artist && ($artwork->artist->born_year === null || $artwork->artist->died_year === null)) {
            $artwork->artist->fill(array_filter(['born_year' => $result['artist_born'] ?? null, 'died_year' => $result['artist_died'] ?? null]))->save();
        }

        $capture->forceFill(['status' => CaptureStatus::Researched, 'step' => PipelineStep::Writing])->save();
    }
}
