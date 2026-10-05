<?php

namespace App\Jobs;

use App\Enums\CaptureStatus;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Services\Pipeline\Voice;

/**
 * Skript einsprechen (App\Services\Pipeline\Voice). Danach ist die Aufnahme fertig (das Lernen laeuft still
 * nach). Scheitert die Stimme, bleibt der Guide ohne Audio lesbar, die Aufnahme ist trotzdem fertig.
 */
class SynthesizeAudio extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $guide = $capture->audioGuide;

        if ($guide === null) {
            throw new \RuntimeException('Kein Skript zum Einsprechen.');
        }

        $capture->forceFill(['step' => PipelineStep::Speaking])->save();
        $error = app(Voice::class)->synthesize($capture, $guide, rich: true);

        $capture->forceFill([
            'status' => CaptureStatus::Done,
            'step' => null,
            'finished_at' => now(),
            'error_message' => $error !== null ? 'Die Stimme ist ausgefallen: '.$error.' Text und Fakten sind trotzdem da.' : null,
        ])->save();
    }
}
