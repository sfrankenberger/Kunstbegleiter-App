<?php

namespace App\Services\Pipeline;

use App\Models\Artwork;

/**
 * Musikbett je Epoche (docs/konzept.md Abschnitt 16): Dateien unter storage/app/music/{epoche-slug}.mp3,
 * Zuordnung verwandter Epochen in config museumguide.music.epochs, sonst default.mp3. Keine Datei, keine Musik.
 */
class MusicBed
{
    public function pick(?Artwork $artwork): ?string
    {
        if (! (bool) config('museumguide.music.enabled', true)) {
            return null;
        }

        $dir = rtrim((string) config('museumguide.music.dir', storage_path('app/music')), '/');
        $slug = $artwork?->epoch?->slug;
        $candidates = [];

        if ($slug !== null) {
            $candidates[] = $slug;
            $mapped = config('museumguide.music.epochs.'.$slug);

            if (is_string($mapped)) {
                $candidates[] = $mapped;
            }
        }

        $candidates[] = 'default';

        foreach ($candidates as $name) {
            if (is_file($dir.'/'.$name.'.mp3')) {
                return $dir.'/'.$name.'.mp3';
            }
        }

        return null;
    }
}
