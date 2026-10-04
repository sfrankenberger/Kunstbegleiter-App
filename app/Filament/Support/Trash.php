<?php

namespace App\Filament\Support;

use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Papierkorb in Filament, ein Baustein fuer alle Resources und Relation Manager (Standard aus Tourtool):
 * Filter "Papierkorb", Wiederherstellen (einzeln und mehrfach), endgueltig loeschen nur fuer Admins.
 * Die Abfrage der Resource muss den SoftDeletingScope abschalten (Trash::query), sonst greift der Filter nicht.
 */
class Trash
{
    public static function filter(): TrashedFilter
    {
        return TrashedFilter::make()->label('Papierkorb');
    }

    /**
     * @return array<int, RestoreAction|ForceDeleteAction>
     */
    public static function recordActions(): array
    {
        return [
            RestoreAction::make()->label('Wiederherstellen'),
            ForceDeleteAction::make()->label('Endgültig löschen')->visible(fn (): bool => self::mayForceDelete()),
        ];
    }

    /**
     * @return array<int, RestoreBulkAction|ForceDeleteBulkAction>
     */
    public static function bulkActions(): array
    {
        return [
            RestoreBulkAction::make()->label('Wiederherstellen'),
            ForceDeleteBulkAction::make()->label('Endgültig löschen')->visible(fn (): bool => self::mayForceDelete()),
        ];
    }

    public static function mayForceDelete(): bool
    {
        return (bool) (auth()->user()?->is_admin ?? false);
    }

    /**
     * Abfrage ohne den SoftDeletingScope, damit der Filter "Papierkorb" die geloeschten Zeilen zeigen kann.
     */
    public static function query(Builder $query): Builder
    {
        return $query->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
