<?php

namespace App\Jobs;

use App\Enums\CaptureStatus;
use App\Models\Capture;
use App\Services\Pipeline\Pipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Gemeinsamer Rahmen der Pipeline-Jobs: drei Versuche mit Pause, danach steht der Fehler an der Aufnahme und die
 * Kette bricht ab (docs/grundgeruest.md: "drei Wiederholungen, dann klare Meldung mit Erneut versuchen").
 */
abstract class PipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly int $captureId) {}

    public function tries(): int
    {
        return (int) config('museumguide.pipeline.tries', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $seconds = (int) config('museumguide.pipeline.backoff_seconds', 20);

        return [$seconds, $seconds * 3];
    }

    public function handle(): void
    {
        $capture = Capture::query()->with(['user', 'visit.museum.city', 'photos', 'artwork.artist', 'artwork.epoch'])->find($this->captureId);

        if ($capture === null || $capture->status === CaptureStatus::Failed) {
            return;
        }

        $this->run($capture);
    }

    abstract protected function run(Capture $capture): void;

    public function failed(?Throwable $exception): void
    {
        $capture = Capture::query()->find($this->captureId);

        if ($capture !== null) {
            Pipeline::fail($capture, $exception?->getMessage() ?? 'Unbekannter Fehler');
        }
    }
}
