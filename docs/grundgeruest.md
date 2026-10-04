# Museums-Audioguide-App - Grundgerüst für Claude Code

Oct 3, 2026 · @Sebastian Frankenberger

## Welches Claude-Modell

Empfehlung: Claude Fable 5.1 für Planung, Architektur und die KI-Pipeline, Claude Sonnet 5.5 für die laufende Umsetzung.

| Phase | Modell in Claude Code | Warum |
| --- | --- | --- |
| Code lesen, Plan, Datenmodell, Etappe 1 | Fable 5.1 | Stärkstes Modell, macht weniger Architekturfehler, die man später teuer umbaut |
| KI-Pipeline, Prompts, Audio-Dramaturgie (Etappe 3) | Fable 5.1 | Hier entscheidet sich die Qualität der Audioguides |
| Routine: Filament-Seiten, Tests, Archiv, Styling | Sonnet 5.5 | Schneller, verbraucht weniger vom Nutzungskontingent, für Standardarbeit völlig ausreichend |
| Fehlersuche, wenn Sonnet zweimal hängt | Fable 5.1 | Umschalten mit `/model` |

In der App selbst (API-Aufrufe vom Server):

| Aufgabe | Modell | API-String |
| --- | --- | --- |
| Bilderkennung, Recherche mit Websuche, Skript schreiben | Sonnet 5.5 (Standard) | `claude-sonnet-5-5` |
| Optional: Skript in Premium-Qualität | Opus 5.5 | `claude-opus-5-5` |
| Kleinkram: Museum aus Ort ableiten, Tags, Epoche zuordnen | Haiku 4.5 | `claude-haiku-4-5-20251001` |

Modellname je Aufgabe in `config/museumguide.php`, damit man ohne Code-Änderung wechseln kann. Claude kann nicht sprechen: Für die Stimmen braucht es einen eigenen Text-to-Speech-Dienst (siehe Ablauf).

## Ziel und Plattform

Die App erzeugt im Museum auf Knopfdruck einen persönlichen deutschen Audioguide (1 bis 4 Minuten) aus 1 bis 3 Fotos, zugeschnitten auf Wiener Austria Guides mit Vorwissen.

- **Nutzer:** Sebastian und Martha, je ein eigener Account mit eigener Lernentwicklung. Accounts lassen sich koppeln für gemeinsame Museumsbesuche.
- **Lernziel:** Künstler und Epochen besser verstehen, Vergleiche ziehen, Ideen bekommen, wie man ein Werk Gästen nahebringt und einen roten Faden durch ein Museum legt.
- **Arbeitstitel:** "Kunstbegleiter" (Name offen). Domain offen, z.B. eine Subdomain auf srv.iksf.de.

**Plattform: PWA, keine native iOS-App.** Kamera, GPS und Audio-Wiedergabe funktionieren in einer PWA auf dem iPhone. Kein App-Store, kein Apple-Developer-Konto, Updates kommen über den normalen Deploy. Grenzen der PWA: Audio im Hintergrund bei gesperrtem Bildschirm ist unzuverlässig, die Standort-Freigabe fragt Safari öfter neu ab. Eine native Hülle (z.B. Capacitor) kann man später darüberlegen, falls das stört. Das Backend bleibt dann gleich.

## Technik-Stack und Betrieb

Aktuelles Laravel auf srv.iksf.de, gleicher Standard wie tourtool und Lea.

| Baustein | Wahl |
| --- | --- |
| Backend | Laravel (aktuelle Version), PHP 8.5, MariaDB |
| Mobile Oberfläche | Livewire + Alpine, mobil zuerst, als PWA (Manifest, Service Worker, Home-Bildschirm-Icon) |
| Verwaltung | Filament-Panel für Admin (Nutzer, Kosten, Prompts, Datensicherungen) |
| Hintergrundjobs | Laravel Queue über Redis, Worker als systemd-Dienst |
| Live-Fortschritt | Reverb (Websocket) oder einfaches Polling, "Erkenne Werk... Recherchiere... Spreche ein..." |
| KI | Anthropic API (Vision, Websuche-Tool, Textgenerierung), Schlüssel nur in der `.env` |
| Stimmen | ElevenLabs (mehrere deutsche Stimmen, Dialog-Modus mit Sprecherwechsel). Alternative: OpenAI TTS oder Google Cloud TTS. Anbieter hinter einem Interface `TtsProvider`, austauschbar |
| Ort | Browser-Geolocation + Google Places API (Museum in der Nähe finden) |
| Dateien | Fotos und MP3 in `storage/app/private`, Auslieferung nur über signierte URLs |

