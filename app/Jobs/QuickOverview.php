<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PhotoType;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Services\Pipeline\Voice;
use App\Support\Prompts;
use App\Support\QueueKick;

/**
 * Schnellstufe (docs/konzept.md Abschnitt 11): ein einziger Aufruf ohne Websuche liefert Kurztext und Fact Sheet.
 * Danach die Studio-Stimme (Voice); faellt sie aus oder fehlt der Schluessel, liest das Handy vor. Danach ist die Aufnahme fertig; "Ausfuehrlichen Guide
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
        $words = (array) config('museumguide.quick.words', ['min' => 100, 'max' => 160]);

        $result = app(ClaudeClient::class)->structured(AiPurpose::Quick, [['role' => 'user', 'content' => 'Schreibe jetzt Kurztext und Fact Sheet als JSON.']], Schemas::script(), [
            'system' => Prompts::render('quick-text', [
                'knowledge_profile' => filled($capture->user->knowledge_profile) ? (string) $capture->user->knowledge_profile : 'Austria Guide in Wien, breites Vorwissen zur Kunstgeschichte.',
                'title' => $artwork->title,
                'artist' => $artwork->artist?->name ?? 'unbekannt',
                'dating' => $artwork->dating ?? 'ohne Datierung',
                'technique' => $artwork->technique ?? '',
                'museum' => $museum?->name ?? '',
                'city' => $museum?->city?->name ?? '',
                'label_text' => $labelText !== '' ? $labelText : 'keine',
                'museum_notes' => RecognizeArtwork::museumNotes($museum?->research),
                'words_min' => $words['min'] ?? 100,
                'words_max' => $words['max'] ?? 160,
            ]),
            'effort' => 'low',
            'max_tokens' => 2048,
        ], $capture->user, $capture);

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));
        self::storeGuide($capture, $segments, (array) ($result['fact_sheet'] ?? []));
        $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
    }

    /**
     * Bilder (Portraet, Werk, Vergleichswerke) und Kuenstlerprofil in der Queue nachladen, ohne die Antwortzeit zu verlaengern.
     *
     * @param  array<string, mixed>  $sheet
     */
    public static function fetchImages(Capture $capture, array $sheet): void
    {
        if ($capture->artwork_id === null) {
            return;
        }

        if ((bool) config('museumguide.images.enabled', true)) {
            FetchImages::dispatch((int) $capture->artwork_id, array_values(array_filter((array) ($sheet['sections']['related_works'] ?? []), 'is_array')));
        }

        $artist = $capture->artwork?->artist;

        if ($artist !== null && (bool) config('museumguide.artist_profile.enabled', true) && ($artist->profile_checked_at === null || $artist->profile_checked_at->lt(now()->subDays((int) config('museumguide.artist_profile.days', 180))))) {
            ProfileArtist::dispatch($artist->getKey(), $capture->user_id, (string) $capture->artwork?->title);
        }

        QueueKick::now();
    }

    /**
     * Kurztext (ohne MP3, das Handy liest vor) und Fact Sheet anlegen.
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $sheet
     */
    public static function storeGuide(Capture $capture, array $segments, array $sheet): void
    {
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

        $capture->factSheets()->create([
            'key_facts' => (array) ($sheet['key_facts'] ?? []),
            'key_statements' => (array) ($sheet['key_statements'] ?? []),
            'cross_references' => (array) ($sheet['cross_references'] ?? []),
            'sections' => is_array($sheet['sections'] ?? null) ? $sheet['sections'] : null,
        ]);

        $capture->setRelation('audioGuide', $guide);
        self::fetchImages($capture, $sheet);

        // Studio-Stimme auch in der Schnellstufe (Sebastian, 05.10.2026); ohne Anbieter liest das Handy vor
        $voice = app(Voice::class);

        if ($voice->isReal()) {
            $capture->forceFill(['step' => PipelineStep::Speaking])->save();
            $voice->synthesize($capture, $guide);
        }
    }
}
