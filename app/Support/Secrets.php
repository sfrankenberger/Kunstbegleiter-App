<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Schluessel und Token (docs/konzept.md Abschnitt 9): zuerst aus dem Admin (Tabelle settings, verschluesselt),
 * sonst aus der .env ueber config/museumguide.php. Werte stehen nie im Aenderungsprotokoll und nie in Antworten
 * der Oberflaeche, nur "gesetzt" oder "fehlt". Cache je Schluessel, beim Speichern geleert.
 */
class Secrets
{
    /** @var array<string, string> Name im Admin => Config-Pfad fuer den Rueckfall aus der .env */
    public const KEYS = [
        'anthropic_key' => 'museumguide.anthropic.key',
        'google_places_key' => 'museumguide.places.key',
        'elevenlabs_key' => 'museumguide.tts.elevenlabs_key',
        'openai_key' => 'museumguide.tts.openai_key',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'anthropic_key' => 'Anthropic API-Schlüssel (Erkennung, Recherche, Skript)',
        'google_places_key' => 'Google Places API-Schlüssel (Museum in der Nähe)',
        'elevenlabs_key' => 'ElevenLabs API-Schlüssel (Stimmen, ab Etappe 3)',
        'openai_key' => 'OpenAI API-Schlüssel (Stimmen, Alternative)',
    ];

    public static function get(string $name): ?string
    {
        self::assertKnown($name);

        $stored = Cache::rememberForever(self::cacheKey($name), function () use ($name): string {
            $value = Setting::query()->where('key', $name)->value('value');

            return is_string($value) ? $value : '';
        });

        if ($stored !== '') {
            return $stored;
        }

        $fallback = trim((string) config(self::KEYS[$name]));

        return $fallback !== '' ? $fallback : null;
    }

    public static function has(string $name): bool
    {
        return self::get($name) !== null;
    }

    /**
     * Woher der Wert kommt: admin, env oder null (fehlt).
     */
    public static function source(string $name): ?string
    {
        self::assertKnown($name);

        if (Setting::query()->where('key', $name)->whereNotNull('value')->where('value', '!=', '')->exists()) {
            return 'admin';
        }

        return trim((string) config(self::KEYS[$name])) !== '' ? 'env' : null;
    }

    public static function set(string $name, ?string $value, ?User $by = null): void
    {
        self::assertKnown($name);
        $value = trim((string) $value);

        if ($value === '') {
            Setting::query()->where('key', $name)->delete();
        } else {
            Setting::query()->updateOrCreate(['key' => $name], ['value' => $value, 'updated_by_id' => $by?->getKey()]);
        }

        Cache::forget(self::cacheKey($name));
    }

    /**
     * Letzte vier Zeichen zur Wiedererkennung, nie der ganze Schluessel.
     */
    public static function hint(string $name): ?string
    {
        $value = self::get($name);

        return $value === null ? null : '••••'.substr($value, -4);
    }

    private static function cacheKey(string $name): string
    {
        return 'secrets.'.$name;
    }

    private static function assertKnown(string $name): void
    {
        if (! array_key_exists($name, self::KEYS)) {
            throw new \InvalidArgumentException('Unbekannter Schlüssel: '.$name);
        }
    }
}