Betriebsstandard (aus `claude/laravel-apps-betrieb.md`):

1. Eigene Redis-DBs 6/7 auf Instanz 6380 mit eigenem Präfix, Cache, Session und Queue über Redis.
2. Queue-Worker als systemd-Dienst nach Vorlage `tourtool-queue-*.service`, Neustart nach Deploy.
3. `app-register add ...` für Backup und Wiederherstellung, dann `BACKUP_APP=<name>` in die `.env`.
4. Im Repo: Seite "Datensicherungen", Papierkorb (SoftDeletes) und Änderungsprotokoll nach den bestehenden Anleitungen, gleiche Klassennamen.
5. Produktion auf `main`, Staging auf `staging`. Auf dem Server nichts von Hand ändern.
6. Wichtig: Fotos und MP3s liegen in `storage/app` und sind damit im stündlichen restic-Backup.

## Datenmodell

Kern ist die Trennung zwischen dem Kunstwerk (einmal, geteilt) und dem persönlichen Erlebnis (pro Nutzer).

| Modell | Wichtige Felder | Zweck |
| --- | --- | --- |
| `User` | Name, E-Mail, Passwort, Vorwissen-Profil (Freitext + Schwerpunkte), Stimme-Vorlieben | Eigener Account |
| `Pairing` | user\_a, user\_b, Status (angefragt/aktiv) | Kopplung Sebastian und Martha |
| `Visit` | user, Museum, Stadt, lat/lng, Genauigkeit, gestartet\_um, gültig\_bis (+30 Min, verlängert sich bei jeder Aufnahme), shared\_with (gekoppelter Nutzer) | Der Museumsbesuch als Kontext |
| `City` | Name, Land | Archiv-Gruppierung |
| `Museum` | Name, Stadt, Place-ID, Website, Koordinaten | Archiv-Gruppierung, Recherche-Startpunkt |
| `Exhibition` | Museum, Titel, Zeitraum, Quelle, abgerufen\_am | Sonderausstellung oder Sammlung |
| `Artist` | Name, Lebensdaten, Normdaten-ID (Wikidata/GND), Kurzbio | Archiv, Wiederholungen erkennen |
| `Epoch` | Name, Zeitraum | Archiv nach Epochen |
| `Artwork` | Titel, Artist, Datierung, Technik, Maße, Museum, Inventarnummer, Epoche, Fakten-JSON, Quellen | Das Werk, einmal angelegt |
| `Capture` | Visit, user, Artwork, Status (hochgeladen/erkannt/recherchiert/fertig/fehler) | Eine Analyse aus 1 bis 3 Fotos |
| `CapturePhoto` | Capture, Datei, Typ (Werk/Werktext/Raumtext), OCR-Text | Die einzelnen Fotos |
| `Research` | Artwork, Quellen-Liste (URL, Titel, Art), Zusammenfassung, gefundene Audioguides/Literatur | Recherche, wiederverwendbar |
| `AudioGuide` | Capture, Skript (Segmente mit Sprecher), MP3, Dauer, Modell, Kosten | Das Hörstück |
| `FactSheet` | Capture, Kurzfakten, Kernaussagen, "Für deine Gäste"-Ideen | Bildschirm-Zusammenfassung |
| `KnowledgeItem` | user, Artist/Epoch/Thema, was schon erzählt wurde, Datum | Lerngedächtnis, verhindert Wiederholungen |
| `CityTip` | Visit, Titel, Art (Ausstellung, Kirche, Event), Link, Begründung | Weitere Tipps in der Stadt |

Papierkorb und Verlauf für `Capture`, `AudioGuide`, `Artwork`, `Artist`, `Museum`, `Visit`.

## Ablauf: Besuch, Fotos, Pipeline

Ein Besuch startet mit dem Standort, jede Aufnahme läuft als Kette von Queue-Jobs, das Ergebnis ist nach etwa 1 bis 2 Minuten hörbar.

**Beim Öffnen der App**

1. GPS abfragen, Ort für 30 Minuten als aktiven `Visit` speichern. Jede neue Aufnahme verlängert um 30 Minuten.
2. Museum bestimmen: Google Places in der Nähe, bei mehreren Treffern Auswahl antippen. Manuelle Korrektur immer möglich ("Ich bin im ...").
3. Hintergrundjob: kurze Recherche zum Museum (laufende Sonderausstellungen, Sammlungsschwerpunkte, Online-Sammlung, offizieller Audioguide). Ergebnis am Visit speichern, für alle Aufnahmen wiederverwenden.
4. Ist Martha gekoppelt und in der Nähe, Frage: "Gemeinsamer Besuch?"

