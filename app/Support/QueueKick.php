<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue-Worker sofort anstossen (docs/konzept.md Abschnitt 13): Der Cron startet den Worker nur jede Minute, das
 * reicht nicht fuer "ausfuehrlich in 45 Sekunden". Nach jedem Dispatch wird deshalb ein abgekoppelter Worker mit
 * --stop-when-empty gestartet; laeuft schon einer, nimmt der die Jobs, der zweite beendet sich gleich wieder.
 * Hoechstens ein Start je 15 Sekunden (Cache-Sperre). Mit systemd-Worker (Betrieb Abschnitt 2, Punkt 6) unnoetig
 * und per MUSEUMGUIDE_QUEUE_KICK=false abschaltbar.
 */
class QueueKick
{
    public static function now(): void
    {
        if (! (bool) config('museumguide.queue.kick', true) || config('queue.default') === 'sync' || ! function_exists('exec')) {
            return;
        }

        if (! Cache::lock('queue-kick', 15)->get()) {
            return;
        }

        $php = (string) config('museumguide.queue.php', 'php');
        $artisan = base_path('artisan');
        $command = sprintf('nohup %s %s queue:work --stop-when-empty --max-time=600 --tries=1 > /dev/null 2>&1 &', escapeshellarg($php), escapeshellarg($artisan));

        try {
            exec($command);
        } catch (Throwable $e) {
            Log::warning('QueueKick failed: '.$e->getMessage());
        }
    }
}
