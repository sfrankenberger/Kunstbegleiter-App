<?php

namespace App\Filament\Resources\Users;

use App\Enums\GuideLength;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\ActivitiesRelationManager;
use App\Models\AiCall;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Nutzer im Admin: Name, E-Mail, Passwort, Admin-Recht, Vorwissen-Profil, Monatslimit, Kosten des Monats, Verlauf.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Nutzer';

    protected static ?string $pluralModelLabel = 'Nutzer';

    protected static ?string $slug = 'nutzer';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Konto')->columns(2)->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(100),
                TextInput::make('email')->label('E-Mail')->email()->required()->unique(ignoreRecord: true)->maxLength(190),
                TextInput::make('password')->label('Passwort')->password()->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Beim Bearbeiten leer lassen, um das Passwort zu behalten.'),
                Toggle::make('is_admin')->label('Admin (Zugang zu /admin)'),
            ]),
            Section::make('Vorwissen und Vorlieben')->columns(2)->schema([
                Textarea::make('knowledge_profile')->label('Vorwissen-Profil')->rows(4)->columnSpanFull()
                    ->helperText('Freitext, wird jedem Skript mitgegeben, z. B. "Austria Guide, Schwerpunkt Wien um 1900".'),
                Select::make('preferred_length')->label('Länge')->options(collect(GuideLength::cases())->mapWithKeys(fn (GuideLength $l) => [$l->value => $l->label()]))->default('normal'),
                TextInput::make('monthly_budget_cents')->label('Monatslimit (Cent)')->numeric()->minValue(0)
                    ->helperText('Leer: Standard aus der Config ('.(int) config('museumguide.costs.monthly_limit_cents').' Cent).'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable(),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                TextColumn::make('month_cents')->label('Kosten dieser Monat')
                    ->state(fn (User $record): string => number_format(AiCall::monthCents($record) / 100, 2, ',', '.').' €'),
                TextColumn::make('created_at')->label('Angelegt')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/neu'),
            'edit' => EditUser::route('/{record}/bearbeiten'),
        ];
    }
}
