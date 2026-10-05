<?php

namespace App\Filament\Pages;

use App\Support\Backup\BackupRestoreService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Einstellungen > Datensicherungen (nur Admin, Standard aus Tourtool): Snapshots des Servers und von Strato HiDrive, Stand einer
 * laufenden Wiederherstellung (alle 3 Sekunden) und der Knopf "Auf diesen Stand zuruecksetzen" mit App-Name und
 * Passwort als Bestaetigung. Die Arbeit macht BackupRestoreService ueber das Server-Skript app-restore.
 */
class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 47;

    protected static ?string $navigationLabel = 'Datensicherungen';

    protected static ?string $title = 'Datensicherungen';

    protected static ?string $slug = 'datensicherungen';

    protected string $view = 'filament.pages.backups';

    public const SOURCE_LABELS = ['local' => 'Server', 'remote' => 'Strato HiDrive'];

    public const STATE_LABELS = [
        'none' => 'Keine Wiederherstellung aktiv',
        'queued' => 'Wiederherstellung läuft ...',
        'running' => 'Wiederherstellung läuft ...',
        'done' => 'Wiederherstellung abgeschlossen',
        'failed' => 'Wiederherstellung fehlgeschlagen',
        'error' => 'Stand nicht lesbar',
    ];

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->is_admin ?? false) && app(BackupRestoreService::class)->enabled();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Stand der Wiederherstellung fuer den Statusbereich (wire:poll). Fehler werden als Zustand "error" gezeigt.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        try {
            return app(BackupRestoreService::class)->status();
        } catch (Throwable $e) {
            return ['state' => 'error', 'message' => Str::limit($e->getMessage(), 300), 'log' => []];
        }
    }

    /**
     * Snapshots fuer die Tabelle. Ein Fehler des Skripts kommt als "error" zurueck, die Liste bleibt leer.
     *
     * @return array{snapshots: list<array{id: string, time: string, source: string, pre_restore: bool}>, remote_error: string|null, local_error: string|null, error: string|null}
     */
    public function snapshots(): array
    {
        try {
            return app(BackupRestoreService::class)->snapshots() + ['error' => null];
        } catch (Throwable $e) {
            return ['snapshots' => [], 'remote_error' => null, 'local_error' => null, 'error' => Str::limit($e->getMessage(), 300)];
        }
    }

    public function appName(): string
    {
        return app(BackupRestoreService::class)->app();
    }

    /**
     * Zeitpunkt lesbar in Europe/Vienna, z. B. "Di. 29.09.2026, 14:05".
     */
    public static function formatTime(?string $time, string $format = 'ddd DD.MM.YYYY, HH:mm'): string
    {
        if ($time === null || trim($time) === '') {
            return '-';
        }

        try {
            return Carbon::parse($time)->timezone((string) config('app.timezone'))->locale('de')->isoFormat($format);
        } catch (Throwable) {
            return $time;
        }
    }

    public static function sourceLabel(?string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? (string) $source;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Liste aktualisieren')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    app(BackupRestoreService::class)->forgetSnapshots();
                    Notification::make()->title('Liste aktualisiert')->success()->send();
                }),
        ];
    }

    /**
     * "Auf diesen Stand zuruecksetzen" je Zeile (Argumente id, source, time): Rueckfrage mit App-Name und Passwort.
     */
    public function restoreAction(): Action
    {
        $app = $this->appName();

        return Action::make('restore')
            ->label('Auf diesen Stand zurücksetzen')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Auf diesen Stand zurücksetzen')
            ->modalDescription(fn (array $arguments): string => 'Alle Änderungen aller Benutzer seit '.self::formatTime($arguments['time'] ?? null, 'DD.MM.YYYY HH:mm').' gehen verloren. Hochgeladene Dateien bleiben unverändert. Die App ist währenddessen kurz offline.')
            ->modalSubmitActionLabel('Jetzt zurücksetzen')
            ->schema([
                TextInput::make('app_name')
                    ->label('App-Name zur Bestätigung')
                    ->helperText('Tippe '.$app.' ein')
                    ->required()
                    ->autocomplete('off')
                    ->in([$app])
                    ->validationMessages(['in' => 'App-Name stimmt nicht']),
                TextInput::make('password')
                    ->label('Dein Passwort')
                    ->password()
                    ->required()
                    ->autocomplete('current-password')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! Hash::check((string) $value, (string) auth()->user()?->password)) {
                            $fail('Passwort falsch');
                        }
                    }),
            ])
            ->action(fn (array $arguments) => $this->startRestore((string) ($arguments['id'] ?? ''), (string) ($arguments['source'] ?? ''), (string) ($arguments['time'] ?? '')));
    }

    private function startRestore(string $id, string $source, string $time): void
    {
        $service = app(BackupRestoreService::class);

        try {
            $service->restore($id, $source, (string) auth()->user()?->email);
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title('Wiederherstellung nicht gestartet')->body($e->getMessage())->send();

            return;
        } catch (RuntimeException $e) {
            Notification::make()->danger()->persistent()->title($e->getMessage())->body('Die Wiederherstellung wurde nicht gestartet.')->send();

            return;
        }

        $service->forgetSnapshots();

        Notification::make()->success()->persistent()
            ->title('Wiederherstellung gestartet')
            ->body('Stand vom '.self::formatTime($time).' ('.self::sourceLabel($source).'). Die App ist gleich kurz offline, der Fortschritt steht oben auf der Seite.')
            ->send();
    }
}
