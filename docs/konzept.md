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
- Queue-Worker als systemd-Dienst (`deploy/kunst-queue.service`), Neustart nach dem Deploy über `kunst-queue-reload.path` (wie Tourtool, kein sudo). Bis Root die Dienste anlegt, läuft der Worker per Cron mit `--stop-when-empty`. Scheduler per Cron.
- PHP-Handler `plesk-php85-fastcgi` (LiteSpeed), nicht FPM (05.10.2026, Abschnitt 2 in docs/betrieb.md).
- Datensicherung über `app-register` und die Seite Admin > Datensicherungen (`BackupRestoreService`, nur mit `BACKUP_APP`). Fotos und MP3 in `storage/app/private` liegen im restic-Backup.
- Live-Fortschritt vorerst per Polling (`wire:poll`), Reverb erst, wenn es stört.
- Details Schritt für Schritt in `docs/betrieb.md`.

## 7. Etappe 2: Besuch und Kamera (05.10.2026)

- **Besuch starten:** Reiter Jetzt, Knopf "Besuch starten" holt den Standort aus dem Browser (`navigator.geolocation`), `VisitService::nearbyMuseums` fragt den `PlacesClient` im Umkreis `museumguide.places.radius_m` (Standard 300 m). Ein Treffer startet den Besuch sofort, mehrere zeigen eine Auswahl, keiner oder ein Fehler der Suche zeigen "Ich bin im ..." (Museum und Stadt von Hand) oder "Ohne Museum starten". Ein laufender Besuch ohne Museum bekommt es später über "Museum eintragen".
- **Museum:** aus einem Places-Treffer per `place_id` einmal angelegt (auch aus dem Papierkorb geholt), Stadt aus der Adresse (Postleitzahl plus Name, Land AT/DE/CH aus dem Adressende). Von Hand eingetragene Museen werden über den Namen (ohne Groß/Klein) wiederverwendet und haben keine `place_id`.
- **Ein aktiver Besuch je Nutzer:** `VisitService::start` beendet den vorigen (`valid_until` auf jetzt). "Beenden" im Kopf des Besuchs macht dasselbe. Standort nur am Besuch gespeichert (lat, lng, Genauigkeit), keine Spur.
- **Google Places:** `GooglePlacesClient` (Places API New, `places:searchNearby`, Typen museum und art_gallery, Sprache de, nach Entfernung). Aktiv mit `MUSEUMGUIDE_PLACES=google` und `GOOGLE_PLACES_KEY` in der `.env`, sonst der Fake mit vier Wiener Museen.
- **Fotos:** 1 bis 3 je Aufnahme, Kamera (`capture="environment"`) oder Fotos-Mediathek. Alpine verkleinert im Browser auf `museumguide.photos.max_edge` (2000 px) als JPEG 85 % und lädt über Livewire (`uploadMultiple`) hoch. Server prüft Bild und Größe (`museumguide.photos.max_bytes`). Dateien unter `storage/app/private/captures/{capture}/1.jpg` (Disk `local`), Auslieferung nur über signierte Adressen (`CapturePhoto::url`, 60 Minuten, Route `fotos.show`) und nur für Nutzer mit Sicht auf die Aufnahme (`CapturePolicy`: Besitzer oder Partner des geteilten Besuchs).
- **Aufnahme:** `CaptureService::create` legt Capture (Status hochgeladen) und Fotos an, erstes Foto Typ Werk, weitere Werktext (die automatische Erkennung kommt in Etappe 3), und verlängert den Besuch. Seite `/aufnahme/{capture}`: Fotos, Typ je Foto korrigierbar, Löschen in den Papierkorb (Fotos gehen mit).
- `Visit::remainingMinutes()` rundet auf (30 Sekunden vor Ablauf zeigt noch 1 Minute).

## 9. Zugänge im Admin (05.10.2026)

- Sebastian wollte Schlüssel und Token selbst pflegen, statt die `.env` am Server zu bearbeiten. Seite Admin > Einstellungen > Zugänge (`App\Filament\Pages\Zugaenge`, nur Admin): Anthropic, Google Places, ElevenLabs, OpenAI.
- Ablage in der Tabelle `settings` (`key`, `value` verschlüsselt als `text`, `updated_by_id`), Zugriff nur über `App\Support\Secrets` (`get`, `has`, `source`, `set`, `hint`). Reihenfolge: Admin vor `.env`; die `.env`-Werte in `config/museumguide.php` bleiben als Rückfall. Cache je Schlüssel, beim Speichern geleert.
- Die Seite zeigt nie den ganzen Schlüssel, nur Herkunft (Admin, .env, fehlt) und die letzten vier Zeichen. Leer lassen behält den Wert, "Löschen" entfernt ihn. Kein Änderungsprotokoll für `settings` (Geheimnisse), nur `updated_by_id`.
- "Anthropic prüfen" (GET /v1/models) und "Google Places prüfen" (Nearby Search um das KHM) melden, ob der Schlüssel gültig ist.
- Anbieterwahl `auto` (Standard in `.env.example`): Google Places, sobald ein Schlüssel da ist, sonst Fake; TTS bis Etappe 3 immer Fake. `MUSEUMGUIDE_PLACES=fake` erzwingt den Fake.

## 8. Offen nach Etappe 2 (Stand 05.10.2026)

- Google-Places-Schlüssel anlegen und unter Admin > Einstellungen > Zugänge eintragen (bis dahin Fake-Museen, nur Wien).
- Etappe 3: Pipeline (Erkennung mit Typ-Erkennung der Fotos, Recherche, Skript, Faktencheck, Fact Sheet, Audio mit einer Stimme), Prompts in `resources/prompts/`, Preise in `museumguide.pricing`.
- Museums-Recherche im Hintergrund beim Start eines Besuchs (Grundgerüst, "Beim Öffnen der App" Punkt 3) kommt mit der Pipeline.

- Server: Staging und Live laufen (05.10.2026, Etappe 1 auf `main` gemergt und deployt). Offen sind die Root-Schritte systemd und app-register (docs/betrieb.md Abschnitt 2, Punkte 6 und 7).
- Datei `claude/laravel-apps-betrieb.md` ins Repo legen, falls sie bei Sebastian liegt.
- TTS-Anbieter wählen, Martha ab Etappe 3.
