<?php

namespace App\Services\Places;

use App\Models\City;
use App\Models\Place;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Orte aus Wikidata (docs/konzept.md Abschnitt 23): Umkreissuche (SPARQL wikibase:around) nach Statuen, Gebaeuden,
 * Plaetzen, Kirchen, Denkmaelern mit Bild, Architekt und Baujahr; Namenssuche; Anlegen als Place (Schluessel
 * Wikidata-ID). Kostenlos, kein Schluessel, User-Agent Pflicht. Alle Fehler enden still in einer leeren Liste.
 */
class WikiPlaces
{
    public const SPARQL = 'https://query.wikidata.org/sparql';

    /** Wikidata-Klassen, die hier nicht gemeint sind (Strassen, Haltestellen, Verwaltung). */
    private const EXCLUDE = [
        'Q79007', 'Q34442', 'Q548662', 'Q953806', 'Q2175765', 'Q55488', 'Q174782', 'Q4830453', 'Q3957', 'Q515', 'Q5', 'Q15284', 'Q486972', 'Q123705',
        // Ereignisse, Organisationen, Ausstellungen, Bezirke
        'Q1190554', 'Q2223653', 'Q645883', 'Q175331', 'Q3839081', 'Q13418847', 'Q1656682', 'Q43229', 'Q31855', 'Q2385804', 'Q464980', 'Q3918', 'Q6881511', 'Q11691', 'Q1346164', 'Q13220204', 'Q252846', 'Q16917', 'Q3914', 'Q7278',
    ];

    /** Zuordnung grober Klassen zu Place::KINDS (erste passende gewinnt). */
    private const KINDS = [
        'statue' => ['Q179700', 'Q860861', 'Q1392213'],
        'fountain' => ['Q483453'],
        'monument' => ['Q4989906', 'Q5003624', 'Q575759', 'Q1076486', 'Q721747', 'Q2091545', 'Q4989906'],
        'church' => ['Q16970', 'Q1088552', 'Q2977', 'Q120560', 'Q108325', 'Q44613', 'Q34627', 'Q32815'],
        'square' => ['Q174782', 'Q22698'],
        'bridge' => ['Q12280'],
        'park' => ['Q22698', 'Q1107656', 'Q22652'],
        'building' => ['Q41176', 'Q16560', 'Q1021645', 'Q11755880', 'Q23413', 'Q27686', 'Q1497364', 'Q24354', 'Q33506', 'Q7075', 'Q57659484', 'Q16560'],
    ];

    /**
     * Orte im Umkreis (Meter), nahester zuerst.
     *
     * @return list<array{wikidata_id: string, name: string, description: ?string, kind: string, lat: float, lng: float, image: ?string, architect: ?string, built: ?string, distance_m: int}>
     */
    public function nearby(float $lat, float $lng, int $radiusM = 300, int $limit = 25): array
    {
        return $this->parse($this->run($this->query($lat, $lng, $radiusM)), $lat, $lng, $limit);
    }

