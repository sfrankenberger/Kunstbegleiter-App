<?php

namespace App\Enums;

/**
 * Was ein Foto zeigt: das Werk, den Werktext (Schild) oder den Raumtext.
 */
enum PhotoType: string
{
    case Artwork = 'artwork';
    case Label = 'label';
    case RoomText = 'room_text';

    public function label(): string
    {
        return match ($this) {
            self::Artwork => 'Werk',
            self::Label => 'Werktext',
            self::RoomText => 'Raumtext',
        };
    }
}
