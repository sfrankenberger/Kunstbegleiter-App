<?php

namespace App\Services\Places;

use App\Models\Place;
use App\Services\Pipeline\PlaceMatcher;
use App\Support\Secrets;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orte in der Naehe aus mehreren Quellen (docs/konzept.md Abschnitt 24): Wikidata (bekannte Orte mit Bild,
 * Architekt, Baujahr), OpenStreetMap (dichter, weltweit) und Google Places (wenn ein Schluessel da ist). Treffer
 * werden zusammengefuehrt (gleiche Wikidata-ID, sonst aehnlicher Name im Umkreis von 80 Metern), Wikidata-Angaben
 * gewinnen, die Stadt kommt aus der Position (Geocoder).
 */
class PlaceFinder
{
    public function __construct(private readonly WikiPlaces $wiki, private readonly OverpassPlaces $osm, private readonly Geocoder $geocoder) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function nearby(float $lat, float $lng, int $radiusM = 400, int $limit = 30): array
    {
        $hits = [];

        foreach ($this->wiki->nearby($lat, $lng, $radiusM, 60) as $hit) {
            $hits[] = $hit + ['osm_id' => null, 'place_id' => null, 'address' => null, 'source' => 'wikidata'];
        }

        foreach ($this->osm->nearby($lat, $lng, $radiusM, 60) as $hit) {
            $this->merge($hits, $hit + ['place_id' => null, 'source' => 'osm']);
        }

        if ((bool) config('museumguide.places.google_pois', true) && Secrets::get('google_places_key') !== '' && Secrets::get('google_places_key') !== null) {
            try {
                foreach (app(GooglePlacesClient::class)->nearbyPois($lat, $lng, $radiusM) as $hit) {
                    $this->merge($hits, $hit + ['wikidata_id' => null, 'osm_id' => null, 'description' => null, 'kind' => 'other', 'image' => null, 'architect' => null, 'built' => null, 'notable' => 0, 'source' => 'google']);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        usort($hits, fn (array $a, array $b): int => [$b['notable'] ?? 0, $a['distance_m']] <=> [$a['notable'] ?? 0, $b['distance_m']]);

        return array_values(array_slice($hits, 0, $limit));
    }

    /**
     * Ort anlegen oder wiederfinden: Wikidata-ID, sonst OSM-ID, sonst Google-ID, sonst Name in der Naehe.
     *
     * @param  array<string, mixed>  $hit
     */
    public function place(array $hit, ?float $lat = null, ?float $lng = null): Place
    {
        $query = Place::query()->withTrashed();
        $place = null;

        if (filled($hit['wikidata_id'] ?? null)) {
            $place = (clone $query)->firstWhere('wikidata_id', $hit['wikidata_id']);
        }

        if ($place === null && filled($hit['osm_id'] ?? null)) {
            $place = (clone $query)->firstWhere('osm_id', $hit['osm_id']);
        }

        if ($place === null && filled($hit['place_id'] ?? null)) {
            $place = (clone $query)->firstWhere('place_id', $hit['place_id']);
        }

        if ($place !== null) {
            if ($place->trashed()) {
                $place->restore();
            }

            $place->fill(array_filter([
                'wikidata_id' => $place->wikidata_id ?: ($hit['wikidata_id'] ?? null),
                'osm_id' => $place->osm_id ?: ($hit['osm_id'] ?? null),
                'place_id' => $place->place_id ?: ($hit['place_id'] ?? null),
                'image_url' => $place->image_url ?: ($hit['image'] ?? null),
                'architect' => $place->architect ?: ($hit['architect'] ?? null),
                'built' => $place->built ?: ($hit['built'] ?? null),
            ], fn ($v) => $v !== null))->save();

            return $place;
        }

        $plat = $hit['lat'] ?? $lat;
        $plng = $hit['lng'] ?? $lng;
        $city = $plat !== null && $plng !== null ? $this->geocoder->city((float) $plat, (float) $plng) : null;

        return Place::query()->create([
            'city_id' => $city?->getKey(),
            'name' => $hit['name'],
            'kind' => $hit['kind'] ?? 'other',
            'wikidata_id' => $hit['wikidata_id'] ?? null,
            'osm_id' => $hit['osm_id'] ?? null,
            'place_id' => $hit['place_id'] ?? null,
            'lat' => $plat,
            'lng' => $plng,
            'address' => $hit['address'] ?? null,
            'description' => $hit['description'] ?? null,
            'architect' => $hit['architect'] ?? null,
            'built' => $hit['built'] ?? null,
            'image_url' => $hit['image'] ?? null,
            'image_credit' => isset($hit['image']) ? (($hit['source'] ?? '') === 'wikidata' ? 'Wikimedia Commons' : 'OpenStreetMap') : null,
            'facts' => [],
        ]);
    }

    /**
     * Ersten Treffer finden, dessen Name zum erkannten Namen passt (Fotoerkennung).
     *
     * @param  list<array<string, mixed>>  $hits
     * @return array<string, mixed>|null
     */
    public static function match(array $hits, string $name): ?array
    {
        foreach ($hits as $hit) {
            if (PlaceMatcher::similar((string) $hit['name'], $name)) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @param  array<string, mixed>  $hit
     */
    private function merge(array &$hits, array $hit): void
    {
        foreach ($hits as $i => $existing) {
            $sameId = filled($hit['wikidata_id'] ?? null) && ($existing['wikidata_id'] ?? null) === $hit['wikidata_id'];
            $sameName = PlaceMatcher::similar((string) $existing['name'], (string) $hit['name']) && abs($existing['distance_m'] - $hit['distance_m']) < 80;

            if ($sameId || $sameName) {
                foreach (['osm_id', 'place_id', 'address', 'image', 'architect', 'built', 'description'] as $key) {
                    if (blank($existing[$key] ?? null) && filled($hit[$key] ?? null)) {
                        $hits[$i][$key] = $hit[$key];
                    }
                }

                if ($existing['kind'] === 'other' && ($hit['kind'] ?? 'other') !== 'other') {
                    $hits[$i]['kind'] = $hit['kind'];
                }

                return;
            }
        }

        $hits[] = $hit;
    }

    /**
     * Treffer in der Liste nach Schluessel (fuer die Auswahl auf dem Reiter Stadt).
     */
    public static function key(array $hit): string
    {
        return (string) ($hit['wikidata_id'] ?? $hit['osm_id'] ?? $hit['place_id'] ?? Str::slug($hit['name']));
    }
}