**Aufnahme**

1. Großer Kamera-Knopf. 1 bis 3 Fotos sammeln (Werk, Werktext, Raumtext), Typ automatisch erkennen lassen, korrigierbar. Dann "Audioguide erstellen".
2. Fotos vor dem Upload im Browser verkleinern (lange Kante ca. 2000 px).

**Pipeline (Queue-Jobs nacheinander)**

1. `RecognizeArtwork`: Claude Vision liest Texte (OCR) und erkennt das Werk. Mit Museum und Ausstellung als Kontext. Ausgabe als JSON: Titel, Künstler, Datierung, Sicherheit (0 bis 1), Alternativen. Unter 0,7 Sicherheit: Nutzer bestätigt oder wählt aus.
2. Abgleich: Gibt es das `Artwork` schon (auch bei Martha)? Dann Recherche wiederverwenden.
3. `ResearchArtwork`: Claude mit Websuche. Reihenfolge: Online-Sammlung des Museums, Ausstellungstexte, Werkverzeichnis, Wikidata, Fachliteratur, vorhandene Audioguides und Kuratoren-Aussagen. Jede Aussage mit Quelle speichern.
4. `BuildContext`: Vorwissen-Profil, bisherige Werke im Archiv, Werke dieses Besuchs (auch des gekoppelten Nutzers), `KnowledgeItem`s zu Künstler und Epoche.
5. `WriteScript`: Skript als JSON-Segmente mit Sprecherrolle, Ziellänge 200 bis 550 Wörter (ca. 1,5 bis 4 Minuten). Dazu `FactSheet`.
6. `CheckFacts`: zweiter, kurzer Aufruf prüft Skript gegen die Recherche. Nicht belegte Fakten und Zitate werden entfernt oder als Deutung markiert.
7. `SynthesizeAudio`: TTS je Segment bzw. als Dialog, zu einer MP3 zusammenfügen.
8. `UpdateKnowledge`: was erzählt wurde, in `KnowledgeItem` eintragen. Fertig-Meldung an die Oberfläche.

Fehler in einem Schritt: drei Wiederholungen, dann klare Meldung mit "Erneut versuchen". Text und Fakten werden angezeigt, auch wenn nur die Stimme scheitert.

## Audioguide: Dramaturgie, Stimmen, Zitate

Jeder Guide folgt einem festen Bogen, klingt aber wie ein professionell produzierter Museums-Audioguide, nicht wie ein vorgelesener Lexikonartikel.

**Aufbau (Richtwerte bei 3 Minuten)**

1. Einstieg mit Haken, dann Titel, Künstler, Jahr (15 s)
2. Genau hinschauen: kurze Objektbeschreibung, Blick führen ("Schauen Sie links unten...") (30 s)
3. Entstehung und Provenienz: Auftrag, Umstände, Weg ins Museum (30 s)
4. Der Künstler: nur was für dieses Werk zählt, Stellung im Lebenswerk (30 s)
5. Einordnung: Kunstgeschichte und Zeitgeschichte, was passierte gleichzeitig (30 s)
6. Deutung: warum wichtig, mögliche Lesarten, Bezug zu vorher gesehenen Werken (30 s)
7. Ausklang mit einer Beobachtungsfrage oder einem Satz zum Mitnehmen (15 s)

**Sprecherrollen**

| Rolle | Einsatz |
| --- | --- |
| Erzähler/in | Trägt den Guide, warm, lebendig |
| Zweite Stimme | Kontrapunkt, Nachfrage, Kontext ("Und was hat das mit Wien zu tun?") |
| Zitat-Stimme | Liest belegte Zitate des Künstlers, von Zeitgenossen oder Kuratoren |

**Regeln für Zitate (Muss)**

- Nur echte, in der Recherche mit Quelle belegte Zitate. Nie ein Zitat erfinden oder einer Person in den Mund legen.
- Im Audio angesagt: "Der Maler schrieb 1889 an seinen Bruder: ...". Fremdsprachige Zitate übersetzt und als Übersetzung erkennbar.
- Kurz halten (1 bis 2 Sätze). Kurator-Aussagen nur aus veröffentlichten Quellen, nie als erfundenes Interview.
- Keine Stimmenklone realer Personen. Die Zitat-Stimme ist eine neutrale Sprecherstimme.
- Gibt es kein belegtes Zitat, wird erzählt statt zitiert.