    /**
     * SPARQL fuer den Umkreis (auch fuer PlaceFinder, der Wikidata und OpenStreetMap parallel abfragt).
     */
    public function query(float $lat, float $lng, int $radiusM): string
    {
        $km = max(0.05, $radiusM / 1000);

        return <<<SPARQL
SELECT ?item ?itemLabel ?itemDescription ?coord ?image ?architectLabel ?inception ?dewiki ?enwiki ?collection (GROUP_CONCAT(DISTINCT ?class; separator=",") AS ?classes) ?sitelinks WHERE {
  SERVICE wikibase:around { ?item wdt:P625 ?coord . bd:serviceParam wikibase:center "Point({$lng} {$lat})"^^geo:wktLiteral ; wikibase:radius "{$km}" . }
  ?item wikibase:sitelinks ?sitelinks . FILTER(?sitelinks > 0)
  OPTIONAL { ?item wdt:P31 ?class . }
  OPTIONAL { ?item wdt:P18 ?image . }
  OPTIONAL { ?item wdt:P84 ?architect . }
  OPTIONAL { ?item wdt:P571 ?inception . }
  OPTIONAL { ?item wdt:P195 ?collection . }
  OPTIONAL { ?dewiki schema:about ?item ; schema:isPartOf <https://de.wikipedia.org/> . }
  OPTIONAL { ?enwiki schema:about ?item ; schema:isPartOf <https://en.wikipedia.org/> . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "de,en". }
} GROUP BY ?item ?itemLabel ?itemDescription ?coord ?image ?architectLabel ?inception ?dewiki ?enwiki ?collection ?sitelinks ORDER BY DESC(?sitelinks) LIMIT 200
SPARQL;
    }

    /**
     * Zeilen der SPARQL-Antwort in Treffer verwandeln: Strassen, Ereignisse, Organisationen und Museumsstuecke
     * (Eigenschaft P195 Sammlung) fallen weg.
     *
     * @param  list<array<string, array{value: string}>>  $rows
     * @return list<array<string, mixed>>
     */
    public function parse(array $rows, float $lat, float $lng, int $limit): array
    {
        $places = [];

        foreach ($rows as $row) {
            $classes = array_values(array_filter(array_map(fn (string $c): string => basename($c), explode(',', (string) ($row['classes']['value'] ?? '')))));

            if (filled($row['collection']['value'] ?? null)) {
                continue;
            }

            if (array_intersect($classes, self::EXCLUDE) !== [] && array_intersect($classes, array_merge(...array_values(self::KINDS))) === []) {
                continue;
            }

            if (preg_match('/Point\(([-0-9.]+) ([-0-9.]+)\)/', (string) ($row['coord']['value'] ?? ''), $m) !== 1) {
                continue;
            }

            $id = basename((string) $row['item']['value']);
            $name = (string) ($row['itemLabel']['value'] ?? '');

            if ($name === '' || $name === $id) {
                continue;
            }

            $plat = (float) $m[2];
            $plng = (float) $m[1];
            $places[$id] = [
                'wikidata_id' => $id,
                'name' => $name,
                'description' => filled($row['itemDescription']['value'] ?? null) ? mb_substr((string) $row['itemDescription']['value'], 0, 300) : null,
                'kind' => $this->kind($classes),
                'lat' => $plat,
                'lng' => $plng,
                'image' => filled($row['image']['value'] ?? null) ? self::thumb((string) $row['image']['value']) : null,
                'architect' => filled($row['architectLabel']['value'] ?? null) ? (string) $row['architectLabel']['value'] : null,
                'built' => filled($row['inception']['value'] ?? null) ? substr((string) $row['inception']['value'], 0, 4) : null,
                'distance_m' => (int) round(self::distance($lat, $lng, $plat, $plng)),
                // Bekannte Orte zuerst: Stufe 2 = Artikel in mindestens drei Sprachen, Stufe 1 = deutscher oder
                // englischer Artikel, Stufe 0 = nur Denkmalliste oder Commons (Gedenktafeln, Wohnhaeuser)
                'notable' => (int) ($row['sitelinks']['value'] ?? 0) >= 3 ? 2 : (filled($row['dewiki']['value'] ?? null) || filled($row['enwiki']['value'] ?? null) ? 1 : 0),
            ];
        }

        usort($places, fn (array $a, array $b): int => [$b['notable'], $a['distance_m']] <=> [$a['notable'], $b['distance_m']]);

        return array_values(array_slice($places, 0, $limit));
    }

    /**
     * Namenssuche (wbsearchentities, Deutsch, dann Englisch), mit Koordinaten und Bild des Treffers.
     *
     * @return array{wikidata_id: string, name: string, description: ?string, kind: string, lat: ?float, lng: ?float, image: ?string, architect: ?string, built: ?string}|null
     */
    public function search(string $name): ?array
    {
        try {
            foreach (['de', 'en'] as $language) {
                $response = Http::withHeaders(['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)'])
                    ->timeout((int) config('museumguide.places.wikidata_timeout', 12))
                    ->get('https://www.wikidata.org/w/api.php', ['action' => 'wbsearchentities', 'search' => $name, 'language' => $language, 'uselang' => 'de', 'type' => 'item', 'limit' => 5, 'format' => 'json']);

                foreach ((array) $response->json('search') as $hit) {
                    $id = (string) ($hit['id'] ?? '');
                    $details = $this->details($id);

                    if ($details === null || $details['lat'] === null) {
                        continue;
                    }

                    return ['wikidata_id' => $id, 'name' => (string) ($hit['label'] ?? $name), 'description' => filled($hit['description'] ?? null) ? mb_substr((string) $hit['description'], 0, 300) : null] + $details;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * Koordinaten, Bild, Architekt, Baujahr und Klassen eines Eintrags.
     *
     * @return array{kind: string, lat: ?float, lng: ?float, image: ?string, architect: ?string, built: ?string}|null
     */
    private function details(string $id): ?array
    {
        $rows = $this->run(<<<SPARQL
SELECT ?coord ?image ?architectLabel ?inception (GROUP_CONCAT(DISTINCT ?class; separator=",") AS ?classes) WHERE {
  OPTIONAL { wd:{$id} wdt:P625 ?coord . } OPTIONAL { wd:{$id} wdt:P31 ?class . } OPTIONAL { wd:{$id} wdt:P18 ?image . }
  OPTIONAL { wd:{$id} wdt:P84 ?architect . } OPTIONAL { wd:{$id} wdt:P571 ?inception . }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "de,en". }
} GROUP BY ?coord ?image ?architectLabel ?inception LIMIT 1
SPARQL);
        $row = $rows[0] ?? null;

        if ($row === null) {
            return null;
        }

        $lat = $lng = null;

        if (preg_match('/Point\(([-0-9.]+) ([-0-9.]+)\)/', (string) ($row['coord']['value'] ?? ''), $m) === 1) {
            $lat = (float) $m[2];
            $lng = (float) $m[1];
        }

        $classes = array_values(array_filter(array_map(fn (string $c): string => basename($c), explode(',', (string) ($row['classes']['value'] ?? '')))));

        return [
            'kind' => $this->kind($classes),
            'lat' => $lat,
            'lng' => $lng,
            'image' => filled($row['image']['value'] ?? null) ? self::thumb((string) $row['image']['value']) : null,
            'architect' => filled($row['architectLabel']['value'] ?? null) ? (string) $row['architectLabel']['value'] : null,
            'built' => filled($row['inception']['value'] ?? null) ? substr((string) $row['inception']['value'], 0, 4) : null,
        ];
    }

    /**
     * Ort anlegen oder wiederfinden (Schluessel Wikidata-ID), Stadt aus dem naechsten bekannten Treffer.
     *
     * @param  array{wikidata_id: string, name: string, description?: ?string, kind?: string, lat?: ?float, lng?: ?float, image?: ?string, architect?: ?string, built?: ?string}  $hit
     */
    public function placeFromHit(array $hit, ?City $city = null): Place
    {
        $place = Place::query()->withTrashed()->firstWhere('wikidata_id', $hit['wikidata_id']);

        if ($place !== null) {
            if ($place->trashed()) {
                $place->restore();
            }

            return $place;
        }

        $lat = $hit['lat'] ?? null;
        $lng = $hit['lng'] ?? null;

        if ($city === null && $lat !== null && $lng !== null) {
            $city = app(Geocoder::class)->city((float) $lat, (float) $lng);
        }

        return Place::query()->create([
            'city_id' => $city?->getKey(),
            'name' => $hit['name'],
            'kind' => $hit['kind'] ?? 'other',
            'wikidata_id' => $hit['wikidata_id'],
            'lat' => $hit['lat'] ?? null,
            'lng' => $hit['lng'] ?? null,
            'description' => $hit['description'] ?? null,
            'architect' => $hit['architect'] ?? null,
            'built' => $hit['built'] ?? null,
            'image_url' => $hit['image'] ?? null,
            'image_credit' => isset($hit['image']) ? 'Wikimedia Commons' : null,
            'facts' => [],
        ]);
    }

    /**
     * @param  list<string>  $classes
     */
    private function kind(array $classes): string
    {
        foreach (self::KINDS as $kind => $ids) {
            if (array_intersect($classes, $ids) !== []) {
                return $kind;
            }
        }

        return 'other';
    }

    public static function thumb(string $commonsUrl): string
    {
        $file = rawurldecode(basename($commonsUrl));

        return 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($file).'?width='.(int) config('museumguide.images.width', 640);
    }

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return ['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)', 'Accept' => 'application/sparql-results+json'];
    }

    /**
     * @return list<array<string, array{value: string}>>
     */
    private function run(string $query): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)', 'Accept' => 'application/sparql-results+json'])
                ->timeout((int) config('museumguide.places.wikidata_timeout', 12))
                ->get(self::SPARQL, ['query' => $query, 'format' => 'json']);

            return $response->successful() ? (array) ($response->json('results.bindings') ?? []) : [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
