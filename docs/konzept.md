# Kunstbegleiter: Konzept und Entscheidungen

Stand: 04.10.2026. Das fachliche Grundgerüst steht in `docs/grundgeruest.md`, der Plan für das Fundament in `docs/plan-etappe-1.md`. Hier stehen die Entscheidungen, die beim Bau gefallen sind, nummeriert und mit Datum. Der Chat ist kein Gedächtnis: was nicht hier steht, ist beim nächsten Mal weg.

## 1. Rahmen (04.10.2026)

- Name: Arbeitstitel **Kunstbegleiter**, Repo `sfrankenberger/Kunstbegleiter-App`, Domains art.tourtool.app (live, Branch `main`) und art-staging.tourtool.app (Staging, Branch `staging`). Sebastian hat am 04.10.2026 Name, Domains und Monatslimit (30 Euro je Nutzer, Platzhalter) freigegeben und den Bau von Etappe 1 bestätigt.
- Zwei Nutzer (Sebastian, Martha), keine Selbstregistrierung. Nutzer entstehen über `php artisan kunst:user`, Admin-Recht über `--admin`.
- Eine App, zwei Ansichten: Handy-Oberfläche unter `/` (Livewire + Alpine, PWA) und Verwaltung unter `/admin` (Filament 5). Beide nutzen dieselbe Sitzung (Guard `web`).

## 2. Stack (04.10.2026)

- Laravel 13, PHP 8.5 (lokal und CI über php.new bzw. setup-php), MariaDB live, SQLite in Tests. Indexnamen höchstens 64 Zeichen, zusammengesetzte Indizes bekommen eigene Namen.
- Filament 5 für `/admin`, Livewire 4 + Alpine für die Handy-Oberfläche. Tailwind 4 über die CLI (`bin/build-css`), das gebaute `public/css/app.css` wird eingecheckt, kein Node am Server, kein Vite.
- Pest 4, Pint, GitHub Actions wie bei Tourtool (`.github/workflows/tests.yml`, PHP 8.5, Umschaltung auf eigenen Runner über `CI_RUNNER`).
- Pakete: filament/filament, spatie/laravel-activitylog, laravel/passkeys. Keine weiteren ohne Anlass.

## 3. Anmeldung (04.10.2026)

- E-Mail + Passwort auf `/anmelden` (Livewire `App\Livewire\Auth\Login`), höchstens 5 Versuche je Adresse und IP in einer Minute, "angemeldet bleiben" vorausgewählt. Sitzung 30 Tage (`SESSION_LIFETIME=43200`).
- Passkeys über die Routen des Pakets laravel/passkeys (`/passkeys/login/*`, `/user/passkeys/*`), ohne `password.confirm` (zwei Nutzer, Passkeys werden im Profil angelegt). Browser-Script `public/js/passkeys.js`, Vorlage Tourtool. RP-ID ist der Host aus `APP_URL`, auf Staging also `art-staging.tourtool.app`: Passkeys gelten je Umgebung.
- Kein Anmeldelink per Mail (anders als Tourtool): bei zwei Nutzern reichen Passwort und Passkey. Kann später kommen, wenn der Mailversand steht (Etappe 5).
- Filament nutzt unter `/admin/login` seine eigene Login-Seite, Zugang nur mit `is_admin`.

## 4. Datenmodell (04.10.2026)

18 Tabellen nach `docs/plan-etappe-1.md` Abschnitt 4, umgesetzt in `database/migrations`. Entscheidungen:

