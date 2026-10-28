<?php

namespace App\Support;

use App\Mail\ErrorAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Unerwartete Ausnahmen per Mail an die Admins (`is_admin`), wie die Betriebsmeldung in Tourtool und Coach Notebook
 * (docs/konzept.md Abschnitt 27): hoechstens eine Mail je Ort (Klasse, Datei, Zeile) und Stunde. Die Mail nennt
 * Klasse, Ort, Adresse und den Anfang der Meldung; den Rest liest Claude im Log (storage/logs/laravel.log).
 * HTTP-Fehler (404, 403, 419) gehen nicht hierher. Scheitert der Versand, bleibt es beim Log.
 */
class ErrorAlert
{
    public const THROTTLE_MINUTES = 60;

    public function report(Throwable $e): bool
    {
        if (! config('museumguide.alert_exceptions', true)) {
            return false;
        }

        $place = str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine();

        try {
            if (! Cache::add('error-alert:'.sha1($e::class.'|'.$place), 1, now()->addMinutes(self::THROTTLE_MINUTES))) {
                return false;
            }

            $admins = User::query()->where('is_admin', true)->whereNotNull('email')->pluck('email');
            if ($admins->isEmpty()) {
                return false;
            }

            $address = app()->runningInConsole() ? 'Konsole' : request()->method().' '.request()->path();
            $text = 'Meldung: '.mb_substr($e->getMessage(), 0, 300)."\nKlasse: ".$e::class."\nOrt: {$place}\nAdresse: {$address}";

            Mail::to($admins->all())->send(new ErrorAlertMail(class_basename($e), $text));

            return true;
        } catch (Throwable $sendError) {
            Log::warning('error alert failed: '.$sendError->getMessage());
        }

        return false;
    }
}
