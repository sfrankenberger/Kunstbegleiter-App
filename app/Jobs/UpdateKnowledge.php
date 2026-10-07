<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Models\Capture;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schritt 8: was erzaehlt wurde, in KnowledgeItem eintragen (Kuenstler, Epoche), Aufnahme fertig melden.
 * Ein Fehler hier laesst den Guide fertig, nur das Lerngedaechtnis bleibt leer.
 */
class UpdateKnowledge extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $guide = $capture->fullGuide ?? $capture->audioGuide;
        $artwork = $capture->artwork;

        try {
            if ($guide !== null && $artwork !== null && ($artwork->artist !== null || $artwork->epoch !== null)) {
                $result = app(ClaudeClient::class)->structured(AiPurpose::Small, [['role' => 'user', 'content' => 'Fasse zusammen.']], Schemas::knowledge(), [
                    'system' => Prompts::render('knowledge', [
                        'artist' => $artwork->artist?->name ?? 'unbekannt',
                        'epoch' => $artwork->epoch?->name ?? 'keine',
                        'title' => $artwork->title,
                        'script' => $guide->scriptText(),
                    ]),
                    'effort' => 'low',
                    'max_tokens' => 1024,
                ], $capture->user, $capture);

                if ($artwork->artist !== null && filled($result['artist_summary'] ?? null)) {
                    $capture->user->knowledgeItems()->create(['subject_type' => $artwork->artist::class, 'subject_id' => $artwork->artist->getKey(), 'capture_id' => $capture->getKey(), 'summary' => (string) $result['artist_summary'], 'told_at' => now()]);
                }

                if ($artwork->epoch !== null && filled($result['epoch_summary'] ?? null)) {
                    $capture->user->knowledgeItems()->create(['subject_type' => $artwork->epoch::class, 'subject_id' => $artwork->epoch->getKey(), 'capture_id' => $capture->getKey(), 'summary' => (string) $result['epoch_summary'], 'told_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($capture->status !== CaptureStatus::Done) {
            $capture->forceFill(['status' => CaptureStatus::Done, 'step' => null, 'finished_at' => now()])->save();
        }
    }
}