- Recherche hängt am Werk (`research.artwork_id`), nicht an der Aufnahme. Ein Werk, das beide fotografieren, wird einmal recherchiert.
- Papierkorb: `Visit` kaskadiert auf `captures` und `cityTips`, `Capture` auf `photos`, `audioGuides`, `factSheets` (`CascadesSoftDeletes`). `Artwork`, `Artist`, `Museum` haben Papierkorb ohne Kaskade: ein Werk im Papierkorb lässt die Aufnahmen stehen. Kein Papierkorb für `users`, `pairings`, `cities`, `epochs`, `exhibitions`, `research`, `knowledge_items`, `ai_calls`, `passkeys`, `activity_log`.
- Verlauf (`LogsChanges`): `User`, `Museum`, `Artist`, `Artwork`, `Visit`, `Capture`, `AudioGuide`. Passwort und Token nie, Aufbewahrung 2 Jahre (`activitylog:clean` täglich 04:20).
- Kosten doppelt: je Aufruf in `ai_calls` (Wahrheit für Limit und Admin), je Guide zusammengefasst in `audio_guides`. `AiCall::monthCents()` liefert den Monat, `User::monthlyLimitCents()` das Limit (eigener Wert oder Config).
- `visits.valid_until` statt Ablauf berechnen: `Visit::extend()` setzt jetzt + `museumguide.visit.minutes`. `User::activeVisit()` ist der jüngste gültige.
- `knowledge_items.subject` als Morph (Artist, Epoch, später Themen).
- Tabelle `research` (Laravel pluralisiert "research" nicht), Model `Research` mit `$table`.
- Enums als Strings in der Datenbank: `CaptureStatus`, `PhotoType`, `PairingStatus`, `GuideLength`, `TipKind`, `AiPurpose`.

## 5. KI, Stimmen, Ort als Schnittstellen (04.10.2026)

- `App\Services\Ai\ClaudeClient`: Hülle um die Anthropic Messages API, Modell je Zweck aus `config/museumguide.php` (`AiPurpose::model()`), `text()` und `json()` (kaputtes JSON einmal neu anfragen, dann Fehler), jeder Aufruf landet in `ai_calls`. Preise je Modell in `museumguide.pricing` (leer bis Etappe 3, dann aktuelle Werte nachschlagen, nicht aus dem Gedächtnis).
- `App\Contracts\TtsProvider` mit `FakeTtsProvider`, `App\Contracts\PlacesClient` mit `FakePlacesClient`. Bindung in `AppServiceProvider` nach `museumguide.tts.provider` und `museumguide.places.provider`. Echte Anbieter kommen in Etappe 2 (Google Places) und 3 (ElevenLabs oder OpenAI TTS).
- Prompts liegen ab Etappe 3 in `resources/prompts/`.

## 6. Betrieb (04.10.2026)

- Eigene Plesk-Sites im Abo tourtool.app: `art.tourtool.app` (Verzeichnis `/var/www/vhosts/tourtool.app/art.tourtool.app`, Document Root `.../public`) und `art-staging.tourtool.app` (Basic-Auth). Datenbanken `kunst` und `kunst_staging`.
- Redis Instanz 6380, DB 6 live, DB 7 staging, Präfix `kunst_` bzw. `kunst_stg_`. Cache, Session und Queue über Redis (lokal und in Tests database bzw. array).
- Queue-Worker als systemd-Dienst (`deploy/kunst-queue.service`), Neustart im `deploy.sh` per sudoers (`deploy/sudoers-kunst`). Scheduler per Cron.
- Datensicherung über `app-register` und die Seite Admin > Datensicherungen (`BackupRestoreService`, nur mit `BACKUP_APP`). Fotos und MP3 in `storage/app/private` liegen im restic-Backup.
- Live-Fortschritt vorerst per Polling (`wire:poll`), Reverb erst, wenn es stört.
- Details Schritt für Schritt in `docs/betrieb.md`.

## 7. Offen nach Etappe 1

- Server einrichten (docs/betrieb.md Abschnitt 2), Staging zuerst.
- Etappe 2: GPS, Museum über Places, Besuch starten, Fotos aufnehmen und hochladen.
- Datei `claude/laravel-apps-betrieb.md` ins Repo legen, falls sie bei Sebastian liegt.
- TTS-Anbieter wählen, Google-Places-Schlüssel anlegen, Martha ab Etappe 3.
