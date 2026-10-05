<?php

namespace App\Services\Visits;

use App\Contracts\PlacesClient;
use App\Models\City;
use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Str;

/**
 * Besuch (docs/grundgeruest.md, "Beim Oeffnen der App"): Standort nehmen, Museen in der Naehe suchen, Besuch
 * anlegen (30 Minuten, jede Aufnahme verlaengert), Museum von Hand setzen ("Ich bin im ..."), Besuch beenden.
 * Ein Nutzer hat hoechstens einen aktiven Besuch; ein neuer beendet den alten.
 */
class VisitService
{
    public function __construct(private readonly PlacesClient $places) {}

    /**
     * Museen in der Naehe des Standorts, nahestes zuerst.
     *
     * @return list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string, distance_m: int}>
     */
    public function nearbyMuseums(float $lat, float $lng): array
    {
        return $this->places->nearbyMuseums($lat, $lng, (int) config('museumguide.places.radius_m', 300));
    }

    /**
     * Besuch am Standort anlegen, mit oder ohne Museum. Ein laufender Besuch wird beendet.
     */
    public function start(User $user, ?float $lat, ?float $lng, ?int $accuracy, ?Museum $museum = null): Visit
    {
        $this->end($user);

        return $user->visits()->create([
            'museum_id' => $museum?->getKey(),
            'city_id' => $museum?->city_id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracy,
            'started_at' => now(),
            'valid_until' => now()->addMinutes((int) config('museumguide.visit.minutes', 30)),
        ]);
    }

    /**
     * Museum aus einem Places-Treffer holen oder anlegen (Schluessel place_id), Stadt aus der Adresse.
     *
     * @param  array{place_id: string, name: string, address?: ?string, lat?: float, lng?: float, website?: ?string}  $place
     */
    public function museumFromPlace(array $place): Museum
    {
        $museum = Museum::query()->withTrashed()->firstWhere('place_id', $place['place_id']);

        if ($museum !== null) {
            if ($museum->trashed()) {
                $museum->restore();
            }

            return $museum;
        }

        return Museum::query()->create([
            'place_id' => $place['place_id'],
            'name' => $place['name'],
            'address' => $place['address'] ?? null,
            'lat' => $place['lat'] ?? null,
            'lng' => $place['lng'] ?? null,
            'website' => $place['website'] ?? null,
            'city_id' => $this->cityFromAddress($place['address'] ?? null)?->getKey(),
        ]);
    }

    /**
     * Museum von Hand ("Ich bin im ..."): bestehendes mit gleichem Namen (ohne Gross/Klein) oder neu, ohne place_id.
     */
    public function museumByName(string $name, ?string $cityName = null): Museum
    {
        $name = trim($name);
        $museum = Museum::query()->whereRaw('lower(name) = ?', [Str::lower($name)])->first();

        if ($museum !== null) {
            return $museum;
        }

        return Museum::query()->create([
            'name' => $name,
            'city_id' => filled($cityName) ? City::query()->firstOrCreate(['name' => trim((string) $cityName), 'country_code' => 'AT'])->getKey() : null,
        ]);
    }

    /**
     * Museum eines laufenden Besuchs setzen oder wechseln.
     */
    public function setMuseum(Visit $visit, Museum $museum): Visit
    {
        $visit->forceFill(['museum_id' => $museum->getKey(), 'city_id' => $museum->city_id ?? $visit->city_id])->save();

        return $visit;
    }

    /**
     * Laufenden Besuch beenden (Gueltigkeit auf jetzt setzen). Nichts passiert ohne aktiven Besuch.
     */
    public function end(User $user): void
    {
        $user->visits()->active()->get()->each(fn (Visit $visit) => $visit->forceFill(['valid_until' => now()])->save());
    }

    /**
     * Stadt aus einer Adresse wie "Maria-Theresien-Platz, 1010 Wien" oder "..., 1010 Wien, Österreich".
     */
    public function cityFromAddress(?string $address): ?City
    {
        if ($address === null || preg_match('/\b\d{4,5}\s+([^\d,]+?)\s*(?:,|$)/u', $address, $m) !== 1) {
            return null;
        }

        $name = trim($m[1]);

        if ($name === '') {
            return null;
        }

        $country = preg_match('/(Deutschland|Germany)\s*$/u', $address) === 1 ? 'DE' : (preg_match('/(Schweiz|Switzerland)\s*$/u', $address) === 1 ? 'CH' : 'AT');

        return City::query()->firstOrCreate(['name' => $name, 'country_code' => $country]);
    }
}
