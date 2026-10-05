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
- Zwei Stufen je Aufnahme: siehe Abschnitt 11.

## 11. Schnell oder ausführlich (Sebastian, 05.10.2026: "ja baue das alles so")

- **Warum:** Sebastian will oft nur einen kurzen Überblick vor einem Bild, manchmal den guten Guide. Zwei Stufen je Aufnahme (`captures.mode`, Enum `GuideMode`: quick, full), Standard je Nutzer (`users.default_mode`, im Profil einstellbar, Vorgabe schnell), beim Fotografieren per Schalter "Gleich ausführlich" umschaltbar.
- **Schnell:** Erkennung wie gehabt (mit Rückfrage), dann ein einziger Aufruf `QuickOverview` (Sonnet 5.5, geringe Anstrengung, keine Websuche, Prompt `quick.md`, 150 bis 250 Wörter nach `museumguide.quick.words`, nur was das Modell sicher weiß). Ergebnis: Kurztext als Audioguide ohne MP3 (`tts_provider` browser) und Fact Sheet. Vorgelesen vom Handy über die Web Speech API (`speechSynthesis`, Sprache de-AT, am iPhone die Siri-Stimmen): Vorlesen, Pause, Von vorn, Tempo. Kein Rückspulen um 15 Sekunden (kann die Browser-Stimme nicht). Dauer etwa 20 bis 40 Sekunden, Kosten wenige Cent. Keine Recherche, kein Lerngedächtnis.
- **Ausführlich:** die Kette aus Abschnitt 10. Aus der Schnellstufe heraus über "Ausführlichen Guide erstellen" (`Pipeline::upgrade`): Werk und Erkennung bleiben, es läuft Recherche bis Merken; der neue Audioguide ersetzt den Kurztext in der Anzeige (`latestOfMany`), der alte bleibt in der Datenbank.
- "Erneut versuchen" kennt die Stufe: schnell wiederholt den einen Aufruf, ausführlich setzt beim letzten Stand fort.

## 12. Arbeitsweise in der Bauphase (Sebastian, 05.10.2026)

- "Können wir das direkt auf der App machen, bei der kleinen App brauche ich kein Staging." Entscheidung: direkt auf `main`, kein PR, kein Staging, solange nur Sebastian die App nutzt. Details in `CLAUDE.md` und `docs/betrieb.md` Abschnitt 4. PR #5 (Etappe 3) wurde so direkt gemergt.

## 13. Zeitvorgaben und Handy-Stimme (Sebastian, 05.10.2026)

- **Vorgabe:** schnell höchstens 10 Sekunden, ausführlich höchstens 45 Sekunden. Vorher: Cron-Worker (bis 60 Sekunden Wartezeit vor dem ersten Schritt) und fünf Aufrufe nacheinander, zusammen 2 bis 4 Minuten.
- **Schnell:** ein einziger Vision-Aufruf (`QuickGuide`, Sonnet 5.5, geringe Anstrengung, 100 bis 160 Wörter, Prompt `quick.md`) macht Erkennung, Kurztext und Fact Sheet zugleich und läuft synchron in der Anfrage (kein Queue-Weg, Knopf zeigt "Erkenne das Werk und schreibe den Überblick ..."). Fotos werden für die KI auf 1024 px verkleinert (`museumguide.vision_edge`). Unsichere Erkennung: nur Rückfrage, nach der Bestätigung schreibt `QuickOverview` (Prompt `quick-text.md`) den Kurztext, ebenfalls synchron. Erwartung 8 bis 12 Sekunden, die 10 sind nicht garantiert (Bildgröße, Modellauslastung).
- **Ausführlich:** Erkennung synchron (`RecognizeArtwork`), dann über die Queue `ResearchAndWrite` (ein Aufruf: Recherche mit höchstens 4 Websuchen, Skript mit eingebautem Faktencheck, Fact Sheet, Prompt `guide.md`), `SynthesizeAudio` (ElevenLabs `eleven_flash_v2_5`, deutlich schneller als `multilingual_v2`), danach ist die Aufnahme fertig; `UpdateKnowledge` lernt still nach. Die getrennten Jobs Recherche, Skript und Faktencheck (Abschnitt 10) sind damit zusammengelegt, der Faktencheck steht als Regel im Prompt. Vorhandene Recherche wird mitgegeben, dann keine Websuche. Erwartung 35 bis 60 Sekunden bis zur Stimme.
- **Worker sofort:** `App\Support\QueueKick` startet nach jedem Dispatch einen abgekoppelten `queue:work --stop-when-empty` (nohup, `KUNST_PHP` in der `.env`), höchstens einmal je 15 Sekunden. Der Cron bleibt als Netz. Mit systemd-Worker (Betrieb Abschnitt 2, Punkt 6) per `MUSEUMGUIDE_QUEUE_KICK=false` abschaltbar.
- **Handy-Stimme:** iOS bricht lange Texte nach etwa einer Minute ab, deshalb wird satzweise in die Warteschlange gestellt (Stücke bis 180 Zeichen, Kette über `onend`). "Satz zurück" statt 15 Sekunden. Stimmenwahl im Player (deutsche Stimmen, bevorzugt Siri, Erweitert oder Premium, de-AT), Auswahl bleibt im Browser gespeichert. Die neuen Siri-Stimmen gibt Safari nur her, wenn sie am iPhone geladen sind (Einstellungen > Bedienungshilfen > Gesprochene Inhalte > Stimmen > Deutsch), der Hinweis steht im Player.

