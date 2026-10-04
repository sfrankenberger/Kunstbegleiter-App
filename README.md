# Kunstbegleiter

Museums-Audioguide-App (Arbeitstitel) für Wiener Austria Guides: aus 1 bis 3 Fotos entsteht ein persönlicher deutscher Audioguide. Läuft unter art.tourtool.app (Staging: art-staging.tourtool.app).

Einstieg: `CLAUDE.md`, dann `docs/grundgeruest.md`, `docs/konzept.md`, `docs/funktionen.md` und `docs/betrieb.md`.

## Lokal starten

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan kunst:user du@example.com --name="Du" --admin   # gibt das Passwort aus
php artisan serve
```

PHP 8.5 wird gebraucht (`curl -fsSL https://php.new/install/linux/8.5 | bash`). Tests: `vendor/bin/pest`. Stil: `vendor/bin/pint`. CSS der Handy-Oberfläche: `npm install` einmal, dann `bin/build-css`.
