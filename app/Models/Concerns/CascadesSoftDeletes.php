<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Papierkorb mit Kindern (docs/konzept.md, Standard aus Tourtool): wandert ein Datensatz in den Papierkorb, gehen
 * die in `$softCascades` genannten Kinder mit (nur die, die selbst SoftDeletes haben). Beim Wiederherstellen kommen
 * genau die Kinder zurueck, die im selben Schritt in den Papierkorb kamen (deleted_at ab dem Zeitpunkt des
 * Elternteils). Endgueltiges Loeschen ueberlaesst die Kinder den Fremdschluesseln der Datenbank (cascadeOnDelete).
 *
 * @property list<string> $softCascades Namen der HasMany/MorphMany-Beziehungen
 */
trait CascadesSoftDeletes
{
    public static function bootCascadesSoftDeletes(): void
    {
        static::deleted(function (Model $model): void {
            if (! self::modelUsesSoftDeletes($model) || $model->isForceDeleting()) {
                return;
            }

            foreach ($model->softCascadeRelations() as $relation) {
                if (! self::modelUsesSoftDeletes($model->{$relation}()->getRelated())) {
                    continue;
                }

                $model->{$relation}()->get()->each->delete();
            }
        });

        static::restoring(function (Model $model): void {
            $deletedAt = $model->getAttribute($model->getDeletedAtColumn());

            if ($deletedAt === null) {
                return;
            }

            $since = $deletedAt->copy()->subSeconds(2);

            foreach ($model->softCascadeRelations() as $relation) {
                $related = $model->{$relation}()->getRelated();

                if (! self::modelUsesSoftDeletes($related)) {
                    continue;
                }

                $model->{$relation}()->onlyTrashed()->where($related->getQualifiedDeletedAtColumn(), '>=', $since)->get()->each->restore();
            }
        });
    }

    /**
     * @return list<string>
     */
    public function softCascadeRelations(): array
    {
        return property_exists($this, 'softCascades') ? $this->softCascades : [];
    }

    public static function modelUsesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
