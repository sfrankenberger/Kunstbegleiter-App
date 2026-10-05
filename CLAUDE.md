# Kunstbegleiter - Arbeitsanweisungen für Claude

Kunstbegleiter (Arbeitstitel, art.tourtool.app) erzeugt im Museum aus 1 bis 3 Fotos einen persönlichen deutschen Audioguide für Wiener Austria Guides. Nutzer: Sebastian und Martha. Eigene App, eigenes Repo, gleicher Betriebsstandard wie Tourtool und die Coaching-App.

## Vor jeder Arbeit lesen

1. `docs/grundgeruest.md` - fachliches Grundgerüst (Ziel, Datenmodell, Pipeline, Dramaturgie, Etappen)
2. `docs/konzept.md` - Entscheidungen und Projektstand (die Wahrheit für fachliche und technische Fragen)
3. `docs/funktionen.md` - Funktionsinventar (vor jeder Arbeit prüfen, nach jeder Arbeit ergänzen)
4. `docs/betrieb.md` - Server, Umgebungen, Einrichtung, lokale Entwicklung
5. `docs/plan-etappe-1.md` - der Plan, nach dem das Fundament gebaut wurde
6. `AGENTS.md` - Laravel-Richtlinien (Boost)

Wenn eine Entscheidung fällt: `docs/konzept.md` im selben Commit aktualisieren.

## Auftraggeber und Arbeitsweise

- Sebastian Frankenberger, kommuniziert auf Deutsch. Antworten und UI-Texte auf Deutsch (österreichisch), Code, Bezeichner und Commit-Messages auf Englisch.
- **Niemals den langen Gedankenstrich (Em-Dash) verwenden**, weder in Texten, UI, Doku noch Antworten.
- Erst prüfen, dann vorschlagen, dann bauen. Etappen (Grundgerüst, Abschnitt Etappen) erst nach Sebastians OK bauen.
- Alles über das Repo, am Server nichts von Hand ändern.
- **Bauphase (Sebastian, 05.10.2026: "direkt auf der App, kein Staging"):** Die App ist nur für Sebastian, nichts Verkauftes. Darum direkt auf `main` pushen, kein PR, kein Staging. CI läuft auf main und meldet Rot, dann sofort nachbessern. Lokal vor dem Push Pint und die Tests der geänderten Bereiche. Ausnahme: Migrationen, die bestehende Daten verändern oder löschen, vorher ankündigen. Die Site art-staging.tourtool.app bleibt bestehen, wird aber nicht mehr bespielt (Branch `staging` ruht).
- Pest-Tests für alles Neue. KI- und TTS-Aufrufe in Tests immer mit Fakes (`Http::fake()`), nie echte Kosten.
- Alle Prompts in `resources/prompts/`, alle Modellnamen und Limits in `config/museumguide.php`.
- KI-Antworten als JSON mit Schema anfordern und validieren, kaputte Antworten einmal neu anfragen.
- Oberfläche deutsch, mobil zuerst (390 px), auf dem iPhone in Safari testen.
- Am Ende jeder Etappe: kurze Zusammenfassung (gebaut, offen) und diese Datei aktualisieren.

## Stack

- Laravel 13, PHP 8.5, MariaDB (Tests mit SQLite: Indexnamen höchstens 64 Zeichen, verschlüsselte Casts nie auf JSON-Spalten)
- Filament 5 für `/admin`, Livewire + Alpine für die Handy-Oberfläche als PWA
- Tailwind über die Standalone-CLI (`bin/build-css`), gebautes CSS wird committed, kein Node am Server
- Redis (Instanz 6380, eigene DB und Präfix) für Cache, Session und Queue; Queue-Worker als systemd-Dienst
- Geldbeträge als Integer in Cent, Zeitzone Europe/Vienna

## Standards (wie Tourtool, docs/plan-etappe-1.md)

- Papierkorb: jedes Geschäftsdaten-Modell mit `SoftDeletes`, Eltern mit `CascadesSoftDeletes`; Resources mit `Trash::filter()`, `Trash::recordActions()`, `Trash::bulkActions()`
- Änderungsprotokoll: `LogsChanges` an jedem Geschäftsdaten-Modell, `ActivitiesRelationManager` an jeder Bearbeiten-Seite, Aufbewahrung 2 Jahre
- Datensicherungen: Seite Admin > Datensicherungen über `App\Support\Backup\BackupRestoreService`, nur mit `BACKUP_APP` in der `.env`
- Schlüssel und Token nie direkt aus `config()` lesen, immer über `App\Support\Secrets` (Admin vor `.env`)

## Server und Deployment

- Plesk-Server srv.iksf.de, Abo tourtool.app, Site art.tourtool.app (`main`). art-staging.tourtool.app (`staging`, Basic-Auth) ruht seit 05.10.2026.
- PHP-Binary `/opt/plesk/php/8.5/bin/php`, Composer `/opt/psa/var/modules/composer/composer.phar`
- `deploy.sh` per Cron jede Minute; `.env` nur am Server, nie committen
- Details in `docs/betrieb.md`

## Stand (04.10.2026)

- Etappe 1 (Fundament) gebaut: Auth mit Passkeys, PWA-Hülle mit vier Reitern, Admin (Nutzer, Datensicherungen), Papierkorb, Verlauf, Datenmodell mit 18 Tabellen, Schnittstellen für KI, TTS und Ort mit Fakes, CI, deploy.sh, systemd-Vorlage. 53 Tests.
- 05.10.2026: Etappe 1 auf `main`, Staging (art-staging.tourtool.app, Basic-Auth) und Live (art.tourtool.app) eingerichtet und deployt. Offen am Server: systemd-Dienste und app-register (Root, docs/betrieb.md Abschnitt 2). 
- 05.10.2026: Etappe 2 gebaut (Besuch mit Standort und Places, Museum von Hand, Fotos verkleinern und hochladen, Aufnahme-Seite mit Fototypen, signierte Fotoauslieferung). 71 Tests. Nächster Schritt: Etappe 3 (Pipeline).
- 05.10.2026: Etappe 3 gebaut (Pipeline aus Queue-Jobs: Erkennung mit Rückfrage, Recherche mit Websuche, Skript mit Fact Sheet, Faktencheck, ElevenLabs-Stimme, Lerngedächtnis, Museumsrecherche; Seite Aufnahme mit Fortschritt, Player und Rückmeldung; Kosten je Aufruf). 87 Tests. Details docs/konzept.md Abschnitt 10. Dazu zwei Stufen je Aufnahme: schnell (ein Aufruf, Handy liest vor) und ausführlich (volle Kette), docs/konzept.md Abschnitt 11. 89 Tests. Offen: Schlüssel eintragen, Stimmen-IDs.
- 05.10.2026: Seite Admin > Einstellungen > Zugänge: API-Schlüssel verschlüsselt in der Datenbank (`App\Support\Secrets`), `.env` nur Rückfall, Anbieter `auto`.
- Lokal PHP 8.5 nötig (php.new), Tests mit `vendor/bin/pest`, Stil mit `vendor/bin/pint`, CSS mit `bin/build-css`.

## Commits

- Aussagekräftige englische Commit-Messages, kleine thematische Commits.
