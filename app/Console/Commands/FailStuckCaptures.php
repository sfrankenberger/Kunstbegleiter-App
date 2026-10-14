<?php

namespace App\Console\Commands;

use App\Enums\CaptureStatus;
use App\Models\Capture;
use App\Services\Pipeline\Pipeline;
use Illuminate\Console\Command;

/**
 * Haengende Aufnahmen beenden (docs/konzept.md Abschnitt 25): laeuft eine Aufnahme laenger als
 * `museumguide.pipeline.stuck_minutes` ohne Fortschritt, wird sie auf Fehler gesetzt, damit die Seite
 * "Erneut versuchen" zeigt statt ewig zu laden. Laeuft im Scheduler alle fuenf Minuten.
 */
class FailStuckCaptures extends Command
{
    protected $signature = 'kunst:stuck-captures';

    protected $description = 'Aufnahmen ohne Fortschritt auf Fehler setzen';

    public function handle(): int
    {
        $minutes = (int) config('museumguide.pipeline.stuck_minutes', 10);
        $count = 0;

        Capture::query()
            ->whereNotIn('status', [CaptureStatus::Done, CaptureStatus::Failed])
            ->where('needs_confirmation', false)
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->each(function (Capture $capture) use (&$count): void {
                Pipeline::fail($capture, 'Hat zu lange gedauert und wurde abgebrochen. Bitte erneut versuchen.');
                $count++;
            });

        $this->info($count.' hängende Aufnahmen beendet.');

        return self::SUCCESS;
    }
}
