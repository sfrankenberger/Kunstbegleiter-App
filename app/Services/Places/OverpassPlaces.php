<?php

namespace App\Services\Places;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Orte aus OpenStreetMap (Overpass API, docs/konzept.md Abschnitt 24): Kunstwerke im oeffentlichen Raum, Denkmaeler,
 * historische Bauten, Kirchen, Brunnen, Plaetze, Bruecken, Parks mit Namen im Umkreis. Kostenlos, kein Schluessel,
 * weltweit dichter als Wikidata. Liefert dieselbe Form wie WikiPlaces::nearby, plus osm_id; traegt ein Element
 * eine Wikidata-ID, wird sie mitgegeben (Zusammenfuehren in PlaceFinder).
 */
class OverpassPlaces
{
    public const ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /**
     * @return list<array<string, mixed>>
     */
    public function nearby(float $lat, float $lng, int $radiusM = 300, int $limit = 40): array
    {
        $around = "(around:{$radiusM},{$lat},{$lng})";
        $query = <<<QL
[out:json][timeout:10];
(
  nwr["name"]["tourism"~"^(artwork|attraction|viewpoint)$"]{$around};
  nwr["name"]["historic"]{$around};
  nwr["name"]["amenity"~"^(place_of_worship|fountain|theatre|townhall)$"]{$around};
  nwr["name"]["building"~"^(church|cathedral|chapel|palace|castle|synagogue|mosque|temple|government|public)$"]{$around};
  nwr["name"]["man_made"~"^(bridge|tower|obelisk|monument)$"]{$around};
  nwr["name"]["leisure"~"^(park|garden)$"]{$around};
  nwr["name"]["place"="square"]{$around};
  nwr["name"]["heritage"]{$around};
);
out center tags 120;
QL;

        try {
            $response = Http::withHeaders(['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)'])
                ->timeout((int) config('museumguide.places.overpass_timeout', 12))
                ->asForm()
                ->post(self::ENDPOINT, ['data' => $query]);
            $elements = $response->successful() ? (array) ($response->json('elements') ?? []) : [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $places = [];

        foreach ($elements as $el) {
            $tags = (array) ($el['tags'] ?? []);
            $name = (string) ($tags['name:de'] ?? $tags['name'] ?? '');
            $plat = (float) ($el['lat'] ?? $el['center']['lat'] ?? 0);
            $plng = (float) ($el['lon'] ?? $el['center']['lon'] ?? 0);

            if ($name === '' || $plat === 0.0 || isset($tags['highway']) || ($tags['historic'] ?? '') === 'memorial' && ($tags['memorial'] ?? '') === 'plaque') {
                continue;
            }

            $osmId = ($el['type'] ?? 'node').'/'.($el['id'] ?? '');
            $image = isset($tags['image']) && str_starts_with((string) $tags['image'], 'http') ? (string) $tags['image'] : (isset($tags['wikimedia_commons']) && str_starts_with((string) $tags['wikimedia_commons'], 'File:') ? WikiPlaces::thumb(substr((string) $tags['wikimedia_commons'], 5)) : null);

            $places[$osmId] = [
                'wikidata_id' => isset($tags['wikidata']) && preg_match('/^Q\d+$/', (string) $tags['wikidata']) === 1 ? (string) $tags['wikidata'] : null,
                'osm_id' => $osmId,
                'name' => $name,
                'description' => self::describe($tags),
                'kind' => self::kind($tags),
                'lat' => $plat,
                'lng' => $plng,
                'image' => $image,
                'architect' => isset($tags['architect']) ? (string) $tags['architect'] : (isset($tags['artist_name']) ? (string) $tags['artist_name'] : null),
                'built' => isset($tags['start_date']) ? substr((string) $tags['start_date'], 0, 4) : (isset($tags['year_of_construction']) ? substr((string) $tags['year_of_construction'], 0, 4) : null),
                'address' => isset($tags['addr:street']) ? trim(($tags['addr:street'] ?? '').' '.($tags['addr:housenumber'] ?? '')) : null,
                'distance_m' => (int) round(WikiPlaces::distance($lat, $lng, $plat, $plng)),
                'notable' => isset($tags['wikipedia']) || isset($tags['wikidata']) ? 1 : 0,
            ];
        }

        usort($places, fn (array $a, array $b): int => [$b['notable'], $a['distance_m']] <=> [$a['notable'], $b['distance_m']]);

        return array_values(array_slice($places, 0, $limit));
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    public static function kind(array $tags): string
    {
        $historic = (string) ($tags['historic'] ?? '');
        $building = (string) ($tags['building'] ?? '');

        return match (true) {
            ($tags['tourism'] ?? '') === 'artwork' && in_array((string) ($tags['artwork_type'] ?? ''), ['statue', 'sculpture', 'bust'], true) => 'statue',
            ($tags['tourism'] ?? '') === 'artwork' => 'statue',
            ($tags['amenity'] ?? '') === 'fountain' => 'fountain',
            in_array($historic, ['memorial', 'monument', 'wayside_cross', 'wayside_shrine'], true), ($tags['man_made'] ?? '') === 'obelisk' => 'monument',
            ($tags['amenity'] ?? '') === 'place_of_worship', in_array($building, ['church', 'cathedral', 'chapel', 'synagogue', 'mosque', 'temple'], true), $historic === 'church' => 'church',
            ($tags['place'] ?? '') === 'square' => 'square',
            ($tags['man_made'] ?? '') === 'bridge', isset($tags['bridge']) => 'bridge',
            in_array((string) ($tags['leisure'] ?? ''), ['park', 'garden'], true) => 'park',
            $building !== '' && $building !== 'no', in_array($historic, ['building', 'castle', 'palace', 'city_gate', 'tower', 'ruins', 'manor'], true), isset($tags['heritage']) => 'building',
            default => 'other',
        };
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private static function describe(array $tags): ?string
    {
        $parts = array_filter([
            $tags['description:de'] ?? $tags['description'] ?? null,
            isset($tags['historic']) ? 'historic: '.$tags['historic'] : null,
            isset($tags['artwork_type']) ? 'artwork: '.$tags['artwork_type'] : null,
            isset($tags['heritage:operator']) ? 'Denkmalschutz: '.$tags['heritage:operator'] : null,
        ]);

        return $parts === [] ? null : mb_substr(implode(' · ', $parts), 0, 300);
    }
}
