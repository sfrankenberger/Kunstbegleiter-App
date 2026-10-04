<?php

namespace App\Services\Places;

use App\Contracts\PlacesClient;

/**
 * Fake fuer Tests und die Bauphase: liefert feste Wiener Museen, keine Netzaufrufe.
 */
class FakePlacesClient implements PlacesClient
{
    /** @var list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string}> */
    public const MUSEUMS = [
        ['place_id' => 'fake-khm', 'name' => 'Kunsthistorisches Museum Wien', 'address' => 'Maria-Theresien-Platz, 1010 Wien', 'lat' => 48.2037, 'lng' => 16.3616, 'website' => 'https://www.khm.at'],
        ['place_id' => 'fake-belvedere', 'name' => 'Oberes Belvedere', 'address' => 'Prinz-Eugen-Straße 27, 1030 Wien', 'lat' => 48.1914, 'lng' => 16.3808, 'website' => 'https://www.belvedere.at'],
        ['place_id' => 'fake-leopold', 'name' => 'Leopold Museum', 'address' => 'Museumsplatz 1, 1070 Wien', 'lat' => 48.2027, 'lng' => 16.3586, 'website' => 'https://www.leopoldmuseum.org'],
        ['place_id' => 'fake-albertina', 'name' => 'Albertina', 'address' => 'Albertinaplatz 1, 1010 Wien', 'lat' => 48.2044, 'lng' => 16.3683, 'website' => 'https://www.albertina.at'],
    ];

    public function nearbyMuseums(float $lat, float $lng, int $radiusMeters): array
    {
        return collect(self::MUSEUMS)
            ->map(fn (array $museum): array => $museum + ['distance_m' => self::distance($lat, $lng, $museum['lat'], $museum['lng'])])
            ->filter(fn (array $museum): bool => $museum['distance_m'] <= $radiusMeters)
            ->sortBy('distance_m')
            ->values()
            ->all();
    }

    /**
     * Entfernung in Metern (Haversine).
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
