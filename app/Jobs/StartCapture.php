<?php

namespace App\Jobs;

use App\Models\Capture;
use App\Services\Captures\CaptureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Pipeline einer Aufnahme im Hintergrund starten (Upload aus der Offline-Warteschlange, 14.10.2026): die Antwort an
 * das Handy kommt sofort, die Seite der Aufnahme zeigt den Fortschritt.
 */
class StartCapture implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $captureId) {}

    public function handle(CaptureService $captures): void
    {
        $capture = Capture::query()->find($this->captureId);

        if ($capture !== null) {
            $captures->startPipeline($capture);
        }
    }
}
