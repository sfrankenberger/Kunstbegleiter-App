<?php

namespace App\Services\Auth;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Anmelden mit Code aus der E-Mail (Sebastian, 14.10.2026): sechs Ziffern, 15 Minuten gueltig, hoechstens fuenf
 * Fehlversuche, nur gehasht im Cache. Ersetzt auch "Passwort vergessen": mit Code anmelden, im Profil neu setzen.
 */
class LoginCodes
{
    public const MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    /**
     * Code schicken, wenn es die Adresse gibt. Gibt nie preis, ob sie bekannt ist.
     */
    public function send(string $email): void
    {
        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        if ($user === null) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::MINUTES));

        Mail::to($user->email)->send(new LoginCodeMail($code, (string) $user->name));
    }

    /**
     * Code pruefen: liefert den Nutzer bei Erfolg (Code ist dann verbraucht), sonst null.
     */
    public function verify(string $email, string $code): ?User
    {
        $user = User::query()->where('email', Str::lower(trim($email)))->first();
        $code = preg_replace('/\D/', '', $code) ?? '';

        if ($user === null || strlen($code) !== 6) {
            return null;
        }

        $entry = Cache::get($this->key($user));

        if (! is_array($entry) || (int) $entry['attempts'] >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, (string) $entry['hash'])) {
            $entry['attempts'] = (int) $entry['attempts'] + 1;
            Cache::put($this->key($user), $entry, now()->addMinutes(self::MINUTES));

            return null;
        }

        Cache::forget($this->key($user));

        return $user;
    }

    private function key(User $user): string
    {
        return 'login-code:'.$user->getKey();
    }
}
