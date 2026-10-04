<?php

namespace App\Models;

use Laravel\Passkeys\Passkey as BasePasskey;

/**
 * Passkey eines Nutzers (WebAuthn, laravel/passkeys). Das Paket speichert den oeffentlichen Schluessel und den
 * Zaehler im JSON `credential`; hier nur die Darstellung fuer das Profil.
 */
class Passkey extends BasePasskey
{
    /**
     * @return array{id: int, name: string, created_at: ?string, last_used_at: ?string}
     */
    public function toRow(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'name' => (string) $this->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
