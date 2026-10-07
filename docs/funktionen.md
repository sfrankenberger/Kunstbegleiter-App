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
| Reiter Jetzt (Besuch starten, Museum wählen oder eintippen, Fotos aufnehmen und hochladen, Werke des Besuchs) | `App\Livewire\Pages\Jetzt`, `resources/views/livewire/pages/jetzt.blade.php` (Alpine: Geolocation, Bild verkleinern) |
| Fotos sammeln auf Jetzt (Kamera liefert je Auslösung ein Foto, Vorschau mit Entfernen, bis `museumguide.photos.max_per_capture`) | Alpine-Block in `resources/views/livewire/pages/jetzt.blade.php` (`pick`, `remove`, `sync`) |
| Aufnahme-Seite (Fotos, Typ je Foto, Löschen) | `App\Livewire\Pages\Aufnahme`, Route `aufnahme` |
| Sprechtext für die Stimme (Jahreszahlen, Daten, Jahrhunderte, Jahrzehnte deutsch ausgeschrieben, Abkürzungen aufgelöst) | `App\Services\Tts\SpeechText::normalize`, aufgerufen in `App\Services\Pipeline\Voice::synthesize` |
| Titelbox der Detailseiten bleibt beim Scrollen unter der Kopfzeile stehen (Werk/Ort und Künstler) | Klasse `kb-title-card` in `resources/css/app.css`, genutzt in `aufnahme.blade.php` und `kuenstler-detail.blade.php` |
| Fotos ausliefern (signiert, nur mit Sicht) | `App\Http\Controllers\PhotoController`, `CapturePhoto::url()`, Route `fotos.show` |
| Reiter Archiv (fertige Aufnahmen, Suche) | `App\Livewire\Pages\Archiv` (`?q=`) |
| Reiter Entdecken (Hülle) | `App\Livewire\Pages\Entdecken` |
| Reiter Profil (Vorwissen, Länge, Kosten, Passkeys, Abmelden) | `App\Livewire\Pages\Profil` |
| CSS bauen | `bin/build-css`, `resources/css/app.css`, Ergebnis `public/css/app.css` (eingecheckt) |

## Admin

| Funktion | Wo |
|---|---|
| Nutzer (Konto, Vorwissen, Limit, Kosten des Monats, Verlauf) | `App\Filament\Resources\Users\UserResource` |
| Datensicherungen | `App\Filament\Pages\Backups`, `App\Support\Backup\BackupRestoreService`, `config/backup-restore.php` |
| Zugänge (API-Schlüssel verschlüsselt, Herkunft, Prüfen) | `App\Filament\Pages\Zugaenge`, `App\Support\Secrets`, Modell `App\Models\Setting`, Tabelle `settings` |
| Papierkorb-Bausteine | `App\Filament\Support\Trash` (filter, recordActions, bulkActions, query) |
| Verlauf-Tab | `App\Filament\Support\ActivitiesRelationManager` |

## Daten und Standards

