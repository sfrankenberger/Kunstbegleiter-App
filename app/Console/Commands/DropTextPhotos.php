<?php

namespace App\Console\Commands;

use App\Models\Capture;
use App\Models\RoomText;
use App\Services\Captures\CaptureService;
use App\Services\Captures\RoomTextService;
use Illuminate\Console\Command;

/**
 * Bestand aufraeumen: Fotos von Werk- und Raumtexten loeschen, deren Text schon abgelesen ist
 * (`php artisan kunst:drop-text-photos`). Neue Aufnahmen machen das von selbst.
 */
class DropTextPhotos extends Command
{
    protected $signature = 'kunst:drop-text-photos';

    protected $description = 'Fotos von abgelesenen Werk- und Raumtexten loeschen, nur der Text bleibt';

    public function handle(CaptureService $captures, RoomTextService $roomTexts): int
    {
        $dropped = 0;

        Capture::query()->whereNotNull('recognition')->with('photos')->chunkById(100, function ($chunk) use ($captures, &$dropped): void {
            foreach ($chunk as $capture) {
                $dropped += $captures->dropTextPhotos($capture);
            }
        });

        $texts = 0;

        RoomText::query()->whereNotNull('text')->where('path', '!=', '')->chunkById(100, function ($chunk) use ($roomTexts, &$texts): void {
            foreach ($chunk as $roomText) {
                $roomTexts->dropPhoto($roomText);
                $texts++;
            }
        });

        $this->info($dropped.' Textfotos von Aufnahmen und '.$texts.' Raumtext-Fotos gelöscht.');

        return self::SUCCESS;
    }
}
