# Betrieb: Server, Umgebungen, Einrichtung

Stand: 04.10.2026. Keine Passwörter in dieser Datei, die stehen nur in den `.env`-Dateien am Server. Vorlage: Tourtool `docs/betrieb.md`.

## 1. Umgebungen

| | Live | Staging |
|---|---|---|
| URL | https://art.tourtool.app | https://art-staging.tourtool.app (Basic-Auth) |
| Admin | https://art.tourtool.app/admin | https://art-staging.tourtool.app/admin |
| Branch | `main` | `staging` |
| Verzeichnis | `/var/www/vhosts/tourtool.app/art.tourtool.app` | `/var/www/vhosts/tourtool.app/art-staging.tourtool.app` |
| Document Root | `.../art.tourtool.app/public` | `.../art-staging.tourtool.app/public` |
| Datenbank | `kunst` (Benutzer `kunst_app`) | `kunst_staging` (Benutzer `kunst_stg`) |
| Redis | 127.0.0.1:6380, DB 6, Präfix `kunst_` | 127.0.0.1:6380, DB 7, Präfix `kunst_stg_` |
| Queue-Dienst | `kunst-queue.service` | `kunst-staging-queue.service` |
| Backup-App | `BACKUP_APP=kunst-prod` | `BACKUP_APP=kunst-staging` |
| Deploy-Log | `~/logs/deploy-kunst.log` | `~/logs/deploy-kunst-staging.log` |

Beide Sites laufen im Plesk-Abo `tourtool.app` auf srv.iksf.de, PHP 8.5 (FPM), Composer `/opt/psa/var/modules/composer/composer.phar`, DNS bei Cloudflare.

## 2. Einrichtung (einmalig, Staging zuerst)

1. **Plesk:** Websites und Domains > tourtool.app > Subdomain hinzufügen `art-staging` (später `art`), Document Root `art-staging.tourtool.app/public`, PHP 8.5 FPM. Staging: Passwortgeschützte Verzeichnisse auf `/`. Zertifikat über Let's Encrypt in Plesk.
2. **DNS (Cloudflare, Zone tourtool.app):** `art-staging` und `art` als A auf 85.214.17.182 (Proxy an; zum ersten Ausstellen des Zertifikats kurz aus).
3. **Datenbank:** in Plesk `kunst_staging` mit Benutzer `kunst_stg` anlegen (live `kunst` / `kunst_app`).
4. **Code:** als Abo-Benutzer per SSH
   ```bash
   cd /var/www/vhosts/tourtool.app
   git clone -b staging git@github.com:sfrankenberger/Kunstbegleiter-App.git art-staging.tourtool.app
   cd art-staging.tourtool.app
   cp .env.example .env   # dann ausfüllen (Abschnitt 3)
   /opt/plesk/php/8.5/bin/php /opt/psa/var/modules/composer/composer.phar install --no-dev --optimize-autoloader
   /opt/plesk/php/8.5/bin/php artisan key:generate
   /opt/plesk/php/8.5/bin/php artisan migrate --force && /opt/plesk/php/8.5/bin/php artisan db:seed --force
   /opt/plesk/php/8.5/bin/php artisan kunst:user mail@sfrankenberger.com --name=Sebastian --admin
   /opt/plesk/php/8.5/bin/php artisan optimize
   ```
