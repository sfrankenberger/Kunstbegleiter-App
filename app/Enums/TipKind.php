<?php

namespace App\Enums;

enum TipKind: string
{
    case Exhibition = 'exhibition';
    case Church = 'church';
    case Building = 'building';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Exhibition => 'Ausstellung',
            self::Church => 'Kirche',
            self::Building => 'Bauwerk',
            self::Event => 'Veranstaltung',
        };
    }
}
