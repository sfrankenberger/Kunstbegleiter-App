<?php

namespace App\Enums;

/**
 * Zwei Stufen je Aufnahme (Sebastian, 05.10.2026): "schnell" ist Erkennung plus Kurzueberblick ohne Websuche,
 * vorgelesen vom Handy (Web Speech API). "ausfuehrlich" ist die ganze Kette mit Recherche, Faktencheck und
 * ElevenLabs. Aus der Schnellstufe heraus laesst sich der ausfuehrliche Guide nachbestellen.
 */
enum GuideMode: string
{
    case Quick = 'quick';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Quick => 'Schnell (Überblick, Handy liest vor)',
            self::Full => 'Ausführlich (Recherche und Studio-Stimme)',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Quick => 'Schnell',
            self::Full => 'Ausführlich',
        };
    }
}
