<?php

namespace App\Services\Captures;

use App\Enums\CaptureStatus;
use App\Enums\GuideMode;
use App\Enums\PhotoType;
use App\Models\Capture;
use App\Models\CapturePhoto;
use App\Models\Place;
use App\Models\RoomText;
use App\Models\User;
use App\Models\Visit;
use App\Services\Pipeline\Pipeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Aufnahme anlegen (docs/grundgeruest.md, "Aufnahme"): 1 bis 3 Fotos auf die private Platte, Capture mit Status
 * "hochgeladen", Fototypen vorbelegen (erstes Foto Werk, weitere Werktext), Besuch um 30 Minuten verlaengern.
 * Danach startet die Pipeline synchron (schnell: ein Aufruf, ausfuehrlich: Erkennung, Rest ueber die Queue).
 * Jeder Fehler (Monatslimit, API) bleibt als Meldung an der Aufnahme stehen, "Erneut versuchen" holt nach.
 */
class CaptureService
{
    public const DISK = 'local';

    /**
     * @param  list<UploadedFile>  $files
     */
    public function create(User $user, Visit $visit, array $files, ?GuideMode $mode = null, ?RoomText $roomText = null): Capture
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
            'room_text_id' => $roomText !== null && (int) $roomText->user_id === (int) $user->getKey() ? $roomText->getKey() : null,
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
     * Ort aus der Liste (Reiter Stadt): Aufnahme ohne Fotos und ohne Besuch, Pipeline startet gleich.
     */
    public function createForPlace(User $user, Place $place, ?GuideMode $mode = null, ?float $lat = null, ?float $lng = null): Capture
    {
        $capture = Capture::query()->create([
            'user_id' => $user->getKey(),
            'place_id' => $place->getKey(),
            'status' => CaptureStatus::Uploaded,
            'length' => $user->preferred_length?->value ?? 'normal',
            'mode' => $mode ?? $user->default_mode ?? GuideMode::Quick,
            'lat' => $lat ?? $place->lat,
            'lng' => $lng ?? $place->lng,
        ]);

        $this->startPipeline($capture);

        return $capture;
    }

    /**
     * Foto in der Stadt (Reiter Stadt): Aufnahme ohne Besuch, Erkennung ordnet den Ort zu (PlaceMatcher).
     *
     * @param  list<UploadedFile>  $files
     */
    public function createForPlacePhoto(User $user, array $files, ?float $lat, ?float $lng, ?GuideMode $mode = null): Capture
    {
        $max = (int) config('museumguide.photos.max_per_capture', 3);

        if ($files === [] || count($files) > $max) {
            throw new InvalidArgumentException('Bitte 1 bis '.$max.' Fotos auswählen.');
        }

        $capture = Capture::query()->create([
            'user_id' => $user->getKey(),
            'status' => CaptureStatus::Uploaded,
            'length' => $user->preferred_length?->value ?? 'normal',
            'mode' => $mode ?? $user->default_mode ?? GuideMode::Quick,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        $this->storePhotos($capture, $files);
        $this->startPipeline($capture);

        return $capture;
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storePhotos(Capture $capture, array $files): void
    {
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
    }

    /**
     * Pipeline anstossen. Fehler werden an der Aufnahme festgehalten, nicht geworfen.
     */
    public function startPipeline(Capture $capture): void
    {
        try {
            app(Pipeline::class)->start($capture);
        } catch (Throwable $e) {
            report($e);
            Pipeline::fail($capture->fresh() ?? $capture, $e->getMessage());
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

    /**
     * Fotos von Werk- und Raumtexten nach dem Ablesen loeschen, der Text wandert nach captures.label_text
     * (Sebastian, 07.10.2026). Werkfotos bleiben, ohne Werkfoto das erste. Liefert die Zahl der geloeschten Fotos.
     */
    public function dropTextPhotos(Capture $capture): int
    {
        $dropped = 0;
        $texts = [];

        $seen = [];
        $hasArtwork = $capture->photos->contains(fn ($p) => $p->type === PhotoType::Artwork);

        foreach ($capture->photos->sortBy('sort_order')->values() as $index => $photo) {
            // Dasselbe Foto zweimal (Bestand vor dem 07.10.2026): nur einmal behalten
            $hash = Storage::disk(self::DISK)->exists($photo->path) ? md5_file(Storage::disk(self::DISK)->path($photo->path)) : null;
            $duplicate = $hash !== null && in_array($hash, $seen, true);
            $seen[] = $hash;

            // Werkfotos bleiben; das erste Foto nur, wenn es gar kein Werkfoto gibt (Raumtext zuerst fotografiert)
            if (! $duplicate && ($photo->type === PhotoType::Artwork || (! $hasArtwork && $index === 0))) {
                continue;
            }

            if (filled($photo->ocr_text)) {
                $texts[] = trim((string) $photo->ocr_text);
            }

            Storage::disk(self::DISK)->delete($photo->path);
            $photo->forceDelete();
            $dropped++;
        }

        if ($texts !== []) {
            $capture->forceFill(['label_text' => trim(implode("\n\n", array_filter([(string) $capture->label_text, ...$texts])))])->save();
        }

        if ($dropped > 0) {
            $capture->unsetRelation('photos');
            $capture->load('photos');
        }

        return $dropped;
    }

    public function photoPath(CapturePhoto $photo): string
    {
        return Storage::disk(self::DISK)->path($photo->path);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    public function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return is_array($size) ? [(int) $size[0], (int) $size[1]] : [null, null];
    }
}
