<?php

namespace App\Services\Captures;

use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PhotoType;
use App\Models\Capture;
use App\Models\CapturePhoto;
use App\Models\User;
use App\Models\Visit;
use App\Services\Pipeline\Pipeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Aufnahme anlegen (docs/grundgeruest.md, "Aufnahme"): 1 bis 3 Fotos auf die private Platte, Capture mit Status
 * "hochgeladen", Fototypen vorbelegen (erstes Foto Werk, weitere Werktext), Besuch um 30 Minuten verlaengern.
 * Danach startet die Pipeline (Erkennung, Recherche, Skript, Audio); ist das Monatslimit erreicht, bleibt die
 * Aufnahme mit der Meldung stehen und kann spaeter mit "Erneut versuchen" nachgeholt werden.
 */
class CaptureService
{
    public const DISK = 'local';

    /**
     * @param  list<UploadedFile>  $files
     */
    public function create(User $user, Visit $visit, array $files, ?GuideMode $mode = null): Capture
    {
        $max = (int) config('museumguide.photos.max_per_capture', 3);

        if ($files === [] || count($files) > $max) {
            throw new InvalidArgumentException('Bitte 1 bis '.$max.' Fotos auswählen.');
        }

        $capture = $visit->captures()->create([
            'user_id' => $user->getKey(),
            'status' => CaptureStatus::Uploaded,
            'length' => $user->preferred_length?->value ?? 'normal',
            'mode' => $mode ?? $user->default_mode ?? GuideMode::Quick,
        ]);

        foreach (array_values($files) as $index => $file) {
            $path = $file->storeAs('captures/'.$capture->getKey(), ($index + 1).'.'.($file->guessExtension() ?: 'jpg'), self::DISK);
            [$width, $height] = $this->dimensions($file);

            $capture->photos()->create([
                'path' => (string) $path,
                'type' => $index === 0 ? PhotoType::Artwork : PhotoType::Label,
                'width' => $width,
                'height' => $height,
                'sort_order' => $index,
            ]);
        }

        $visit->extend();
        $this->startPipeline($capture);

        return $capture;
    }

    /**
     * Pipeline anstossen. Das Monatslimit wird als Fehler an der Aufnahme festgehalten, nicht geworfen.
     */
    public function startPipeline(Capture $capture): void
    {
        try {
            app(Pipeline::class)->start($capture);
        } catch (RuntimeException $e) {
            Pipeline::fail($capture, $e->getMessage());
        }
    }

    /**
     * Aufnahme samt Dateien endgueltig entfernen (Papierkorb bleibt fuer "delete()" am Model).
     */
    public function purge(Capture $capture): void
    {
        Storage::disk(self::DISK)->deleteDirectory('captures/'.$capture->getKey());
        $capture->forceDelete();
    }

    public function photoPath(CapturePhoto $photo): string
    {
        return Storage::disk(self::DISK)->path($photo->path);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return is_array($size) ? [(int) $size[0], (int) $size[1]] : [null, null];
    }
}
