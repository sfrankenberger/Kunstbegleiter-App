<?php

namespace App\Services\Places;

use App\Contracts\PlacesClient;
use App\Support\Secrets;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Museum in der Naehe ueber die Google Places API (New), "Nearby Search": Typen museum und art_gallery im Umkreis
 * aus config/museumguide.php. Schluessel nur aus der .env (GOOGLE_PLACES_KEY). Tests fangen mit Http::fake() ab.
 */
class GooglePlacesClient implements PlacesClient
{
    public const ENDPOINT = 'https://places.googleapis.com/v1/places:searchNearby';

    /**
     * Sehenswuerdigkeiten, Denkmaeler, Kirchen, Plaetze im Umkreis (Reiter Stadt), gleiche Form wie die Museen.
     *
     * @return list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string, distance_m: int}>
     */
    public function nearbyPois(float $lat, float $lng, int $radiusMeters): array
    {
        return $this->search($lat, $lng, $radiusMeters, ['tourist_attraction', 'historical_landmark', 'monument', 'church', 'cultural_landmark', 'sculpture', 'plaza', 'historical_place'], 20);
    }

    public function nearbyMuseums(float $lat, float $lng, int $radiusMeters): array
    {
        return $this->search($lat, $lng, $radiusMeters, ['museum', 'art_gallery'], 10);
    }

    /**
     * @param  list<string>  $types
     * @return list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string, distance_m: int}>
     */
    private function search(float $lat, float $lng, int $radiusMeters, array $types, int $max): array
    {
        $key = (string) Secrets::get('google_places_key');

        if ($key === '') {
            throw new RuntimeException('Kein Google-Places-Schlüssel: unter Admin > Einstellungen > Zugänge eintragen (oder GOOGLE_PLACES_KEY in der .env).');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.websiteUri',
        ])
            ->timeout(15)
            ->post(self::ENDPOINT, [
                'includedTypes' => $types,
                'maxResultCount' => $max,
                'languageCode' => 'de',
                'rankPreference' => 'DISTANCE',
                'locationRestriction' => ['circle' => ['center' => ['latitude' => $lat, 'longitude' => $lng], 'radius' => $radiusMeters]],
            ])
            ->throw()
            ->json();

        return collect($response['places'] ?? [])
            ->filter(fn (mixed $place): bool => is_array($place) && isset($place['id'], $place['location']))
            ->map(fn (array $place): array => [
                'place_id' => (string) $place['id'],
                'name' => (string) ($place['displayName']['text'] ?? 'Museum'),
                'address' => isset($place['formattedAddress']) ? (string) $place['formattedAddress'] : null,
                'lat' => (float) ($place['location']['latitude'] ?? 0),
                'lng' => (float) ($place['location']['longitude'] ?? 0),
                'website' => isset($place['websiteUri']) ? (string) $place['websiteUri'] : null,
                'distance_m' => FakePlacesClient::distance($lat, $lng, (float) ($place['location']['latitude'] ?? 0), (float) ($place['location']['longitude'] ?? 0)),
            ])
            ->sortBy('distance_m')
            ->values()
            ->all();
    }
}
