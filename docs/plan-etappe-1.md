# Plan Etappe 1: Fundament

Stand: 04.10.2026. Vorschlag zur Freigabe, gebaut wird erst nach Sebastians OK. Fachliche Grundlage: `docs/grundgeruest.md`.

## 1. Vorschläge zu den offenen Fragen

| Frage | Vorschlag | Begründung |
|---|---|---|
| Name | Arbeitstitel **Kunstbegleiter** beibehalten | Ist im Grundgerüst schon so benannt, lässt sich jederzeit umbenennen (Name steht nur in `config/app.php`, Manifest und Doku) |
| Repository | `sfrankenberger/Kunstbegleiter-App`, privat | Gleiches Muster wie `Tourtool-App` und `Coaching-App`. Umbenennen auf GitHub ist ein Klick, alte Adresse leitet weiter |
| Domain | **art.tourtool.app** (live) und **art-staging.tourtool.app** (Staging, Basic-Auth) | Eigene Plesk-Site im Abo tourtool.app wie `staging.tourtool.app` und `mcp.tourtool.app`, eigenes Verzeichnis `/var/www/vhosts/tourtool.app/art.tourtool.app` und `.../art-staging.tourtool.app`. DNS-Zone liegt bei Cloudflare, Zertifikat über Plesk. Kein neues Abo, keine neue Domain. Eigene Sitzung und eigene Passkeys (RP-ID `art.tourtool.app`), kein Konflikt mit Tourtool |
| Datenbank | `kunst` und `kunst_staging`, Benutzer `kunst_app` und `kunst_stg` | Wie bei Tourtool, über Plesk angelegt |
| Redis | Instanz 6380, DB 6 (live) und DB 7 (Staging), Präfix `kunst_` bzw. `kunst_stg_` | Nach dem Betriebsstandard aus dem Grundgerüst. Cache, Session und Queue über Redis |
| Queue-Worker | systemd `kunst-queue.service` und `kunst-staging-queue.service` nach Vorlage `tourtool-queue-*.service`, Neustart im `deploy.sh` per sudoers-Eintrag | Pipeline-Jobs laufen bis zu 2 Minuten, ein Cron-Worker mit `--stop-when-empty` reicht dafür nicht |
| Backup | `app-register add kunst-prod ...` und `kunst-staging`, `BACKUP_APP` in die `.env`; Fotos und MP3 in `storage/app/private` landen im stündlichen restic-Backup | Standard |
| Live-Fortschritt | Etappe 1 und 2 mit Polling (`wire:poll.3s`), Reverb erst, wenn die Pipeline läuft und das Polling stört | Weniger Betrieb am Anfang, die Pipeline dauert ohnehin 1 bis 2 Minuten |
| CSS | Tailwind 4 über die Standalone-CLI (`bin/build-css`), gebaute CSS-Datei wird committed | Kein Node am Server (wie bei der Coaching-App). Filament und Livewire bringen eigene Assets mit |
| TTS | Etappe 1 nur das Interface `TtsProvider` und ein `FakeTtsProvider` für Tests; Anbieter in Etappe 3 | Entscheidung ElevenLabs oder OpenAI TTS kann warten, Code bleibt austauschbar |
| Monatslimit | Startwert 30 EUR je Nutzer in `config/museumguide.php`, Hinweis ab 80 % | Reiner Platzhalter, Sebastian legt den Wert fest |

Hinweis: Die im Grundgerüst genannte Datei `claude/laravel-apps-betrieb.md` liegt weder im Tourtool- noch im Coaching-App-Repo. Die darin zusammengefassten Punkte (Redis 6380, systemd, app-register, restic) stehen im Grundgerüst selbst und wurden hier übernommen. Falls die Datei lokal bei Sebastian liegt: bitte in das neue Repo unter `docs/betrieb-standard.md` legen.

## 2. Stack (wie Tourtool, Stand 04.10.2026)

- Laravel 13, PHP 8.5, MariaDB, Tests mit SQLite (Indexnamen höchstens 64 Zeichen, verschlüsselte Casts nie auf JSON-Spalten)
- Filament 5 für `/admin` (Nutzer, Kosten, Prompts, Datensicherungen), Livewire 4 + Alpine für die Handy-Oberfläche unter `/`
- Pest 4, Pint, GitHub Actions wie `tests.yml` von Tourtool (PHP 8.5, Umschaltung auf eigenen Runner über `CI_RUNNER`)
- Pakete: `spatie/laravel-activitylog`, `laravel/passkeys`, `laravel/horizon` nicht (zu viel für zwei Nutzer), `intervention/image` fürs Verkleinern am Server als Rückfall (Hauptweg bleibt der Browser)
- `deploy.sh` von Tourtool übernommen, plus `sudo systemctl restart kunst-queue` am Ende
- Branch `main` = live, `staging` = Staging. Etappen werden erst auf Staging getestet, dann nach `main` (Grundgerüst, Punkt 2)