**Ton für die Zielgruppe:** Ansprache auf Augenhöhe mit erfahrenen Guides. Basiswissen nicht erklären, dafür Querverbindungen nach Wien (Belvedere, KHM, Albertina, Leopold) und zu bereits gesehenen Werken. Immer Deutsch, unabhängig von der Sprache der Fotos.

Die Prompts liegen als Dateien in `resources/prompts/` (versioniert), nicht im Code verstreut.

## Lernen, Vorwissen, gemeinsamer Besuch

Die App merkt sich pro Nutzer, was schon erzählt wurde, und baut darauf auf statt zu wiederholen.

- **Im selben Besuch:** Zweites Werk desselben Künstlers bekommt keine neue Biografie, sondern "Im Vergleich zu dem Bild von vorhin...". Ab dem dritten Werk eine kurze Brücke: Was verbindet die Werke, welche These ergibt sich für den Rundgang.
- **Über Besuche hinweg:** Bezüge zu früher gesehenen Werken ("Wie bei Klimts Beethovenfries, den du im März fotografiert hast..."). Auswahl über Künstler, Epoche, Motiv, Technik. Maximal zwei Rückbezüge pro Guide, damit es nicht ermüdet.
- **Vorwissen-Profil:** Einmal ausfüllen (z.B. "Austria Guide, Schwerpunkt Wien um 1900, Barock gut, Gegenwartskunst weniger"). Wird bei jedem Skript mitgegeben.
- **Rückmeldung:** Daumen hoch/runter und "zu leicht / passt / zu schwer" nach jedem Guide. Fließt in das Profil ein.
- **Länge wählbar:** Kurz (ca. 1,5 Min), Normal (ca. 3 Min), Ausführlich (bis 4 Min).

**Gemeinsamer Besuch (Kopplung)**

- Kopplung einmalig per Einladung, jederzeit lösbar.
- Gemeinsamer Besuch: Beide sehen die Aufnahmen des anderen in diesem Besuch, der Guide bezieht beide ein.
- Jeder behält seinen eigenen Guide-Text und sein eigenes Lerngedächtnis. Ist Martha bei einem Künstler schon weiter, bekommt sie die vertiefte Fassung.
- Ein Werk, das beide fotografieren, wird nur einmal erkannt und recherchiert (spart Kosten).
- Außerhalb gemeinsamer Besuche sieht keiner das Archiv des anderen, außer er teilt einzelne Einträge.

## Bildschirme

Vier Reiter unten, bedienbar mit einer Hand im Museum.

| Reiter | Inhalt |
| --- | --- |
| Jetzt | Aktueller Besuch (Museum, Restzeit), Kamera-Knopf, Fortschritt der laufenden Analyse, Liste der Werke dieses Besuchs |
| Archiv | Filter nach Künstler, Stadt, Museum, Epoche, Datum, Partner; Volltextsuche; Zeitleiste |
| Entdecken | Tipps in der Stadt, Rundgang-Ideen, Vergleiche |
| Profil | Vorwissen, Länge, Stimmen, Kopplung, Kosten-Übersicht |

**Werk-Seite:** Player oben (Play, 15 s zurück, Geschwindigkeit 0,8 bis 1,5), darunter das Fact Sheet: Titel, Künstler, Datierung, Technik, Maße, Standort im Museum, 3 bis 5 Kernaussagen, Querverweise auf eigene Werke, Quellenliste mit Links, Skript zum Mitlesen. Fotos ausklappbar.

**"Für deine Gäste" (eigener Block auf der Werk-Seite, nicht im Audio):**

- Ein Einstiegssatz, mit dem man vor dem Werk eine Gruppe abholt
- Eine Frage an die Gäste
- Eine Anekdote, die hängen bleibt (belegt)
- Bezug zu einem Werk in Wien, das du bei eigenen Führungen aufgreifen kannst

**Entdecken: Stadt-Tipps** (aufpoppend nach dem Besuch oder auf Knopfdruck, nie im Audioguide): laufende Ausstellungen, nahe Kirchen oder Bauten mit Bezug zu den gesehenen Werken, Events. Jeder Tipp mit Begründung ("weil du heute drei Caravaggisten gesehen hast") und Link. Einmal pro Besuch recherchiert, gecacht.

