<?php

namespace Database\Seeders;

use App\Models\Epoch;
use Illuminate\Database\Seeder;

/**
 * Standard-Epochen fuer das Archiv. Mehrfach aufrufbar (Slug ist der Schluessel).
 */
class EpochSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, from: int|null, to: int|null}> */
    public const EPOCHS = [
        ['name' => 'Antike', 'slug' => 'antike', 'from' => -800, 'to' => 500],
        ['name' => 'Mittelalter', 'slug' => 'mittelalter', 'from' => 500, 'to' => 1400],
        ['name' => 'Gotik', 'slug' => 'gotik', 'from' => 1150, 'to' => 1500],
        ['name' => 'Renaissance', 'slug' => 'renaissance', 'from' => 1400, 'to' => 1600],
        ['name' => 'Manierismus', 'slug' => 'manierismus', 'from' => 1520, 'to' => 1600],
        ['name' => 'Barock', 'slug' => 'barock', 'from' => 1600, 'to' => 1750],
        ['name' => 'Rokoko', 'slug' => 'rokoko', 'from' => 1720, 'to' => 1780],
        ['name' => 'Klassizismus', 'slug' => 'klassizismus', 'from' => 1770, 'to' => 1840],
        ['name' => 'Romantik', 'slug' => 'romantik', 'from' => 1790, 'to' => 1850],
        ['name' => 'Biedermeier', 'slug' => 'biedermeier', 'from' => 1815, 'to' => 1848],
        ['name' => 'Realismus', 'slug' => 'realismus', 'from' => 1840, 'to' => 1890],
        ['name' => 'Historismus', 'slug' => 'historismus', 'from' => 1850, 'to' => 1900],
        ['name' => 'Impressionismus', 'slug' => 'impressionismus', 'from' => 1860, 'to' => 1900],
        ['name' => 'Symbolismus', 'slug' => 'symbolismus', 'from' => 1880, 'to' => 1910],
        ['name' => 'Jugendstil und Wiener Secession', 'slug' => 'jugendstil', 'from' => 1890, 'to' => 1914],
        ['name' => 'Expressionismus', 'slug' => 'expressionismus', 'from' => 1905, 'to' => 1930],
        ['name' => 'Klassische Moderne', 'slug' => 'klassische-moderne', 'from' => 1900, 'to' => 1945],
        ['name' => 'Nachkriegsmoderne', 'slug' => 'nachkriegsmoderne', 'from' => 1945, 'to' => 1970],
        ['name' => 'Gegenwartskunst', 'slug' => 'gegenwartskunst', 'from' => 1970, 'to' => null],
    ];

    public function run(): void
    {
        foreach (self::EPOCHS as $index => $epoch) {
            Epoch::query()->updateOrCreate(
                ['slug' => $epoch['slug']],
                ['name' => $epoch['name'], 'from_year' => $epoch['from'], 'to_year' => $epoch['to'], 'sort_order' => $index + 1],
            );
        }
    }
}
