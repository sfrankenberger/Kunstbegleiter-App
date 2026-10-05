<?php

namespace App\Services\Pipeline;

use App\Models\Artwork;
use Illuminate\Support\Facades\Cache;

/**
 * Musikbett je Epoche (docs/konzept.md Abschnitt 16): Ordner storage/app/music/{epoche-slug}/ mit 10 bis 15
 * Stuecken, daraus wird zufaellig gewaehlt (nie dasselbe wie beim vorigen Guide dieser Epoche). Zuordnung
 * verwandter Epochen in config museumguide.music.epochs, Rueckfall Ordner default/. Einzeldateien
 * {slug}.mp3 gehen weiter. Keine Datei, keine Musik.
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
            $tracks = glob($dir.'/'.$name.'/*.mp3') ?: [];

            if ($tracks !== []) {
                return $this->rotate($name, $tracks);
            }

            if (is_file($dir.'/'.$name.'.mp3')) {
                return $dir.'/'.$name.'.mp3';
            }
        }

        return null;
    }

    /**
     * Zufaellig, aber nicht das zuletzt gespielte Stueck dieser Gruppe.
     *
     * @param  list<string>  $tracks
     */
    private function rotate(string $group, array $tracks): string
    {
        sort($tracks);
        $key = 'music-last-'.$group;
        $last = Cache::get($key);
        $pool = count($tracks) > 1 ? array_values(array_filter($tracks, fn (string $t): bool => $t !== $last)) : $tracks;
        $choice = $pool[random_int(0, count($pool) - 1)];
        Cache::put($key, $choice, now()->addDays(30));

        return $choice;
    }
}