**Entdecken: Rundgang-Ideen:** Aus den Werken eines Besuchs oder einer Auswahl im Archiv erzeugt die App einen roten Faden: Reihenfolge, These, Übergänge zwischen den Stationen, Zeitbedarf. Export als PDF oder Text für die eigene Tourvorbereitung.

## Datenschutz, Kosten, Urheberrecht

Alle Schlüssel und KI-Aufrufe bleiben auf dem Server, jede Aufnahme protokolliert ihre Kosten.

- **Kosten:** Pro Aufnahme Tokens (Anthropic) und Zeichen (TTS) in `AudioGuide` speichern, Übersicht im Profil und im Admin. Monatslimit pro Nutzer in der Config, ab 80 % Hinweis. Echte Preise beim Bau aktuell nachschlagen, nicht aus dem Gedächtnis eintragen. Recherche und Erkennung pro Werk wiederverwenden.
- **Standort:** Nur während eines aktiven Besuchs genutzt, im Archiv nur Museum und Stadt, keine Bewegungsspur.
- **Fotos:** Privat, nur für den Besitzer (und im gemeinsamen Besuch für den Partner). Nicht öffentlich verlinkbar.
- **Urheberrecht:** Die App ist privat und zum Lernen. Sie fasst Recherche in eigenen Worten zusammen und kopiert keine Texte von Museumsseiten oder fremden Audioguides. Zitate kurz und mit Quelle. Fotos von Werken bleiben privat und werden nicht veröffentlicht. Vorhandene Audioguides werden verlinkt und empfohlen, nicht nachgebaut.
- **Login:** E-Mail + Passwort, optional Passkey. Rate-Limit auf die teuren Endpunkte.
- **Mails:** Einladung zur Kopplung braucht echten Mailversand (bei tourtool steht Produktion noch auf `MAIL_MAILER=log`, hier gleich richtig einrichten).

## Etappen und Arbeitsweise für Claude Code

In fünf Etappen bauen, jede auf Staging testbar, erst nach OK nach `main`.

| Etappe | Inhalt | Modell |
| --- | --- | --- |
| 1 Fundament | Laravel-Projekt, Auth, PWA-Hülle, Redis, Queue-Dienst, Datensicherung/Papierkorb/Verlauf nach Standard, Datenmodell und Migrationen | Fable 5.1 |
| 2 Besuch und Kamera | GPS, Museum über Places, Visit 30 Min, Fotos aufnehmen und hochladen | Sonnet 5.5 |
| 3 KI-Pipeline | Erkennung, Recherche, Skript, Faktencheck, Fact Sheet, vorerst Audio mit einer Stimme | Fable 5.1 |
| 4 Stimmen und Lernen | Mehrere Sprecher, Lerngedächtnis, Rückbezüge, Archiv mit Filtern | Sonnet 5.5, Prompts mit Fable |
| 5 Zu zweit und Entdecken | Kopplung, gemeinsamer Besuch, Stadt-Tipps, Rundgang-Ideen, Export | Sonnet 5.5 |

**Anweisungen an Claude Code (so in den ersten Prompt übernehmen)**

1. Lies dieses Dokument und `claude/laravel-apps-betrieb.md`. Zeig mir einen Plan für Etappe 1 inklusive Datenmodell, erst nach meinem OK bauen.
2. Alles über das Repo, auf dem Server nichts von Hand ändern. Erst `staging`, dann `main`.
3. Pest-Tests für alles Neue. KI- und TTS-Aufrufe in Tests immer mit Fakes (`Http::fake()`), nie echte Kosten.
4. Alle Prompts in `resources/prompts/`, alle Modellnamen und Limits in `config/museumguide.php`.
5. KI-Antworten als JSON mit Schema anfordern und validieren, kaputte Antworten einmal neu anfragen.
6. Oberfläche deutsch, mobil zuerst, auf dem iPhone in Safari testen.
7. Kein langer Gedankenstrich in Texten und Oberfläche.
8. Am Ende jeder Etappe: kurze Zusammenfassung, was gebaut ist und was offen ist, und `CLAUDE.md` aktualisieren.

**Offene Fragen vor dem Start**

- [ ] Name und Domain der App
- [ ] TTS-Anbieter: ElevenLabs testen (Stimmenqualität Deutsch, Dialog-Modus) oder günstiger starten
- [ ] Google-Places-API-Schlüssel anlegen
- [ ] Monatslimit für KI-Kosten pro Nutzer festlegen
- [ ] Martha als zweite Testerin ab Etappe 3
