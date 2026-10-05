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

## 10. Etappe 3: Pipeline (05.10.2026)

- **Kette:** `CaptureService::create` startet nach dem Speichern der Fotos `Pipeline::start`. Queue-Jobs (`app/Jobs`, Basis `PipelineJob`: 3 Versuche, Pause 20 und 60 Sekunden, dann Status Fehler mit Meldung): `RecognizeArtwork`, `ResearchArtwork`, `WriteScript`, `CheckFacts`, `SynthesizeAudio`, `UpdateKnowledge`. Der laufende Schritt steht in `captures.step` (`PipelineStep`), die Seite Aufnahme lädt alle 4 Sekunden neu (`wire:poll`), solange es läuft.
- **Erkennung:** Claude Vision (Sonnet 5.5) bekommt alle Fotos als Base64, liest Schilder ab (`capture_photos.ocr_text`), setzt die Fototypen und liefert Titel, Künstler, Datierung, Inventarnummer, Epoche, Sicherheit und Alternativen (JSON-Schema `Schemas::recognition`). Unter `museumguide.pipeline.confidence_threshold` (0,7) wartet die Aufnahme (`needs_confirmation`): Vorschlag, Alternative oder Handeingabe bestätigen, dann geht es weiter.
- **Abgleich:** `ArtworkMatcher` findet ein bestehendes Werk über Museum plus Inventarnummer oder Titel plus Künstler (auch von Martha). Eine vorhandene Recherche wird wiederverwendet, `ResearchArtwork` übersprungen.
- **Recherche:** Sonnet 5.5 mit Websuche (Server-Werkzeug `web_search_20260209`, höchstens `museumguide.research.max_searches` = 8 Suchen, `pause_turn` wird fortgesetzt). Ergebnis: Zusammenfassung, belegte Aussagen mit Quelle, Zitate, Quellen, Wien-Bezüge, Lebensdaten. Fakten, Zitate und Wien-Bezüge liegen als Sondereinträge `_facts`, `_quotes`, `_vienna` in `research.sources`; `ContextBuilder::sources` liefert nur echte Quellen.
- **Skript:** `ContextBuilder` baut Vorwissen-Profil, Werke dieses Besuchs (auch vom Partner) und höchstens zwei Rückbezüge aus dem Lerngedächtnis. Modell Sonnet 5.5, bei `captures.premium` Opus 5.5. Länge nach `GuideLength` (Wörter). Ergebnis: Segmente (Rolle narrator, second, quote) und Fact Sheet (Kurzfakten, Kernaussagen, "Für deine Gäste" mit Einstieg, Frage, Anekdote, Wien-Bezug, Querverweise).
- **Faktencheck:** eigener Aufruf (Sonnet, geringe Anstrengung) streicht Unbelegtes oder markiert es als Deutung. Danach Stimme: eine Stimme (narrator) für alles, ElevenLabs (`eleven_multilingual_v2`), MP3 unter `captures/{capture}/guide-{guide}.mp3`, Auslieferung signiert über `audio.show`. Fällt die Stimme aus, bleibt der Guide lesbar (Meldung an der Aufnahme, Kette läuft weiter).
- **Lerngedächtnis:** Haiku fasst je Künstler und Epoche zusammen (`knowledge_items`), Fehler hier lassen den Guide trotzdem fertig werden.
- **Museumsrecherche:** `ResearchMuseum` beim Start eines Besuchs oder "Museum eintragen", höchstens einmal je Woche je Museum (`museums.researched_at`), fließt in Erkennung und Recherche ein.
- **Kosten:** jeder HTTP-Aufruf ein `ai_calls`-Eintrag (Tokens inklusive Cache, Websuchen in `characters`, Cent nach `museumguide.pricing`: Sonnet 5.5 2/10, Opus 5.5 4/20, Haiku 4.5 1/5 USD je Million, Websuche 10 USD je 1000, ElevenLabs 0,30 USD je 1000 Zeichen, Kurs 0,92). Vor Start und "Erneut versuchen" prüft `Pipeline::assertBudget` das Monatslimit; überschritten heißt Status Fehler mit Meldung, kein Aufruf.
- **Claude-API-Regeln** (aus der Doku, Stand 05.10.2026): Modell-IDs ohne Datum (`claude-sonnet-5-5`, `claude-opus-5-5`, `claude-haiku-4-5`), strukturierte Ausgabe über `output_config.format` (JSON-Schema mit `additionalProperties: false`, alle Felder required), Anstrengung über `output_config.effort`, Server-Rückfall `fallbacks: default` mit Beta-Header nur für Sonnet 5.5 und Opus 5.5, `stop_reason refusal` wird als Fehler gemeldet, nie `tool_choice` erzwingen.
- **Seite Aufnahme:** Fortschritt, Rückfrage, Player (Anhören, 15 Sekunden zurück, Tempo 0,8 bis 1,5), Daumen und Schwierigkeit (`audio_guides.feedback`, `difficulty_feedback`), Kurzfakten, Kernaussagen, Für deine Gäste, Querverweise, Text zum Mitlesen (zugeklappt), Quellen, Fotos (zugeklappt, sobald fertig), Kosten der Aufnahme, "Erneut versuchen" (`Pipeline::retry` setzt ab dem letzten erreichten Stand fort).
- **Tests:** `tests/Feature/PipelineTest.php` mit `Http::fake`-Sequenzen (Queue in Tests synchron, eine Kette läuft sofort durch). Keine echten Aufrufe in Tests.
- **Idee (Sebastian, 05.10.2026, noch nicht entschieden):** zwei Stufen je Aufnahme. "Schnell": Erkennung plus Kurzübersicht ohne Websuche, vorgelesen vom Handy (Web Speech API, auf dem iPhone die Siri-Stimmen, kostenlos, sofort). "Ausführlich": die ganze Kette mit Recherche, Faktencheck und ElevenLabs, auf Knopfdruck aus der Schnellstufe heraus. Vorschlag in der Sitzung vom 05.10.2026, Bau nach Sebastians Ja.

## 8. Offen nach Etappe 3 (Stand 05.10.2026)

- Schlüssel für Anthropic und ElevenLabs unter Admin > Einstellungen > Zugänge eintragen (ohne Anthropic-Schlüssel bleibt jede Aufnahme mit Fehlermeldung stehen; ohne ElevenLabs gibt es Text ohne Audio). Google Places optional.
- Stimmen-IDs in `config/museumguide.php` (`tts.elevenlabs.voices`) auf echte Stimmen setzen.
- Entscheidung schnell/ausführlich (Abschnitt 10, letzter Punkt).
- Etappe 4: zwei Stimmen und Zitatstimme, Entdecken-Reiter mit Tipps, Kopplung mit Martha im Alltag.
- Server: Staging und Live laufen (05.10.2026, Etappe 1 auf `main` gemergt und deployt). Offen sind die Root-Schritte systemd und app-register (docs/betrieb.md Abschnitt 2, Punkte 6 und 7).
- Datei `claude/laravel-apps-betrieb.md` ins Repo legen, falls sie bei Sebastian liegt.
