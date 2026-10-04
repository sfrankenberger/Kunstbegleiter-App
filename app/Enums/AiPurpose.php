<?php

namespace App\Enums;

/**
 * Wofuer ein KI-Aufruf war (ai_calls.purpose). Das Modell je Zweck steht in config/museumguide.php.
 */
enum AiPurpose: string
{
    case Recognize = 'recognize';
    case Research = 'research';
    case Script = 'script';
    case Check = 'check';
    case Museum = 'museum';
    case Tips = 'tips';
    case Small = 'small';
    case Tts = 'tts';

    public function label(): string
    {
        return match ($this) {
            self::Recognize => 'Erkennung',
            self::Research => 'Recherche',
            self::Script => 'Skript',
            self::Check => 'Faktencheck',
            self::Museum => 'Museum',
            self::Tips => 'Stadt-Tipps',
            self::Small => 'Kleinkram',
            self::Tts => 'Stimme',
        };
    }

    public function model(): string
    {
        return (string) config('museumguide.models.'.$this->value, config('museumguide.models.default'));
    }
}
