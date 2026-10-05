<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bilder von Wikidata und Wikimedia Commons (docs/konzept.md Abschnitt 20): Suche nach Kuenstler oder Werk
 * (wbsearchentities, Deutsch, dann Englisch), Bild aus der Eigenschaft P18, Vorschau ueber Special:FilePath
 * (Breite aus config museumguide.images.width), Nachweis (Urheber, Lizenz) aus den Commons-Metadaten.
 * Nur Links, keine Dateien. Alle Fehler enden still in null.
 */
class WikiImages
{
    public const WIKIDATA = 'https://www.wikidata.org/w/api.php';

    public const COMMONS = 'https://commons.wikimedia.org/w/api.php';

    /**
     * Portraet oder Abbildung: ['url' => ..., 'credit' => ..., 'wikidata_id' => ...] oder null. Bei Werken wird nur
     * der Titel gesucht und $mustContain (Nachname des Kuenstlers) muss in Label oder Beschreibung stehen.
     *
     * @return array{url: string, credit: ?string, wikidata_id: string}|null
     */
    public function find(string $search, ?string $wikidataId = null, ?string $mustContain = null): ?array
    {
        try {
            $id = $wikidataId ?: $this->searchEntity($search, $mustContain);

            if ($id === null) {
                return null;
            }

            $file = $this->imageFile($id);

            if ($file === null) {
                return null;
            }

            return [
                'url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode(str_replace(' ', '_', $file)).'?width='.(int) config('museumguide.images.width', 640),
                'credit' => $this->credit($file),
                'wikidata_id' => $id,
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function searchEntity(string $search, ?string $mustContain): ?string
    {
        foreach (['de', 'en'] as $language) {
            $hits = (array) $this->get(self::WIKIDATA, ['action' => 'wbsearchentities', 'search' => $search, 'language' => $language, 'uselang' => 'de', 'type' => 'item', 'limit' => 5, 'format' => 'json'])['search'] ?? [];

            foreach ($hits as $hit) {
                $haystack = mb_strtolower(($hit['label'] ?? '').' '.($hit['description'] ?? ''));

                if ($mustContain === null || str_contains($haystack, mb_strtolower($mustContain))) {
                    return (string) $hit['id'];
                }
            }
        }

        return null;
    }

    private function imageFile(string $id): ?string
    {
        $claims = (array) $this->get(self::WIKIDATA, ['action' => 'wbgetclaims', 'entity' => $id, 'property' => 'P18', 'format' => 'json'])['claims']['P18'] ?? [];
        $file = $claims[0]['mainsnak']['datavalue']['value'] ?? null;

        return is_string($file) && $file !== '' ? $file : null;
    }

    private function credit(string $file): ?string
    {
        $pages = (array) $this->get(self::COMMONS, ['action' => 'query', 'titles' => 'File:'.$file, 'prop' => 'imageinfo', 'iiprop' => 'extmetadata', 'format' => 'json'])['query']['pages'] ?? [];
        $meta = (array) (array_values($pages)[0]['imageinfo'][0]['extmetadata'] ?? []);
        $artist = trim(strip_tags((string) ($meta['Artist']['value'] ?? '')));
        $license = trim((string) ($meta['LicenseShortName']['value'] ?? ''));
        $credit = trim(implode(', ', array_filter([$artist !== '' ? mb_substr($artist, 0, 120) : null, $license !== '' ? $license : null, 'Wikimedia Commons'])));

        return $credit !== '' ? mb_substr($credit, 0, 300) : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query): array
    {
        $response = Http::withHeaders(['User-Agent' => 'Kunstbegleiter/1.0 (mail@sfrankenberger.com)'])
            ->timeout((int) config('museumguide.images.timeout', 8))
            ->get($url, $query);

        return $response->successful() ? (array) $response->json() : [];
    }
}
