<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\GuideLength;
use App\Enums\PhotoType;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Models\Research;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\ContextBuilder;
use App\Services\Pipeline\Schemas;
use App\Services\Places\Geocoder;
use App\Support\Prompts;

/**
 * Ausfuehrlich in einem Aufruf (Ziel 45 Sekunden mit Stimme, docs/konzept.md Abschnitt 13): Recherche mit
 * Websuche (hoechstens museumguide.research.max_searches), Skript mit eingebautem Faktencheck und Fact Sheet.
 * Gibt es zum Werk schon eine Recherche (auch von Martha), wird sie mitgegeben und nicht neu gesucht.
 */
class ResearchAndWrite extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $artwork = $capture->artwork;
        $place = $capture->place;

        if ($artwork === null && $place === null) {
            throw new \RuntimeException('Kein Werk an der Aufnahme.');
        }

        $existing = $place !== null ? $place->research : $artwork->research;
        $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => $existing !== null ? PipelineStep::Writing : PipelineStep::Researching])->save();
        $context = app(ContextBuilder::class)->build($capture);
        $museum = $capture->visit?->museum ?? $artwork?->museum;
        $labelText = $capture->labelText();
        $words = ($capture->length ?? GuideLength::Normal)->words();
        $maxSearches = (int) config('museumguide.research.max_searches', 4);

        // Stadt auch ohne eingetragenes Museum (15.10.2026): aus Besuch oder Position
        $city = $museum?->city?->name ?? $capture->visit?->city?->name;

        if ($city === null && ($lat = $capture->visit?->lat ?? $capture->lat) !== null && ($lng = $capture->visit?->lng ?? $capture->lng) !== null) {
            $city = app(Geocoder::class)->city((float) $lat, (float) $lng)?->name;
        }

        // Das Werkfoto geht mit (15.10.2026): bei unsicherer Erkennung kann der Guide ueber das Sichtbare sprechen
        $photo = $capture->photos->firstWhere('type', PhotoType::Artwork) ?? ($place === null ? $capture->photos->first() : null);
        $content = $photo !== null
            ? [RecognizeArtwork::imageBlock($photo), ['type' => 'text', 'text' => 'Das Foto zeigt das Werk, vor dem die Hörerin steht. Recherchiere, schreibe Skript und Fact Sheet und liefere alles als JSON.']]
            : 'Recherchiere, schreibe Skript und Fact Sheet und liefere alles als JSON.';

        $options = [
            'system' => $place !== null ? Prompts::render('place-guide', [
                'max_searches' => $existing !== null ? 0 : $maxSearches,
                'name' => $place->name,
                'kind' => $place->kindLabel(),
                'city' => $place->city?->name ?? 'unbekannt',
                'address' => $place->address ? ', '.$place->address : '',
                'architect' => $place->architect ?? 'unbekannt',
                'built' => $place->built ?? 'unbekannt',
                'description' => $place->description ?? 'keine',
                'label_text' => $labelText,
                'research' => $existing !== null ? ContextBuilder::researchText($existing) : 'keine',
                'knowledge_profile' => $context['knowledge_profile'],
                'knowledge_context' => $context['knowledge_context'],
                'words_min' => $words['min'],
                'words_max' => $words['max'],
            ]) : Prompts::render('guide', [
                'max_searches' => $existing !== null ? 0 : $maxSearches,
                'title' => $artwork?->title ?? '',
                'artist' => $artwork?->artist?->name ?? 'unbekannt',
                'dating' => $artwork?->dating ?? 'ohne Datierung',
                'technique' => $artwork?->technique ?? '',
                'museum' => $museum?->name ?? 'Museum unbekannt',
                'city' => $city ?? 'Stadt unbekannt',
                'label_text' => $labelText,
                'museum_notes' => RecognizeArtwork::museumNotes($museum?->research),
                'research' => $existing !== null ? $context['research'] : 'keine',
                'knowledge_profile' => $context['knowledge_profile'],
                'visit_context' => $context['visit_context'],
                'knowledge_context' => $context['knowledge_context'],
                'words_min' => $words['min'],
                'words_max' => $words['max'],
            ]),
            'model' => $capture->premium ? (string) config('museumguide.models.script_premium') : null,
            'web_search' => $existing === null,
            'max_searches' => $maxSearches,
            'effort' => 'medium',
            'max_tokens' => 8192,
        ];

        $result = app(ClaudeClient::class)->structured(AiPurpose::Script, [['role' => 'user', 'content' => $content]], Schemas::guide(), $options, $capture->user, $capture);

        // Spricht das Skript mit dem Nutzer statt ueber das Werk (Rueckfragen, "Fact Sheet", "Schild nennen"), einmal
        // neu schreiben lassen (Sebastian, 15.10.2026: "das hat er falsch gemacht")
        if (self::isMeta((array) ($result['segments'] ?? []))) {
            $options['system'] .= "\n\nWICHTIG: Der letzte Versuch hat statt eines Skripts mit dem Nutzer gesprochen. Das ist verboten. Schreibe jetzt das Gespräch der beiden Fachleute über das, was auf dem Foto zu sehen ist und was zum Künstler und zur Gattung sicher bekannt ist. Keine Rückfragen, keine Sätze über fehlende Angaben, Schild, Skript oder Fact Sheet.";
            $options['web_search'] = false;
            $result = app(ClaudeClient::class)->structured(AiPurpose::Script, [['role' => 'user', 'content' => $content]], Schemas::guide(), $options, $capture->user, $capture);

            if (self::isMeta((array) ($result['segments'] ?? []))) {
                throw new \RuntimeException('Der vertiefende Guide konnte nicht sinnvoll geschrieben werden. Bitte das Werk bestätigen oder den Werktext fotografieren und erneut versuchen.');
            }
        }

        $call = $capture->aiCalls()->latest('id')->first();

        if ($existing === null) {
            Research::query()->create([
                'artwork_id' => $artwork?->getKey(),
                'place_id' => $place?->getKey(),
                'summary' => (string) ($result['summary'] ?? ''),
                'sources' => array_values(array_merge((array) ($result['sources'] ?? []), [['_facts' => $result['facts'] ?? []], ['_quotes' => $result['quotes'] ?? []], ['_vienna' => $result['vienna_links'] ?? []]])),
                'existing_guides' => (array) ($result['existing_guides'] ?? []),
                'model' => $call?->model ?? AiPurpose::Script->model(),
                'input_tokens' => (int) ($call?->input_tokens ?? 0),
                'output_tokens' => (int) ($call?->output_tokens ?? 0),
                'cost_cents' => (int) ($call?->cost_cents ?? 0),
            ]);

            ($artwork ?? $place)->fill(array_filter([
                'facts' => (array) ($result['facts'] ?? []),
                'sources' => $artwork !== null ? (array) ($result['sources'] ?? []) : null,
                'wikidata_id' => $artwork !== null ? ($result['wikidata_id'] ?? null) : null,
            ], fn (mixed $v): bool => $v !== null))->save();

            if ($artwork?->artist && ($artwork->artist->born_year === null || $artwork->artist->died_year === null)) {
                $artwork->artist->fill(array_filter(['born_year' => $result['artist_born'] ?? null, 'died_year' => $result['artist_died'] ?? null]))->save();
            }
        }

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));

        $guide = $capture->audioGuides()->create([
            'kind' => 'full',
            'script' => $segments,
            'word_count' => str_word_count(strip_tags(implode(' ', array_column($segments, 'text')))),
            'model' => $call?->model ?? AiPurpose::Script->model(),
            'input_tokens' => (int) ($call?->input_tokens ?? 0),
            'output_tokens' => (int) ($call?->output_tokens ?? 0),
            'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
        ]);

        $sheet = (array) ($result['fact_sheet'] ?? []);
        $capture->factSheets()->create([
            'kind' => 'full',
            'key_facts' => (array) ($sheet['key_facts'] ?? []),
            'key_statements' => (array) ($sheet['key_statements'] ?? []),
            'cross_references' => (array) ($sheet['cross_references'] ?? []),
            'sections' => is_array($sheet['sections'] ?? null) ? $sheet['sections'] : null,
        ]);

        $capture->setRelation('audioGuide', $guide);
        QuickOverview::fetchImages($capture, $sheet);
        $capture->forceFill(['status' => CaptureStatus::Scripted, 'step' => PipelineStep::Speaking])->save();
    }

    /**
     * Erkennt Skripte, die mit dem Nutzer ueber die App sprechen statt ueber das Werk.
     *
     * @param  array<int, mixed>  $segments
     */
    public static function isMeta(array $segments): bool
    {
        $text = implode(' ', array_map(fn (mixed $s): string => is_array($s) ? (string) ($s['text'] ?? '') : '', $segments));

        if (trim($text) === '') {
            return true;
        }

        return preg_match('/(fact ?sheet|\bskript\b|wenn du mir|nennst du mir|nenn(e|st) mir|brauche ich (gewissheit|mehr)|erstelle ich|schildtext nennen|inventarnummer nennst|ohne (museum|schild)[^.]{0,60}nicht (sicher )?zuordnen)/iu', $text) === 1;
    }
}
