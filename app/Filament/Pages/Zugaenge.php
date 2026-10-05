<?php

namespace App\Filament\Pages;

use App\Contracts\PlacesClient;
use App\Services\Places\FakePlacesClient;
use App\Support\Secrets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnitEnum;

/**
 * Einstellungen > Zugaenge (nur Admin, docs/konzept.md Abschnitt 9): API-Schluessel fuer Anthropic, Google Places
 * und die Stimmen eintragen. Die Werte liegen verschluesselt in der Datenbank (App\Support\Secrets), die .env ist
 * nur der Rueckfall. Die Seite zeigt nie den ganzen Schluessel, nur Herkunft und die letzten vier Zeichen.
 * Leer lassen behaelt den gespeicherten Wert, "Löschen" entfernt ihn (dann gilt wieder die .env).
 */
class Zugaenge extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Zugänge';

    protected static ?string $title = 'Zugänge';

    protected static ?string $slug = 'zugaenge';

    protected string $view = 'filament.pages.zugaenge';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->is_admin ?? false);
    }

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];

        foreach (Secrets::KEYS as $name => $configPath) {
            $fields[] = TextInput::make($name)
                ->label(Secrets::LABELS[$name])
                ->password()
                ->revealable()
                ->autocomplete('off')
                ->maxLength(500)
                ->placeholder(self::placeholder($name))
                ->helperText(self::status($name));
        }

        return $schema
            ->components([
                Section::make('API-Schlüssel')
                    ->description('Neue Werte eintragen und speichern. Leer lassen behält den gespeicherten Wert. Die Schlüssel liegen verschlüsselt in der Datenbank und werden nirgends angezeigt.')
                    ->schema($fields),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $saved = [];

        foreach (array_keys(Secrets::KEYS) as $name) {
            $value = trim((string) ($data[$name] ?? ''));

            if ($value !== '') {
                Secrets::set($name, $value, auth()->user());
                $saved[] = Secrets::LABELS[$name];
            }
        }

        $this->form->fill([]);

        if ($saved === []) {
            Notification::make()->warning()->title('Nichts geändert')->body('Kein neuer Wert eingetragen.')->send();

            return;
        }

        Notification::make()->success()->title('Gespeichert')->body(implode(', ', $saved))->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkAnthropic')->label('Anthropic prüfen')->icon(Heroicon::OutlinedSignal)->color('gray')->action(fn () => $this->checkAnthropic()),
            Action::make('checkPlaces')->label('Google Places prüfen')->icon(Heroicon::OutlinedMapPin)->color('gray')->action(fn () => $this->checkPlaces()),
        ];
    }

    /**
     * "Löschen" je Schluessel: entfernt den gespeicherten Wert, danach gilt wieder die .env (oder nichts).
     */
    public function forgetAction(): Action
    {
        return Action::make('forget')
            ->label('Löschen')
            ->link()
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => Secrets::LABELS[$arguments['name']] ?? 'Schlüssel')
            ->modalDescription('Den gespeicherten Schlüssel entfernen? Danach gilt wieder der Wert aus der .env, falls vorhanden.')
            ->action(function (array $arguments): void {
                $name = (string) ($arguments['name'] ?? '');

                if (array_key_exists($name, Secrets::KEYS)) {
                    Secrets::set($name, null, auth()->user());
                    Notification::make()->success()->title('Schlüssel entfernt')->send();
                }
            });
    }

    /**
     * Herkunft und Endung je Schluessel fuer die Anzeige.
     *
     * @return list<array{name: string, label: string, source: ?string, hint: ?string}>
     */
    public function rows(): array
    {
        return collect(Secrets::KEYS)->keys()->map(fn (string $name): array => [
            'name' => $name,
            'label' => Secrets::LABELS[$name],
            'source' => Secrets::source($name),
            'hint' => Secrets::hint($name),
        ])->all();
    }

    public static function status(string $name): string
    {
        return match (Secrets::source($name)) {
            'admin' => 'Gesetzt im Admin, endet auf '.Secrets::hint($name),
            'env' => 'Aus der .env, endet auf '.Secrets::hint($name).'. Ein Wert hier hat Vorrang.',
            default => 'Fehlt',
        };
    }

    private static function placeholder(string $name): string
    {
        return Secrets::has($name) ? 'Neuen Schlüssel eintragen, um zu ersetzen' : 'Schlüssel eintragen';
    }

    private function checkAnthropic(): void
    {
        $key = Secrets::get('anthropic_key');

        if ($key === null) {
            Notification::make()->danger()->title('Kein Anthropic-Schlüssel gesetzt')->send();

            return;
        }

        try {
            $response = Http::baseUrl((string) config('museumguide.anthropic.base_url'))
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => (string) config('museumguide.anthropic.version')])
                ->timeout(15)
                ->get('/v1/models', ['limit' => 1]);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Anthropic nicht erreichbar')->body($e->getMessage())->send();

            return;
        }

        if ($response->successful()) {
            Notification::make()->success()->title('Anthropic antwortet')->body('Schlüssel gültig.')->send();
        } else {
            Notification::make()->danger()->title('Anthropic lehnt ab (HTTP '.$response->status().')')->body((string) ($response->json('error.message') ?? ''))->send();
        }
    }

    private function checkPlaces(): void
    {
        $client = app(PlacesClient::class);

        if ($client instanceof FakePlacesClient) {
            Notification::make()->warning()->title('Kein Google-Schlüssel, die App nutzt die Fake-Museen')->send();

            return;
        }

        try {
            $museums = $client->nearbyMuseums(48.2037, 16.3616, 300);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Google Places antwortet nicht')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Google Places antwortet')->body(count($museums).' Museen um das KHM gefunden'.($museums !== [] ? ', zuerst '.$museums[0]['name'] : '').'.')->send();
    }
}