## 14. Abschnitte im Fact Sheet und Player ohne Unterbrechung (Sebastian, 05.10.2026)

- **Abbruch beim Aufklappen:** Livewire zeichnete beim Aufklappen (Text, Fotos) die Seite neu und ersetzte den Player, die Wiedergabe stoppte. Jetzt: Aufklappen rein im Browser (Alpine `x-show`), Player-Karte mit `wire:ignore` (Livewire fasst sie nie an), Rückmeldung (Daumen, Schwierigkeit) in eigener Karte.
- **Abschnitte:** `fact_sheets.sections` (JSON) mit Künstler, Provenienz, Deutung, Epoche, Genau hinschauen, Außerdem, Zitat (Text, Sprecher, Anlass), Anekdote, Kuratorenstimme (Text, Name). Die Prompts verlangen Ergänzungen, die im Audio nicht gesagt werden, Unsicheres bleibt null. Anzeige als Karten mit Icons (`resources/views/components/kb-icon.blade.php`, Heroicons, Name `x-kb-icon`, weil `x-icon` von blade-icons belegt ist). Im ausführlichen Guide sollen belegtes Zitat, Anekdote und Kuratorenstimme auch im Audio vorkommen (Prompt `guide.md`).

## 15. Studio-Stimme auch in der Schnellstufe (Sebastian, 05.10.2026: "ja super so machen wir das")

- Safari bekommt die Siri-Stimmen nicht (Apple gibt sie Web-Apps nicht frei), nur die Stimmen aus Bedienungshilfen > Gesprochene Inhalte. Deshalb spricht jetzt auch die Schnellstufe mit ElevenLabs (`App\Services\Pipeline\Voice`, gemeinsam mit `SynthesizeAudio`), synchron nach dem Kurztext (etwa 4 Sekunden, 10 bis 15 Cent). Ohne Schlüssel oder bei Ausfall liest weiter das Handy vor (Stimmenwahl mit Kompakt, Erweitert, Premium).
- Stimmen (Sebastian, 05.10.2026: immer Mann und Frau, der Mann reif und gemütlich, dynamisch für gutes Storytelling): narrator "Christian - Warm and Captivating" (`NBqeXKdZHweef6y0B67V`, Mann), second und quote "Leonie - Clear and Engaging" (`uvysWDLbKpA4XvpD3GI6`, Frau), beide aus der ElevenLabs-Bibliothek in Sebastians Konto übernommen. IDs in `config/museumguide.php` und als `ELEVENLABS_VOICE_*` in der `.env` am Server. Der Prompt `guide.md` verlangt häufigen Wechsel (mindestens jedes dritte Segment die Frau, höchstens vier Sätze je Segment, Nachfragen, Blicklenkung). Stimmeinstellung lebendiger: stability 0,45, style 0,35 (`museumguide.tts.elevenlabs`).

## 16. Zwei Sprecher und Musikbett im ausführlichen Guide (Sebastian, 05.10.2026: "ja passt umsetzen")

