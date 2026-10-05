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
                'original' => self::nullableString(),
                'speaker' => ['type' => 'string'],
                'context' => self::nullableString(),
                'source_url' => ['type' => 'string'],
            ])),
            'sources' => self::array($source),
            'existing_guides' => self::array(self::object(['title' => ['type' => 'string'], 'url' => ['type' => 'string']])),
            'vienna_links' => self::array(self::object(['title' => ['type' => 'string'], 'reason' => ['type' => 'string']])),
            'artist_born' => ['type' => ['integer', 'null']],
            'artist_died' => ['type' => ['integer', 'null']],
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
                'guest_ideas' => self::object([
                    'opener' => ['type' => 'string'],
                    'question' => ['type' => 'string'],
                    'anecdote' => ['type' => 'string'],
                    'vienna_link' => ['type' => 'string'],
                ]),
                'cross_references' => self::array(['type' => 'string']),
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
    private static function nullableString(): array
    {
        return ['type' => ['string', 'null']];
    }
}
