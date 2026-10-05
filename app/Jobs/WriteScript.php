<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\GuideLength;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\ContextBuilder;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schritte 4 und 5: Kontext bauen (Vorwissen, Besuch, Lerngedaechtnis) und Skript mit Fact Sheet schreiben.
 * Premium-Modell, wenn die Aufnahme es verlangt (captures.premium).
 */
class WriteScript extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $artwork = $capture->artwork;
        $research = $artwork?->research;

        if ($artwork === null || $research === null) {
            throw new \RuntimeException('Keine Recherche zum Werk.');
        }

        $capture->forceFill(['step' => PipelineStep::Writing])->save();
        $context = app(ContextBuilder::class)->build($capture);
        $length = $capture->length ?? GuideLength::Normal;
        $words = $length->words();
        $museum = $capture->visit?->museum ?? $artwork->museum;

        $result = app(ClaudeClient::class)->structured(AiPurpose::Script, [['role' => 'user', 'content' => 'Schreibe jetzt Skript und Fact Sheet als JSON.']], Schemas::script(), [
            'system' => Prompts::render('script', [
                'knowledge_profile' => $context['knowledge_profile'],
                'words_min' => $words['min'],
                'words_max' => $words['max'],
                'title' => $artwork->title,
                'artist' => $artwork->artist?->name ?? 'unbekannt',
                'dating' => $artwork->dating ?? 'ohne Datierung',
                'technique' => $artwork->technique ?? '',
                'museum' => $museum?->name ?? '',
                'city' => $museum?->city?->name ?? '',
                'research' => $context['research'],
                'quotes' => $context['quotes'],
                'visit_context' => $context['visit_context'],
                'knowledge_context' => $context['knowledge_context'],
            ]),
            'model' => $capture->premium ? (string) config('museumguide.models.script_premium') : null,
            'effort' => 'medium',
            'max_tokens' => 8192,
        ], $capture->user, $capture);

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));
        $call = $capture->aiCalls()->latest('id')->first();

        $guide = $capture->audioGuides()->create([
            'script' => $segments,
            'word_count' => str_word_count(strip_tags(implode(' ', array_column($segments, 'text')))),
            'model' => $capture->premium ? (string) config('museumguide.models.script_premium') : AiPurpose::Script->model(),
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
        $capture->forceFill(['status' => CaptureStatus::Scripted, 'step' => PipelineStep::Checking])->save();
    }
}
