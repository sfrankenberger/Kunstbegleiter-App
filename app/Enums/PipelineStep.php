<?php

namespace App\Enums;

/**
 * Laufender Schritt der Pipeline (captures.step) fuer die Fortschrittsanzeige "Erkenne Werk ... Recherchiere ...".
 */
enum PipelineStep: string
{
    case Recognizing = 'recognizing';
    case Confirming = 'confirming';
    case Researching = 'researching';
    case Writing = 'writing';
    case Checking = 'checking';
    case Speaking = 'speaking';
    case Learning = 'learning';

    public function label(): string
    {
        return match ($this) {
            self::Recognizing => 'Erkenne das Werk ...',
            self::Confirming => 'Bitte bestätigen, welches Werk es ist',
            self::Researching => 'Recherchiere ...',
            self::Writing => 'Schreibe den Audioguide ...',
            self::Checking => 'Prüfe die Fakten ...',
            self::Speaking => 'Spreche ein ...',
            self::Learning => 'Merke mir, was erzählt wurde ...',
        };
    }
}
