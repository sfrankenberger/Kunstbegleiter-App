<?php

namespace App\Services\Pipeline;

use App\Models\Capture;
use App\Models\Place;
use App\Services\Places\Geocoder;
use App\Services\Places\PlaceFinder;
use App\Services\Places\WikiPlaces;
use Illuminate\Support\Str;

/**
 * Ort nach der Fotoerkennung zuordnen (docs/konzept.md Abschnitt 23): zuerst die Wikidata-Treffer im Umkreis des
 * Standorts (Name enthalten), dann die Namenssuche bei Wikidata, sonst ein Ort nur mit Namen.
 */
class PlaceMatcher
{
    public function __construct(private readonly WikiPlaces $wiki, private readonly PlaceFinder $finder, private readonly Geocoder $geocoder) {}

    /**
     * @param  array<string, mixed>  $recognition
     */
    public function attach(Capture $capture, array $recognition): Place
    {
        $name = trim((string) ($recognition['title'] ?? 'Unbekannter Ort'));
        $place = null;

        if ($capture->lat !== null && $capture->lng !== null) {
            $hit = PlaceFinder::match($this->finder->nearby((float) $capture->lat, (float) $capture->lng, (int) config('museumguide.places.poi_radius_m', 400), 60), $name);
            $place = $hit !== null ? $this->finder->place($hit, (float) $capture->lat, (float) $capture->lng) : null;
        }

        if ($place === null) {
            $hit = $this->wiki->search($name);
            $place = $hit !== null ? $this->finder->place($hit, $capture->lat !== null ? (float) $capture->lat : null, $capture->lng !== null ? (float) $capture->lng : null) : Place::query()->whereRaw('lower(name) = ?', [Str::lower($name)])->first();
        }

        if ($place === null) {
            $place = Place::query()->create([
                'name' => $name,
                'kind' => 'other',
                'lat' => $capture->lat,
                'lng' => $capture->lng,
                'architect' => filled($recognition['artist'] ?? null) ? (string) $recognition['artist'] : null,
                'built' => filled($recognition['dating'] ?? null) ? mb_substr((string) $recognition['dating'], 0, 60) : null,
                'city_id' => $capture->lat !== null && $capture->lng !== null ? $this->geocoder->city((float) $capture->lat, (float) $capture->lng)?->getKey() : null,
                'facts' => [],
            ]);
        }

        if ($place->architect === null && filled($recognition['artist'] ?? null)) {
            $place->forceFill(['architect' => (string) $recognition['artist']])->save();
        }

        $capture->forceFill(['place_id' => $place->getKey(), 'confirmed_at' => now()])->save();
        $capture->setRelation('place', $place);

        return $place;
    }

    public static function similar(string $a, string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        return $na === $nb || str_contains($na, $nb) || str_contains($nb, $na) || similar_text($na, $nb) / max(strlen($na), strlen($nb)) > 0.8;
    }

    /**
     * Kleinschreibung, Umlaute als ae/oe/ue/ss, nur Buchstaben und Ziffern.
     */
    private static function normalize(string $text): string
    {
        $text = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $text);

        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($text)));
    }
}