- **Zwei Sprecher:** Jedes Segment wird mit der Stimme seiner Rolle eingesprochen (narrator "Christian", second und quote "Alexander"), alle Segmente gleichzeitig (`ElevenLabsTtsProvider::synthesizeMany` über `Http::pool`), darum nicht langsamer als eine Stimme. Kosten gleich, ElevenLabs rechnet nach Zeichen. Die Schnellstufe bleibt bei einer Stimme am Stück (ein Aufruf, schneller).
- **Zusammensetzen und Musik:** `App\Services\Pipeline\AudioMixer` (ffmpeg, am Server `/usr/bin/ffmpeg`): Segmente mit 0,5 s Pause verketten, Musikbett zwei Sekunden vorweg, dann bei 12 % Lautstärke unter der Stimme, am Ende drei Sekunden ausgeblendet. Ohne ffmpeg oder bei Fehler nur verkettet, ohne Musik. Etwa 1 bis 2 Sekunden Rechenzeit, keine laufenden Kosten.
- **Musik je Epoche (Sebastian, 05.10.2026: 10 bis 15 Stücke je Epoche, damit man hintereinander Verschiedenes hört):** `App\Services\Pipeline\MusicBed` wählt zufällig aus `storage/app/music/{epoche-slug}/*.mp3` (nie dasselbe wie beim vorigen Guide dieser Gruppe, Merker im Cache), dann die Zuordnung `museumguide.music.epochs` (Rokoko zu Barock, Biedermeier zu Romantik usw.), dann `default/`. Die Liste steht in `resources/music/manifest.tsv` (Epoche, Datei, URL, Lizenz, Titel): 84 ruhige Sätze aus den gemeinfreien Musopen-Aufnahmen und der United States Air Force Band auf Wikimedia Commons (Public Domain, CC0, drei mit Namensnennung, in `storage/app/music/QUELLEN.md`), je Epoche 12 bis 17 Stücke: Barock (Bach Goldberg, Albinoni, Vivaldi, Byrd), Klassik (Mozart, Haydn, Beethoven, Clementi), Romantik (Chopin, Brahms, Schubert, Mendelssohn, Dvořák, Smetana, Grieg, Strauss), Impressionismus (Scriabin, Granados, Albéniz, Rachmaninow, Mahler, Holst), Moderne (Mahler, Rachmaninow, Holst, Elgar), Standard (Mischung). `bin/fetch-music` lädt sie am Server, längere Sätze werden auf 10 Minuten gekürzt. Eigene Stücke einfach als MP3 in den Epochen-Ordner legen.
- Abschaltbar mit `MUSEUMGUIDE_MUSIC=false`.

## 17. Niveau des Guides und zweite Stimme (Sebastian, 05.10.2026)

- "Ich brauche den Guide wirklich auf einem tieferen Niveau, so wie eine Kuratorin über so ein Werk sprechen würde, die sich mit einem Kunstexperten austauscht." Prompt `guide.md` neu: Fachgespräch auf Kuratorenniveau, keine Grundlagen, keine Hinweise auf Wiener Museen, die eine Wiener Guide kennt (Wien-Bezug nur, wenn er inhaltlich trägt), keine Vermutungen zur Ausstellungssituation ("vielleicht Sonderausstellung"), keine Sätze über Unbekanntes oder Lücken (unbelegte Provenienz bleibt einfach weg). Inhalt: Bildaufbau, Malweise, Restaurierungsbefunde, Ikonographie, Forschungsstand und Streitfragen, Vergleichswerke mit Begründung. Die Frau ist nicht mehr die Fragende, sondern eine zweite Fachfrau mit eigenem Schwerpunkt (Technik, Material, Ikonographie, Provenienz), die ergänzt und mit Argumenten widerspricht. Dieselben Regeln verkürzt in `quick.md` und `quick-text.md`.
- Zweite Stimme: "Leonie" wirkte neben "Christian" zu jung ("fast etwas dumm"). Jetzt "Sabrina - Authentic and Engaging" (`cqPdIo76zSHFDcSZpFov`, reif, ruhig, artikuliert), in Sebastians ElevenLabs-Bibliothek übernommen, `ELEVENLABS_VOICE_SECOND` und `_QUOTE` in der `.env`.

## 18. Aufgeräumte Werk-Seite (Sebastian, 05.10.2026)

- Reihenfolge: Kopf (Titel, Künstler, Stand) mit dem eigenen Foto klein rechts daneben, dann Rückfrage oder "Ausführlichen Guide erstellen", Kurzfakten, Kernaussagen, Abschnitte (Künstler, Provenienz, Deutung, Epoche, Genau hinschauen, Außerdem), Zitat, Anekdote, Kuratorenstimme, Querverweise, Text zum Mitlesen (zugeklappt), Quellen, Fotos (zugeklappt), ganz am Ende "War der Guide gut?" (Daumen, Schwierigkeit) und Löschen. Player bleibt fest unten.
- Alle Kästen außer dem Text zum Mitlesen sind Stichpunkte: die Abschnitte kommen vom Modell als Listen (Schema `sections.*` als Array, je 2 bis 5 Punkte von höchstens 20 Wörtern); ältere Aufnahmen mit Fließtext werden satzweise aufgeteilt.
- Weg: Kasten "Für deine Gäste" (auch aus Schema und Prompts), Kostenzeile unten.

## 19. Besuch starten mit drei Knöpfen (Sebastian, 05.10.2026)

- Vorher musste erst die Standortsuche durch (bis 12 Sekunden), ehe "Ich bin im ..." und "Ohne Museum" erschienen. Jetzt stehen alle drei Wege von Anfang an auf der Startkarte: "Museum in der Nähe suchen" (GPS plus Places), "Museum eingeben" (Name und Stadt), "Ohne Museum" (sofort).