## 3. Was Etappe 1 liefert

1. **Projekt**: Laravel 13 frisch, `CLAUDE.md`, `AGENTS.md` (Boost), `docs/` (Grundgerüst, dieser Plan, `konzept.md`, `funktionen.md`, `betrieb.md`), `.github/workflows/tests.yml`, `deploy.sh`, `pint.json`
2. **Anmeldung**: E-Mail + Passwort, Passkey optional (wie Tourtool `docs/anmeldung.md`), Rate-Limit auf Login. Zwei Nutzer per Seeder-Befehl `kunst:user <mail> --name=...` anlegen (Passwort wird ausgegeben). Keine Selbstregistrierung
3. **PWA-Hülle**: Manifest, Service Worker (nur App-Shell, keine Offline-Daten), Icons, vier Reiter unten (Jetzt, Archiv, Entdecken, Profil) als leere Seiten mit deutschem Text, Layout für 390 px
4. **Admin-Panel** `/admin` (Filament): Nutzer-Resource, Seite Datensicherungen (`BackupRestoreService`, `config/backup-restore.php`, gleiche Klassennamen wie Tourtool), Papierkorb-Helfer `Trash`, `ActivitiesRelationManager`
5. **Standards**: Traits `LogsChanges` und `CascadesSoftDeletes` (aus Tourtool übernommen, ohne den MCP-Teil), `activitylog:clean` im Scheduler (2 Jahre)
6. **Redis und Queue**: `.env.example` mit Redis-Schlüsseln, `config/queue.php` auf Redis, systemd-Unit-Datei als Vorlage im Repo (`deploy/kunst-queue.service`), Betriebsanleitung in `docs/betrieb.md`
7. **Datenmodell und Migrationen** (Abschnitt 4), Models mit Beziehungen, Factories, Enums (`CaptureStatus`, `PhotoType`, `PairingStatus`, `GuideLength`)
8. **Config** `config/museumguide.php`: Modellnamen je Aufgabe, Besuchsdauer 30 Minuten, Foto-Kante 2000 px, Monatslimit, Ziellängen, TTS-Anbieter
9. **Schnittstellen ohne Umsetzung**: `TtsProvider` (Interface + Fake), `PlacesClient` (Interface + Fake), Anthropic-Client-Hülle mit `Http::fake()`-Tests
10. **Tests**: je Baustein ein Rauchtest (Login, jede Reiter-Seite lädt, Admin lädt, Modelle mit Factories, Papierkorb kaskadiert, Verlauf schreibt)

Nicht in Etappe 1: Kamera, GPS, Places, Pipeline, Audio, Kopplung (Etappen 2 bis 5).

## 4. Datenmodell

Alle Tabellen mit `id`, `created_at`, `updated_at`. Geschäftsdaten zusätzlich `deleted_at` (Papierkorb) und `LogsChanges`. Beträge als Integer in Cent, Zeitzone Europe/Vienna, Koordinaten als `decimal(10,7)`.

