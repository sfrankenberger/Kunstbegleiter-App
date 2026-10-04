<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Anmeldung der Handy-Oberflaeche (/anmelden): E-Mail und Passwort, dazu "Mit Passkey anmelden" ueber die Routen
 * des Pakets laravel/passkeys (public/js/passkeys.js). Hoechstens 5 Versuche je Adresse und IP in einer Minute.
 * Keine Selbstregistrierung: Nutzer entstehen per `php artisan kunst:user`.
 */
#[Layout('components.layouts.app', ['title' => 'Anmelden', 'tabs' => false])]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    public function login(): RedirectResponse|Redirector|null
    {
        $this->validate();

        $key = Str::lower($this->email).'|'.(string) request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden noch einmal.']);
        }

        if (! Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'E-Mail oder Passwort stimmen nicht.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectIntended(route('jetzt'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
