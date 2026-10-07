<?php

namespace App\Services\Pipeline;

/**
 * JSON-Schemas der KI-Antworten (strukturierte Ausgabe, docs/konzept.md Abschnitt 10). Jedes Objekt hat
 * additionalProperties false und alle Felder in required, wie die API es verlangt; optionale Werte sind nullable.
 */
class Schemas
{
    /** @return array<string, mixed> */
    public static function recognition(): array
    {
        return self::object(self::recognitionFields());
    }

    /**
     * Schnellstufe in einem Aufruf: Erkennung plus Kurztext plus Fact Sheet.
     *
     * @return array<string, mixed>
     */
    public static function quickGuide(): array
    {
        return self::object(self::recognitionFields() + self::scriptFields());
    }

    /**
     * Ausfuehrlich in einem Aufruf: Recherche plus Skript plus Fact Sheet.
     *
     * @return array<string, mixed>
     */
    public static function guide(): array
    {
        return self::object(self::researchFields() + self::scriptFields());
    }

    /** @return array<string, array<string, mixed>> */
    private static function recognitionFields(): array
    {
        return [
            'photos' => self::array(self::object([
                'index' => ['type' => 'integer'],
                'type' => ['type' => 'string', 'enum' => ['artwork', 'label', 'room_text']],
                'text' => self::nullableString(),
            ])),
            'title' => self::nullableString(),
            'artist' => self::nullableString(),
            'artist_life_dates' => self::nullableString(),
            'dating' => self::nullableString(),
            'technique' => self::nullableString(),
            'dimensions' => self::nullableString(),
            'inventory_number' => self::nullableString(),
            'epoch' => self::nullableString(),
            'confidence' => ['type' => 'number'],
            'alternatives' => self::array(self::object([
                'title' => ['type' => 'string'],
                'artist' => self::nullableString(),
                'reason' => self::nullableString(),
            ])),
            'notes' => self::nullableString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function research(): array
    {
        return self::object(self::researchFields());
    }

    /** @return array<string, array<string, mixed>> */
    private static function researchFields(): array
    {
        $source = self::object(['url' => ['type' => 'string'], 'title' => self::nullableString(), 'kind' => ['type' => 'string']]);

        return [
            'summary' => ['type' => 'string'],
            'facts' => self::array(self::object(['statement' => ['type' => 'string'], 'source_url' => ['type' => 'string']])),
            'quotes' => self::array(self::object([
                'text' => ['type' => 'string'],
                'speaker' => ['type' => 'string'],
                'context' => self::nullableString(),
                'source_url' => ['type' => 'string'],
            ])),
            // existing_guides und vienna_links entfernt (05.10.2026): die Grammatik aus Schema plus Websuche-Werkzeug
            // wurde Anthropic zu gross ("compiled grammar is too large"), beide Felder wurden nie angezeigt
            'sources' => self::array($source),
            'artist_born' => ['type' => 'integer', 'description' => '0, wenn unbekannt.'],
            'artist_died' => ['type' => 'integer', 'description' => '0, wenn unbekannt oder noch lebend.'],
            'wikidata_id' => self::nullableString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function script(): array
    {
        return self::object(self::scriptFields());
    }

    /** @return array<string, array<string, mixed>> */
    private static function scriptFields(): array
    {
        return [
            'segments' => self::array(self::segment()),
            'fact_sheet' => self::object([
                'key_facts' => self::array(self::object(['label' => ['type' => 'string'], 'value' => ['type' => 'string']])),
                'key_statements' => self::array(['type' => 'string']),
                'cross_references' => self::array(['type' => 'string']),
                // Abschnitte fuer den Bildschirm mit Dingen, die im Audio nicht gesagt werden (Abschnitt 14)
                'sections' => self::object([
                    'artist' => self::array(['type' => 'string']),
                    'provenance' => self::array(['type' => 'string']),
                    'interpretation' => self::array(['type' => 'string']),
                    'epoch' => self::array(['type' => 'string']),
                    'look' => self::array(['type' => 'string']),
                    'quote_text' => self::nullableString(),
                    'quote_speaker' => self::nullableString(),
                    'quote_context' => self::nullableString(),
                    'anecdote' => self::nullableString(),
                    'curator_text' => self::nullableString(),
                    'curator_name' => self::nullableString(),
                    'more' => self::array(['type' => 'string']),
                    // Vergleichswerke, auf die Text oder Abschnitte verweisen; Bilder holt FetchImages
                    'related_works' => self::array(self::object([
                        'title' => ['type' => 'string'],
                        'artist' => ['type' => 'string'],
                        'year' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ])),
                ]),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function check(): array
    {
        return self::object([
            'segments' => self::array(self::segment()),
            'changes' => self::array(self::object(['segment' => ['type' => 'integer'], 'change' => ['type' => 'string'], 'reason' => ['type' => 'string']])),
        ]);
    }

    /**
     * Kuenstlerprofil (ProfileArtist).
     *
     * @return array<string, mixed>
     */
    public static function artistProfile(): array
    {
        return self::object([
            'born' => self::nullableString(),
            'died' => self::nullableString(),
            'life' => self::array(['type' => 'string']),
            'key_works' => self::array(self::object(['title' => ['type' => 'string'], 'year' => ['type' => 'string'], 'location' => ['type' => 'string']])),
            'style' => self::array(['type' => 'string']),
            'reception' => self::array(['type' => 'string']),
            'sources' => self::array(self::object(['url' => ['type' => 'string'], 'title' => ['type' => 'string']])),
        ]);
    }

    /** Raumtext ablesen (RoomTextService). */
    public static function roomText(): array
    {
        return self::object(['title' => self::nullableString(), 'text' => ['type' => 'string']]);
    }

    /** @return array<string, mixed> */
    public static function museum(): array
    {
        return self::object([
            'exhibitions' => self::array(self::object(['title' => ['type' => 'string'], 'period' => self::nullableString(), 'url' => self::nullableString()])),
            'collection' => ['type' => 'string'],
            'online_collection' => self::nullableString(),
            'audioguide' => self::nullableString(),
            'notes' => self::array(['type' => 'string']),
        ]);
    }

    /** @return array<string, mixed> */
    public static function knowledge(): array
    {
        return self::object([
            'artist_summary' => ['type' => 'string'],
            'epoch_summary' => self::nullableString(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function segment(): array
    {
        return self::object([
            'role' => ['type' => 'string', 'enum' => ['narrator', 'second', 'quote']],
            'text' => ['type' => 'string'],
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    private static function array(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }

    /** @return array<string, mixed> */
    /**
     * Optionale Texte als einfacher String (leer = unbekannt). Union-Typen wie ["string", "null"] erlaubt die
     * API nur in kleiner Zahl ("schema contains too many parameters with union types"), darum keine.
     * Leere Strings werden in Schemas::normalize zu null.
     */
    private static function nullableString(): array
    {
        return ['type' => 'string', 'description' => 'Leer lassen, wenn unbekannt oder unsicher.'];
    }

    /**
     * Leere Strings und 0 bei Jahreszahlen in null verwandeln, rekursiv.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalize($value);
            } elseif (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            } elseif (in_array($key, ['artist_born', 'artist_died'], true) && (int) $value <= 0) {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