## 20. Bilder zu Künstler und Vergleichswerken (Sebastian, 05.10.2026)

- Wunsch: beim Abschnitt Künstler immer ein Bild des Künstlers, und Bilder zu den Werken, auf die der Text verweist; je Künstler einmal erfasst und dann verknüpft, damit die App nicht aufgebläht wird.
- Umsetzung: `App\Services\Images\WikiImages` sucht bei Wikidata (deutsch, dann englisch), nimmt das Bild aus Eigenschaft P18 und baut einen Vorschau-Link über Wikimedia Commons (`Special:FilePath`, Breite `museumguide.images.width` = 640 px) samt Nachweis (Urheber, Lizenz, "Wikimedia Commons"). Nur Links, keine Dateien. Gespeichert in `artists.portrait_url` und `.portrait_credit`, `artworks.image_url` und `.image_credit` (je mit `*_checked_at`, einmal geprüft, auch wenn nichts gefunden wurde), Vergleichswerke in `related_works` (Titel, Künstler, Jahr, Grund, Bild) je Werk.
- Ablauf: Das Fact Sheet bekommt `sections.related_works` (2 bis 6 Werke, auf die Skript oder Abschnitte verweisen). Nach Kurztext bzw. Skript schickt `QuickOverview::fetchImages` den Job `FetchImages` in die Queue (Worker sofort angestoßen), die Antwortzeit bleibt unberührt. Die Werk-Seite pollt nach dem Fertigwerden noch bis zu drei Minuten alle 5 Sekunden, bis die Bilder da sind. Anzeige: Portrait im Abschnitt Künstler, Karte "Vergleichswerke" als seitlich scrollbare Reihe. Abschaltbar mit `MUSEUMGUIDE_IMAGES=false` (in Tests aus, `phpunit.xml`).
- Grenzen: Wikidata kennt nicht jedes Werk; dann steht "kein Bild". Bildrechte: Commons liefert Werke mit freier Lizenz oder gemeinfrei, der Nachweis steht als Tooltip am Bild.



## 21. Künstlerprofil (Sebastian, 05.10.2026)

- "Bei Albrecht Dürer sind die Fakten über den Künstler etwas dürftig: Geburt, Tod, wichtigste Werke, Rezeption in der Kunstgeschichte. Manchmal habe ich auch Bilder von komplett unbekannten Künstlern dabei."
- `App\Jobs\ProfileArtist`: einmal je Künstler (nach 180 Tagen neu) ein eigener Aufruf (Sonnet 5.5, höchstens 3 Websuchen, Prompt `artist.md`, Schema `Schemas::artistProfile`): Geburt und Tod mit Ort, Leben (4 bis 8 Stichpunkte), wichtigste Werke mit Jahr und Standort, Stil und Technik, Rezeption (Einfluss, Wiederentdeckung, Forschungsstand). Bei unbekannten Namen helfen die Websuchen (AKL, Wien Geschichte Wiki, RKD), lieber wenig Belegtes als Füllmaterial. Abgelegt in `artists.profile` (JSON) und `profile_checked_at`, fehlende Lebensjahre werden am Künstler nachgetragen. Kosten einmalig je Künstler, etwa 5 bis 10 Cent.
- Läuft nach dem Guide in der Queue (aus `QuickOverview::fetchImages`, zusammen mit den Bildern), die Werk-Seite pollt bis zu drei Minuten nach. Anzeige im Abschnitt "Künstler": Portrait, Lebensdaten, Leben, Stil und Technik, Rezeption, Wichtige Werke, darunter "Zu diesem Werk" (die werkbezogenen Punkte aus dem Fact Sheet). Abschaltbar mit `MUSEUMGUIDE_ARTIST_PROFILE=false` (in Tests aus).

## 8. Offen nach Etappe 3 (Stand 05.10.2026)

- Schlüssel für Anthropic und ElevenLabs unter Admin > Einstellungen > Zugänge eintragen (ohne Anthropic-Schlüssel bleibt jede Aufnahme mit Fehlermeldung stehen; ohne ElevenLabs gibt es Text ohne Audio). Google Places optional.
- Stimmen-IDs in `config/museumguide.php` (`tts.elevenlabs.voices`) auf echte Stimmen setzen.
- Entscheidung schnell/ausführlich (Abschnitt 10, letzter Punkt).
- Etappe 4: zwei Stimmen und Zitatstimme, Entdecken-Reiter mit Tipps, Kopplung mit Martha im Alltag.
- Server: Staging und Live laufen (05.10.2026, Etappe 1 auf `main` gemergt und deployt). Offen sind die Root-Schritte systemd und app-register (docs/betrieb.md Abschnitt 2, Punkte 6 und 7).
- Datei `claude/laravel-apps-betrieb.md` ins Repo legen, falls sie bei Sebastian liegt.
