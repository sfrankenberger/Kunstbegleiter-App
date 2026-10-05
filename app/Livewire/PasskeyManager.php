<?php

namespace App\Livewire;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Component;

/**
 * Passkeys der angemeldeten Person im Profil: Liste, "Passkey hinzufuegen" (Alpine ruft public/js/passkeys.js
 * und damit die Routen des Pakets laravel/passkeys), Loeschen mit Rueckfrage.
 */
class PasskeyManager extends Component
{
    /**
     * @return list<array{id: int, name: string, created_at: ?string, last_used_at: ?string}>
     */
    public function passkeys(): array
    {
        return $this->user()->passkeys()->orderBy('created_at')->get()
            ->map(fn (Passkey $passkey): array => $passkey->toRow())
            ->values()
            ->all();
    }

    public function delete(int $id): void
    {
        $passkey = $this->user()->passkeys()->whereKey($id)->first();

        if ($passkey instanceof Passkey) {
            app(DeletePasskey::class)($this->user(), $passkey);
        }
    }

    public function render(): View
    {
        return view('livewire.passkey-manager', ['passkeys' => $this->passkeys()]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
