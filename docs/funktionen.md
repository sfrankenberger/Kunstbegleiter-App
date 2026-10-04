# Funktionsinventar

Welche Funktion wo liegt. Vor jeder Arbeit prüfen, ob es den Baustein schon gibt; nach jeder Arbeit ergänzen.

## Anmeldung und Nutzer

| Funktion | Wo |
|---|---|
| Login E-Mail + Passwort (Handy) | `App\Livewire\Auth\Login`, `resources/views/livewire/auth/login.blade.php`, Route `login` (`/anmelden`) |
| Login Admin | Filament-Standard unter `/admin/login` (`AdminPanelProvider`) |
| Passkeys anlegen, anmelden, löschen | Paket laravel/passkeys (Routen `/passkeys/login/*`, `/user/passkeys/*`), `App\Livewire\PasskeyManager` im Profil, `public/js/passkeys.js`, Modell `App\Models\Passkey` |
| Abmelden | `App\Http\Controllers\Auth\LogoutController`, Route `logout` (POST `/abmelden`) |
| Nutzer anlegen oder aktualisieren | `php artisan kunst:user` (`App\Console\Commands\CreateUser`), Admin > Nutzer (`App\Filament\Resources\Users\UserResource`) |
| Zugang zum Admin | `User::canAccessPanel` (`is_admin`) |

## Handy-Oberfläche

| Funktion | Wo |
|---|---|
| Hülle mit vier Reitern, PWA | `resources/views/components/layouts/app.blade.php`, `components/tab-bar.blade.php`, `public/manifest.webmanifest`, `public/sw.js`, Icons `public/branding/` |
| Reiter Jetzt (aktiver Besuch, Werke des Besuchs) | `App\Livewire\Pages\Jetzt` |
| Reiter Archiv (fertige Aufnahmen, Suche) | `App\Livewire\Pages\Archiv` (`?q=`) |
| Reiter Entdecken (Hülle) | `App\Livewire\Pages\Entdecken` |
| Reiter Profil (Vorwissen, Länge, Kosten, Passkeys, Abmelden) | `App\Livewire\Pages\Profil` |
| CSS bauen | `bin/build-css`, `resources/css/app.css`, Ergebnis `public/css/app.css` (eingecheckt) |

## Admin

| Funktion | Wo |
|---|---|
| Nutzer (Konto, Vorwissen, Limit, Kosten des Monats, Verlauf) | `App\Filament\Resources\Users\UserResource` |
| Datensicherungen | `App\Filament\Pages\Backups`, `App\Support\Backup\BackupRestoreService`, `config/backup-restore.php` |
| Papierkorb-Bausteine | `App\Filament\Support\Trash` (filter, recordActions, bulkActions, query) |
| Verlauf-Tab | `App\Filament\Support\ActivitiesRelationManager` |

## Daten und Standards

| Funktion | Wo |
|---|---|
| Papierkorb mit Kindern | `App\Models\Concerns\CascadesSoftDeletes` (`$softCascades`) |
| Änderungsprotokoll | `App\Models\Concerns\LogsChanges` (`changeLog()`), `activitylog:clean` in `routes/console.php` |
| Aktiver Besuch, verlängern | `User::activeVisit()`, `Visit::extend()`, `Visit::remainingMinutes()` |
| Kopplung, Partner | `Pairing::between()`, `Pairing::partnerOf()`, `User::partner()` |
| Kosten je Nutzer und Monat | `AiCall::monthCents()`, `User::monthlyLimitCents()` |
| Epochen | `Database\Seeders\EpochSeeder` (läuft bei jedem Deploy) |
| Enums | `App\Enums\{CaptureStatus, PhotoType, PairingStatus, GuideLength, TipKind, AiPurpose}` |

## KI, Stimmen, Ort

| Funktion | Wo |
|---|---|
| Anthropic-Aufruf (Text, JSON mit Wiederholung, Kostenprotokoll) | `App\Services\Ai\ClaudeClient`, `App\Services\Ai\Pricing`, `config/museumguide.php` |
| Text-zu-Sprache | `App\Contracts\TtsProvider`, `App\Contracts\TtsResult`, `App\Services\Tts\FakeTtsProvider` |
| Museum in der Nähe | `App\Contracts\PlacesClient`, `App\Services\Places\FakePlacesClient` |
| Prompts | `resources/prompts/` (ab Etappe 3) |