| Funktion | Wo |
|---|---|
| Papierkorb mit Kindern | `App\Models\Concerns\CascadesSoftDeletes` (`$softCascades`) |
| Änderungsprotokoll | `App\Models\Concerns\LogsChanges` (`changeLog()`), `activitylog:clean` in `routes/console.php` |
| Aktiver Besuch, verlängern | `User::activeVisit()`, `Visit::extend()`, `Visit::remainingMinutes()`, `Visit::scopeActive()` |
| Besuch starten, Museum aus Places oder von Hand, beenden, Stadt aus Adresse | `App\Services\Visits\VisitService` |
| Aufnahme anlegen (Fotos speichern, Besuch verlängern, Pipeline starten), endgültig entfernen | `App\Services\Captures\CaptureService` |
| Pipeline starten, nach Bestätigung fortsetzen (schnell oder ausführlich), ausführlich nachbestellen, erneut versuchen, Monatslimit prüfen | `App\Services\Pipeline\Pipeline` |
| Schnellstufe (ein Vision-Aufruf: Erkennung, Kurztext, Fact Sheet; Handy liest satzweise vor) | `App\Jobs\QuickGuide`, `App\Jobs\QuickOverview` (nach Rückfrage), `resources/prompts/quick.md`, `quick-text.md`, Browser-Stimme in `resources/views/livewire/pages/aufnahme.blade.php` |
| Werk und Künstler zuordnen oder anlegen | `App\Services\Pipeline\ArtworkMatcher` |
| Kontext für das Skript (Vorwissen, Besuch, Lerngedächtnis, Quellen) | `App\Services\Pipeline\ContextBuilder` |
| JSON-Schemas der KI-Antworten | `App\Services\Pipeline\Schemas` |
| Pipeline-Schritte (Jobs) | `App\Jobs\{QuickGuide, QuickOverview, RecognizeArtwork, ResearchAndWrite, SynthesizeAudio, UpdateKnowledge, ResearchMuseum}`, Basis `App\Jobs\PipelineJob` |
| Skript einsprechen und MP3 ablegen (schnell eine Stimme, ausführlich zwei Sprecher) | `App\Services\Pipeline\Voice` |
| Segmente verketten, Musikbett mischen (ffmpeg) | `App\Services\Pipeline\AudioMixer` |
| Musik je Epoche wählen | `App\Services\Pipeline\MusicBed`, Dateien `storage/app/music/`, Download `bin/fetch-music` |
| Queue-Worker sofort anstoßen | `App\Support\QueueKick` |
| Fortschritt, Rückfrage, Player, Fact Sheet, Rückmeldung | `App\Livewire\Pages\Aufnahme`, `Capture::isRunning()`, `Capture::progressLabel()` |
| Künstlerprofil (Leben, Werke, Stil, Rezeption, einmal je Künstler) | `App\Jobs\ProfileArtist`, `resources/prompts/artist.md`, `artists.profile` |
| Bilder von Wikidata und Commons (Portrait, Werk, Vergleichswerke) | `App\Services\Images\WikiImages`, `App\Jobs\FetchImages`, `App\Models\RelatedWork` |
| Icons im Fact Sheet | `resources/views/components/kb-icon.blade.php` (`<x-kb-icon name="user" />`) |
| Reiter Stadt (Orte in der Nähe, Ort eingeben, Foto), Ort-Guide | `App\Livewire\Pages\Stadt`, `App\Services\Places\PlaceFinder` (Wikidata, OpenStreetMap, Google), `WikiPlaces`, `OverpassPlaces`, `Geocoder` (Stadt aus Position), `App\Services\Pipeline\PlaceMatcher`, `App\Models\Place`, `CaptureService::createForPlace`, `createForPlacePhoto`, Prompts `place-quick.md`, `place-quick-text.md`, `place-guide.md` |
| Reiter Künstler, Künstler-Seite | `App\Livewire\Pages\Kuenstler`, `App\Livewire\Pages\KuenstlerDetail` |
| Globaler Audio-Player (läuft über Seitenwechsel) | `public/js/player.js` (Alpine-Store), `@persist('player')` in `resources/views/components/layouts/app.blade.php` |
| Audio ausliefern (signiert) | `App\Http\Controllers\AudioController`, `AudioGuide::url()`, Route `audio.show` |
| Sicht auf Aufnahmen (Besitzer, Partner) | `App\Policies\CapturePolicy` |
| Kopplung, Partner | `Pairing::between()`, `Pairing::partnerOf()`, `User::partner()` |
| Kosten je Nutzer und Monat | `AiCall::monthCents()`, `User::monthlyLimitCents()` |
| Epochen | `Database\Seeders\EpochSeeder` (läuft bei jedem Deploy) |
| Enums | `App\Enums\{CaptureStatus, PipelineStep, PhotoType, PairingStatus, GuideLength, GuideMode, TipKind, AiPurpose}` |

## KI, Stimmen, Ort

| Funktion | Wo |
|---|---|
| Anthropic-Aufruf (Text, JSON nach Schema, Websuche, Rückfall, Kostenprotokoll) | `App\Services\Ai\ClaudeClient`, `App\Services\Ai\Pricing`, `config/museumguide.php` |
| Text-zu-Sprache | `App\Contracts\TtsProvider`, `App\Contracts\TtsResult`, `App\Services\Tts\{FakeTtsProvider, ElevenLabsTtsProvider}` |
| Museum in der Nähe | `App\Contracts\PlacesClient`, `App\Services\Places\FakePlacesClient`, `App\Services\Places\GooglePlacesClient` (Places API New) |
| Prompts | `resources/prompts/*.md`, Platzhalter über `App\Support\Prompts::render` |
