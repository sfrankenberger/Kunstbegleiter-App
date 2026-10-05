<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Nutzer anlegen (keine Selbstregistrierung): `php artisan kunst:user mail@example.com --name="Sebastian" --admin`.
 * Gibt ein zufaelliges Passwort aus; ein Passkey kann danach im Profil angelegt werden. Bei bestehender Adresse
 * wird nur Name und Admin-Recht aktualisiert, mit --password ein neues Passwort gesetzt.
 */
class CreateUser extends Command
{
    protected $signature = 'kunst:user {email : E-Mail-Adresse} {--name= : Anzeigename} {--admin : Zugang zum Admin-Panel} {--password : Neues Passwort setzen, wenn der Nutzer schon besteht}';

    protected $description = 'Nutzer anlegen oder aktualisieren und das Passwort ausgeben';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Keine gültige E-Mail-Adresse: '.$email);

            return self::FAILURE;
        }

        $user = User::query()->firstWhere('email', $email);
        $password = Str::password(16, symbols: false);

        if ($user === null) {
            $user = User::query()->create([
                'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
                'email' => $email,
                'password' => $password,
                'is_admin' => (bool) $this->option('admin'),
            ]);

            $this->info('Nutzer angelegt: '.$user->name.' <'.$user->email.'>'.($user->is_admin ? ' (Admin)' : ''));
            $this->line('Passwort: '.$password);

            return self::SUCCESS;
        }

        $changes = array_filter([
            'name' => $this->option('name') ?: null,
            'is_admin' => $this->option('admin') ? true : null,
            'password' => $this->option('password') ? $password : null,
        ], fn (mixed $value): bool => $value !== null);

        $user->fill($changes)->save();

        $this->info('Nutzer aktualisiert: '.$user->name.' <'.$user->email.'>'.($user->is_admin ? ' (Admin)' : ''));

        if ($this->option('password')) {
            $this->line('Neues Passwort: '.$password);
        }

        return self::SUCCESS;
    }
}
