<?php

namespace App\Contracts;

/**
 * Museum in der Naehe finden (Google Places oder ein Fake fuer Tests). Etappe 2 bringt den Google-Treiber.
 */
interface PlacesClient
{
    /**
     * Museen in der Naehe, nahestes zuerst.
     *
     * @return list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string, distance_m: int}>
     */
    public function nearbyMuseums(float $lat, float $lng, int $radiusMeters): array;
}
