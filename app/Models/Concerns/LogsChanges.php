<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Aenderungsprotokoll (docs/konzept.md, Standard aus Tourtool): jede Aenderung an einem Geschaeftsdaten-Modell
 * landet in activity_log mit Benutzer, Zeitpunkt, Ereignis (created, updated, deleted, restored) und den geaenderten
 * Feldern (alt und neu). Ausgenommen sind Zeitstempel, alle versteckten Felder ($hidden) und die in
 * `$activityExcept` genannten Felder.
 *
 * @property list<string> $activityExcept
 */
trait LogsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $except = array_merge(
            ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'],
            $this->getHidden(),
            property_exists($this, 'activityExcept') ? $this->activityExcept : [],
        );

        return LogOptions::defaults()
            ->logAll()
            ->logExcept(array_values(array_unique($except)))
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Verlauf des Datensatzes.
     *
     * @return MorphMany<Activity, $this>
     */
    public function changeLog(): MorphMany
    {
        return $this->morphMany((string) config('activitylog.activity_model'), 'subject');
    }
}
