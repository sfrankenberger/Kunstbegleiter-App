<?php

namespace App\Enums;

/**
 * Laenge des Audioguides: kurz (ca. 1,5 Min), normal (ca. 3 Min), ausfuehrlich (bis 4 Min).
 * Wortzahlen in config/museumguide.php.
 */
enum GuideLength: string
{
    case Short = 'short';
    case Normal = 'normal';
    case Long = 'long';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Kurz (ca. 1,5 Min)',
            self::Normal => 'Normal (ca. 3 Min)',
            self::Long => 'Ausführlich (bis 4 Min)',
        };
    }

    /**
     * @return array{min: int, max: int}
     */
    public function words(): array
    {
        $words = (array) config('museumguide.script.words.'.$this->value, ['min' => 350, 'max' => 450]);

        return ['min' => (int) ($words['min'] ?? 350), 'max' => (int) ($words['max'] ?? 450)];
    }
}
