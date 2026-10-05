# Prompts

Alle Prompts der KI-Pipeline liegen hier als Dateien (versioniert, nicht im Code verstreut, docs/grundgeruest.md). Platzhalter in doppelten geschweiften Klammern, z. B. `{{ museum }}`, werden von `App\Support\Prompts::render()` ersetzt (Arrays als JSON). Jede Datei ist der System-Prompt des jeweiligen Schritts; die Nutzer-Nachricht baut der Job aus den Daten.

| Datei | Schritt | Modell (config/museumguide.php) |
|---|---|---|
| `recognize.md` | Erkennung aus 1 bis 3 Fotos (OCR, Werk, Fototypen) | recognize |
| `research.md` | Recherche mit Websuche, Quellen je Aussage | research |
| `script.md` | Skript mit Sprecherrollen und Fact Sheet | script oder script_premium |
| `check.md` | Faktencheck des Skripts gegen die Recherche | check |
| `museum.md` | Kurze Recherche zum Museum beim Start eines Besuchs | museum |
| `knowledge.md` | Was erzählt wurde, in zwei Sätzen je Künstler und Epoche | small |
