<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['city_id', 'name', 'kind', 'wikidata_id', 'osm_id', 'place_id', 'lat', 'lng', 'address', 'description', 'architect', 'built', 'image_url', 'image_credit', 'facts'])]
/**
 * Ort in der Stadt (docs/konzept.md Abschnitt 23): Statue, Gebaeude, Platz, Kirche, Denkmal. Einmal angelegt
 * (Schluessel Wikidata-ID), geteilt, mit Recherche und Vergleichsbauten wie ein Werk.
 */
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    public const KINDS = ['statue' => 'Statue', 'monument' => 'Denkmal', 'building' => 'Gebäude', 'church' => 'Kirche', 'square' => 'Platz', 'bridge' => 'Brücke', 'fountain' => 'Brunnen', 'park' => 'Park', 'other' => 'Ort'];

    protected function casts(): array
    {
        return ['facts' => 'array', 'lat' => 'float', 'lng' => 'float'];
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['other'];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Capture, $this> */
    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    /** @return HasOne<Research, $this> */
    public function research(): HasOne
    {
        return $this->hasOne(Research::class)->latestOfMany();
    }

    /** @return HasMany<RelatedWork, $this> */
    public function relatedWorks(): HasMany
    {
        return $this->hasMany(RelatedWork::class)->orderBy('sort_order');
    }
}
