<?php

namespace App\Filament\Support;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Tab "Verlauf" fuer alle Resources mit Aenderungsprotokoll (Standard aus Tourtool): wer hat wann was geaendert,
 * je Feld alter und neuer Wert. Nur lesen, keine Aktionen.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'changeLog';

    protected static ?string $title = 'Verlauf';

    public const EVENT_LABELS = [
        'created' => 'angelegt',
        'updated' => 'geändert',
        'deleted' => 'in den Papierkorb',
        'restored' => 'wiederhergestellt',
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, 'changeLog');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer')->latest('id'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Wann')->dateTime('d.m.Y H:i', config('app.timezone'))->sortable(),
                TextColumn::make('causer.name')->label('Wer')->default('System')->placeholder('System'),
                TextColumn::make('event')->label('Ereignis')->badge()
                    ->formatStateUsing(fn (?string $state): string => self::EVENT_LABELS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('changes')->label('Änderungen')->state(fn (Activity $record): string => self::describe($record))->wrap()->html(),
            ])
            ->paginated([25, 50, 100])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * Geaenderte Felder als Zeilen "Feld: alt » neu" (beim Anlegen nur die neuen Werte).
     */
    public static function describe(Activity $activity): string
    {
        $changes = collect($activity->attribute_changes ?? []);
        $attributes = (array) $changes->get('attributes', []);
        $old = (array) $changes->get('old', []);

        if ($attributes === [] && $old === []) {
            return e((string) $activity->description);
        }

        $lines = [];

        foreach ($attributes as $field => $value) {
            $new = self::value($value);

            if (array_key_exists($field, $old)) {
                $lines[] = '<span class="font-medium">'.e((string) $field).'</span>: '.e(self::value($old[$field])).' » '.e($new);
            } else {
                $lines[] = '<span class="font-medium">'.e((string) $field).'</span>: '.e($new);
            }
        }

        foreach ($old as $field => $value) {
            if (! array_key_exists($field, $attributes)) {
                $lines[] = '<span class="font-medium">'.e((string) $field).'</span>: '.e(self::value($value)).' » (leer)';
            }
        }

        return implode('<br>', $lines);
    }

    private static function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(leer)';
        }

        if (is_bool($value)) {
            return $value ? 'ja' : 'nein';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return mb_strimwidth((string) $value, 0, 200, ' ...');
    }
}
