<?php

namespace App\Enums;

enum PairingStatus: string
{
    case Requested = 'requested';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Angefragt',
            self::Active => 'Aktiv',
        };
    }
}
