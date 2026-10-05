<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\ContextBuilder;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schritt 6: zweiter, kurzer Aufruf prueft das Skript gegen die Recherche; nicht belegte Fakten und Zitate
 * werden entfernt oder als Deutung markiert. Die Aenderungen stehen im Skript (changes).
 */
class CheckFacts extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $guide = $capture->audioGuide;
        $research = $capture->artwork?->research;

        if ($guide === null || $research === null) {
            throw new \RuntimeException('Kein Skript oder keine Recherche zum Prüfen.');
        }

        $capture->forceFill(['step' => PipelineStep::Checking])->save();

        $result = app(ClaudeClient::class)->structured(AiPurpose::Check, [['role' => 'user', 'content' => "Recherche:\n".ContextBuilder::researchText($research)."\n\nBelegte Zitate:\n".json_encode(ContextBuilder::quotes($research), JSON_UNESCAPED_UNICODE)."\n\nSkript:\n".json_encode($guide->script, JSON_UNESCAPED_UNICODE)]], Schemas::check(), [
            'system' => Prompts::render('check'),
            'effort' => 'low',
            'max_tokens' => 8192,
        ], $capture->user, $capture);

        $segments = array_values(array_filter((array) ($result['segments'] ?? []), fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null)));

        if ($segments !== []) {
            $guide->forceFill([
                'script' => $segments,
                'word_count' => str_word_count(strip_tags(implode(' ', array_column($segments, 'text')))),
                'cost_cents' => (int) $capture->aiCalls()->sum('cost_cents'),
            ])->save();
        }

        $capture->forceFill(['step' => PipelineStep::Speaking])->save();
    }
}
