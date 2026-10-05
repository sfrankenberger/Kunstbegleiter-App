<?php

namespace App\Services\Pipeline;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\Epoch;
use Illuminate\Support\Str;

/**
 * Abgleich nach der Erkennung (docs/grundgeruest.md, Pipeline Schritt 2): gibt es das Werk schon (gleiches Museum,
 * gleiche Inventarnummer oder gleicher Titel und Kuenstler), auch von Martha? Dann wird es wiederverwendet samt
 * Recherche. Sonst werden Kuenstler (nach Name) und Werk angelegt.
 *
 * @phpstan-type Recognition array{title?: ?string, artist?: ?string, artist_life_dates?: ?string, dating?: ?string, technique?: ?string, dimensions?: ?string, inventory_number?: ?string, epoch?: ?string}
 */
class ArtworkMatcher
{
    /**
     * @param  array<string, mixed>  $recognition
     */
    public function attach(Capture $capture, array $recognition): Artwork
    {
        $museumId = $capture->visit?->museum_id;
        $title = trim((string) ($recognition['title'] ?? 'Unbekanntes Werk'));
        $artist = $this->artist($recognition);
        $inventory = trim((string) ($recognition['inventory_number'] ?? ''));

        $artwork = null;

        if ($museumId !== null && $inventory !== '') {
            $artwork = Artwork::query()->where('museum_id', $museumId)->where('inventory_number', $inventory)->first();
        }

        if ($artwork === null) {
            $artwork = Artwork::query()
                ->whereRaw('lower(title) = ?', [Str::lower($title)])
                ->when($artist, fn ($q) => $q->where('artist_id', $artist->getKey()))
                ->when($museumId !== null, fn ($q) => $q->where('museum_id', $museumId))
                ->first();
        }

        if ($artwork === null) {
            $artwork = Artwork::query()->create([
                'title' => $title,
                'artist_id' => $artist?->getKey(),
                'museum_id' => $museumId,
                'epoch_id' => $this->epoch($recognition['epoch'] ?? null)?->getKey(),
                'dating' => $recognition['dating'] ?? null,
                'technique' => $recognition['technique'] ?? null,
                'dimensions' => $recognition['dimensions'] ?? null,
                'inventory_number' => $inventory !== '' ? $inventory : null,
                'facts' => [],
                'sources' => [],
            ]);
        } else {
            $artwork->fill(array_filter([
                'dating' => $artwork->dating ?: ($recognition['dating'] ?? null),
                'technique' => $artwork->technique ?: ($recognition['technique'] ?? null),
                'dimensions' => $artwork->dimensions ?: ($recognition['dimensions'] ?? null),
                'inventory_number' => $artwork->inventory_number ?: ($inventory !== '' ? $inventory : null),
                'epoch_id' => $artwork->epoch_id ?: $this->epoch($recognition['epoch'] ?? null)?->getKey(),
            ], fn (mixed $v): bool => $v !== null))->save();
        }

        $capture->forceFill(['artwork_id' => $artwork->getKey(), 'confirmed_at' => now()])->save();
        $capture->setRelation('artwork', $artwork);

        return $artwork;
    }

    /**
     * @param  array<string, mixed>  $recognition
     */
    private function artist(array $recognition): ?Artist
    {
        $name = trim((string) ($recognition['artist'] ?? ''));

        if ($name === '' || Str::lower($name) === 'unbekannt') {
            return null;
        }

        $artist = Artist::query()->whereRaw('lower(name) = ?', [Str::lower($name)])->first();

        if ($artist !== null) {
            return $artist;
        }

        [$born, $died] = self::lifeDates((string) ($recognition['artist_life_dates'] ?? ''));

        return Artist::query()->create(['name' => $name, 'born_year' => $born, 'died_year' => $died]);
    }

    private function epoch(?string $name): ?Epoch
    {
        if (blank($name)) {
            return null;
        }

        return Epoch::query()->whereRaw('lower(name) = ?', [Str::lower(trim((string) $name))])->first()
            ?? Epoch::query()->where('name', 'like', '%'.trim((string) $name).'%')->first();
    }

    /**
     * "1862 bis 1918", "1862-1918", "* 1862" in Jahreszahlen.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function lifeDates(string $text): array
    {
        preg_match_all('/\b(1[0-9]{3}|20[0-9]{2})\b/', $text, $m);
        $years = array_map('intval', $m[1]);

        return [$years[0] ?? null, $years[1] ?? null];
    }
}
