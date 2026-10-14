<?php

namespace App\Livewire\Auth;

use App\Services\Auth\LoginCodes;
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
 * des Pakets laravel/passkeys (public/js/passkeys.js), oder mit Code aus der E-Mail (LoginCodes, 14.10.2026; zugleich
 * "Passwort vergessen"). Hoechstens 5 Versuche je Adresse und IP in einer Minute, 3 Codes je 10 Minuten.
 * Keine Selbstregistrierung: Nutzer entstehen per `php artisan kunst:user`.
 */
#[Layout('components.layouts.app', ['title' => 'Anmelden', 'tabs' => false])]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    /** Anmeldeweg: password oder code */
    public string $mode = 'password';

    public bool $codeSent = false;

    public string $code = '';

    public string $notice = '';

    public function useCode(): void
    {
        $this->mode = 'code';
        $this->resetErrorBag();
    }

    public function usePassword(): void
    {
        $this->mode = 'password';
        $this->codeSent = false;
        $this->code = '';
        $this->notice = '';
        $this->resetErrorBag();
    }

    public function sendCode(LoginCodes $codes): void
    {
        $this->validateOnly('email');
        $key = 'login-code-send|'.Str::lower($this->email).'|'.(string) request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Zu viele Codes angefordert. Bitte in '.ceil(RateLimiter::availableIn($key) / 60).' Minuten noch einmal.']);
        }

        RateLimiter::hit($key, 600);

        try {
            $codes->send($this->email);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['email' => 'Die Mail konnte gerade nicht verschickt werden. Bitte später noch einmal oder mit Passwort anmelden.']);
        }

        $this->codeSent = true;
        $this->notice = 'Wenn die Adresse bei uns bekannt ist, ist der Code unterwegs. Er gilt '.LoginCodes::MINUTES.' Minuten, bitte auch im Spam-Ordner nachsehen.';
    }

    public function loginWithCode(LoginCodes $codes): RedirectResponse|Redirector|null
    {
        $this->validate(['email' => 'required|email', 'code' => 'required|string']);
        $key = Str::lower($this->email).'|'.(string) request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden noch einmal.']);
        }

        $user = $codes->verify($this->email, $this->code);

        if ($user === null) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'Der Code stimmt nicht oder ist abgelaufen.']);
        }

        RateLimiter::clear($key);
        Auth::login($user, $this->remember);
        session()->regenerate();

        return $this->redirectIntended(route('jetzt'), navigate: true);
    }

    public function login(): RedirectResponse|Redirector|null
    {
        $this->validate(['email' => 'required|email', 'password' => 'required|string']);

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
