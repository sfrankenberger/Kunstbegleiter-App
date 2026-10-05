<?php

namespace App\Services\Places;

use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stadt und Land zu einer Position (docs/konzept.md Abschnitt 24): Nominatim (OpenStreetMap), eine Anfrage je
 * gerundeter Position, 30 Tage im Cache. Ohne Antwort bleibt die Stadt leer (kein "Wien" mehr als Annahme).
 */
class Geocoder
{
    public const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    public function city(float $lat, float $lng): ?City
    {
        $key = 'geocode-'.round($lat, 3).'-'.round($lng, 3);

        $data = Cache::remember($key, now()->addDays(30), function () use ($lat, $lng): array {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)', 'Accept-Language' => 'de'])
                    ->timeout(8)
                    ->get(self::ENDPOINT, ['format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 10]);
                $address = (array) ($response->json('address') ?? []);

                return [
                    'name' => (string) ($address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? $address['county'] ?? ''),
                    'country' => strtoupper((string) ($address['country_code'] ?? '')),
                ];
            } catch (Throwable $e) {
                report($e);

                return ['name' => '', 'country' => ''];
            }
        });

        if ($data['name'] === '') {
            return null;
        }

        return City::query()->firstOrCreate(['name' => $data['name'], 'country_code' => $data['country'] !== '' ? $data['country'] : 'XX']);
    }
}