| Tabelle | Felder | Bemerkungen |
|---|---|---|
| `users` | name, email (unique), password, locale (de), knowledge_profile (text), focus_areas (json), preferred_length (short/normal/long), voice_preferences (json), monthly_budget_cents (nullable, überschreibt Config), is_admin (bool) | Verlauf ja, Papierkorb nein (nur zwei Nutzer) |
| `passkeys` | wie `laravel/passkeys` | |
| `pairings` | user_a_id, user_b_id, status (requested/active), requested_by_id, token (für Einladungslink), accepted_at | unique (user_a, user_b), kleinerer Wert zuerst |
| `cities` | name, country_code, slug | unique (name, country_code) |
| `museums` | city_id, name, place_id (nullable, unique), website, lat, lng, address, research (json, nullable), researched_at | Papierkorb, Verlauf. `research` ist das Ergebnis der Museums-Recherche aus Etappe 3 |
| `exhibitions` | museum_id, title, starts_on, ends_on, source_url, fetched_at | |
| `artists` | name, sort_name, born_year, died_year, wikidata_id (nullable, unique), gnd_id (nullable), short_bio | Papierkorb, Verlauf |
| `epochs` | name, slug (unique), from_year, to_year, sort_order | Seeder mit Standard-Epochen |
| `artworks` | artist_id (nullable), epoch_id (nullable), museum_id (nullable), title, dating, technique, dimensions, inventory_number, facts (json), sources (json), wikidata_id (nullable) | Papierkorb ohne Kaskade auf captures (siehe unten), Verlauf. Index (museum_id, inventory_number) |
| `visits` | user_id, museum_id (nullable), city_id (nullable), lat, lng, accuracy_m, started_at, valid_until, shared_with_user_id (nullable), notes | Papierkorb mit Kaskade auf captures und city_tips, Verlauf. Index (user_id, valid_until) |
| `captures` | visit_id, user_id, artwork_id (nullable), status (uploaded/recognized/researched/done/failed), recognition (json: Titel, Künstler, Datierung, Sicherheit, Alternativen), confirmed_at, error_message, length (short/normal/long) | Papierkorb mit Kaskade auf capture_photos, audio_guides, fact_sheets; Verlauf. Index (visit_id, status) |
| `capture_photos` | capture_id, path, type (artwork/label/room_text), width, height, ocr_text, sort_order | Datei in `storage/app/private/captures/{capture}/` |
| `researches` | artwork_id, summary, sources (json: url, title, kind), existing_guides (json), model, input_tokens, output_tokens, cost_cents | Eine Recherche je Werk, wiederverwendbar |
| `audio_guides` | capture_id, script (json: Segmente mit role, text), audio_path (nullable), duration_seconds, word_count, model, tts_provider, tts_characters, input_tokens, output_tokens, cost_cents, feedback (up/down, nullable), difficulty_feedback (too_easy/fits/too_hard, nullable) | Papierkorb, Verlauf |
| `fact_sheets` | capture_id, key_facts (json), key_statements (json), guest_ideas (json: Einstiegssatz, Frage, Anekdote, Wien-Bezug), cross_references (json) | |
| `knowledge_items` | user_id, subject_type/subject_id (Artist, Epoch oder Thema als morph), capture_id, summary, told_at | Index (user_id, subject_type, subject_id) |
| `city_tips` | visit_id, title, kind (exhibition/church/building/event), url, reason, sort_order | |
| `ai_calls` | user_id (nullable), capture_id (nullable), purpose (recognize/research/script/check/museum/tips), model, input_tokens, output_tokens, cost_cents, duration_ms, succeeded (bool), error | Kostenprotokoll je Aufruf, Grundlage für die Monatsgrenze. Index (user_id, created_at) |
| `activity_log` | spatie | |

Entscheidungen im Datenmodell:

- **`Research` hängt am Werk, nicht an der Aufnahme.** Fotografieren Sebastian und Martha dasselbe Werk, gibt es eine Recherche und zwei Aufnahmen mit zwei Guides.
- **Papierkorb am Werk kaskadiert nicht auf Aufnahmen.** Ein Werk im Papierkorb lässt die Aufnahmen stehen (artwork_id bleibt), sonst verschwinden bei Martha Aufnahmen, weil Sebastian ein Werk aufräumt. Kaskaden laufen nur entlang Besuch > Aufnahme > Fotos/Guide/Fact Sheet.
- **Kosten doppelt**: je Aufruf in `ai_calls` (Wahrheit für Limits und Admin), je Guide zusammengefasst in `audio_guides` (Anzeige im Profil).
- **Sichtbarkeit**: Alles an `user_id` gebunden. Eine Policy `CapturePolicy` erlaubt dem gekoppelten Partner Lesen, wenn `visits.shared_with_user_id` passt. Werke, Künstler, Museen, Recherchen sind geteilt (keine `user_id`).
- **`visits.valid_until`** statt Ablauf berechnen: jede Aufnahme setzt `valid_until = now + 30 min`. Der aktive Besuch ist der mit `valid_until > now`, höchstens einer je Nutzer.
- `KnowledgeItem.subject` als Morph, damit später auch Themen oder Motive ohne neue Tabelle hineinpassen.

## 5. Reihenfolge der Arbeit in Etappe 1

1. Repo-Grundgerüst, Laravel installieren, Pakete, CI grün mit leerem Test
2. Auth, Nutzerbefehl, Admin-Panel mit Nutzern
3. Standards: Papierkorb, Verlauf, Datensicherungen
4. Migrationen, Models, Factories, Enums, Seeder (Epochen)
5. PWA-Hülle mit vier Reitern, Profil-Seite mit Vorwissen (einzige echte Eingabe in Etappe 1)
6. Redis, Queue, deploy.sh, systemd-Vorlage, `docs/betrieb.md`
7. Tests, Screenshot je Seite bei 390 px, Zusammenfassung, `CLAUDE.md` aktualisieren

Am Server (Sebastian oder Claude über Plesk-API, Schritt für Schritt in `docs/betrieb.md`): Subdomains anlegen, Datenbanken, `.env`, Cron für Deploy und Scheduler, systemd-Dienst, app-register, Basic-Auth auf Staging.

## 6. Offene Entscheidungen für Sebastian

- [ ] Name "Kunstbegleiter" und Repo `Kunstbegleiter-App` passen? (sonst umbenennen, bevor Code dazukommt)
- [ ] art.tourtool.app und art-staging.tourtool.app passen?
- [ ] Monatslimit je Nutzer (Vorschlag 30 EUR)
- [ ] Datei `claude/laravel-apps-betrieb.md` ins Repo legen, falls vorhanden
- [ ] OK für den Bau von Etappe 1