5. **Cron (Abo-Benutzer, Shell `/bin/bash`):**
   ```
   * * * * * APP_DIR=/var/www/vhosts/tourtool.app/art-staging.tourtool.app BRANCH=staging QUEUE_SERVICE=kunst-staging-queue SEED_AFTER_MIGRATE=1 /var/www/vhosts/tourtool.app/art-staging.tourtool.app/deploy.sh >> /var/www/vhosts/tourtool.app/logs/deploy-kunst-staging.log 2>&1
   * * * * * cd /var/www/vhosts/tourtool.app/art-staging.tourtool.app && /opt/plesk/php/8.5/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
6. **Queue-Worker (root):** `deploy/kunst-queue.service` nach `/etc/systemd/system/kunst-staging-queue.service` kopieren, `WorkingDirectory` und Log-Pfad auf `art-staging.tourtool.app` ändern, `systemctl daemon-reload && systemctl enable --now kunst-staging-queue`. Sudoers aus `deploy/sudoers-kunst` nach `/etc/sudoers.d/kunst` (Rechte 0440).
7. **Backup (root):** `app-register add kunst-staging ...` (Verzeichnis und Datenbank), dann `BACKUP_APP=kunst-staging` in die `.env`. Wiederherstellungen werden nur auf Staging geprobt.
8. **Prüfen:** `curl -sI https://art-staging.tourtool.app/up` antwortet 200, `/anmelden` zeigt die Anmeldung, `/manifest.webmanifest` den Namen Kunstbegleiter, `systemctl status kunst-staging-queue` läuft.

Live genauso mit `art`, `main`, `kunst`, `kunst-queue`, `kunst-prod`, ohne `SEED_AFTER_MIGRATE` (der Deploy ruft `db:seed` ohnehin für die Epochen).

## 3. Wichtige `.env`-Schlüssel (nur am Server)

| Schlüssel | Wert |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `production` / `false` / `https://art.tourtool.app` (Staging: `staging`, `false`, `https://art-staging.tourtool.app`) |
| `DB_*` | MariaDB wie Abschnitt 1 |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | alle `redis` |
| `REDIS_PORT`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_PREFIX` | `6380`, `6` (Staging `7`), gleich, `kunst_` (Staging `kunst_stg_`) |
| `SESSION_LIFETIME` | `43200` (30 Tage) |
| `PASSKEYS_RP_ID` | leer lassen (Host aus `APP_URL`) |
| `ANTHROPIC_API_KEY` | ab Etappe 3 |
| `MUSEUMGUIDE_TTS`, `MUSEUMGUIDE_PLACES`, `GOOGLE_PLACES_KEY` | ab Etappe 2 und 3, bis dahin `fake` |
| `MUSEUMGUIDE_MONTHLY_LIMIT_CENTS` | `3000` |
| `BACKUP_APP` | `kunst-prod` / `kunst-staging` |
| `MAIL_*` | ab Etappe 5 echter Versand (Kopplungs-Einladung) |

Nach `.env`-Änderungen: `/opt/plesk/php/8.5/bin/php artisan optimize:clear && ... artisan optimize`, danach `sudo systemctl restart kunst-queue`.

## 4. Ablauf einer Änderung

1. Feature-Branch, lokal Pint und die Tests der geänderten Bereiche, bei Oberflächen ein Screenshot je Seite bei 390 px.
2. Nach `staging` mergen und pushen, der Server holt den Stand innerhalb einer Minute (`deploy.sh`), auf dem iPhone in Safari prüfen.
3. Passt es: nach `main` mergen (Pull Request mit grüner CI). Live-Deploy innerhalb einer Minute, kurze Wartungsseite während `composer install` und `migrate`.
4. Nach jedem Merge das Deploy-Log auf FAIL prüfen, bei Zweifel `php artisan migrate:status` am Server.

## 5. Lokal

- PHP 8.5 lokal: `curl -fsSL https://php.new/install/linux/8.5 | bash` (legt `~/.config/herd-lite/bin/php` an), `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate --seed`, `php artisan kunst:user ... --admin`, `php artisan serve`.
- CSS: `npm install` einmal, dann `bin/build-css` nach jeder Änderung an Blade-Klassen oder `resources/css/app.css`, Ergebnis einchecken.
- Tests: `vendor/bin/pest`, Stil: `vendor/bin/pint`.
- Icons: `public/branding/icon.svg` ist die Vorlage, PNGs werden mit Chromium gerendert und eingecheckt (der Server rendert nichts).
