<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\MuseumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['city_id', 'name', 'place_id', 'website', 'address', 'lat', 'lng', 'research', 'researched_at'])]
/**
 * Museum: Archiv-Gruppierung und Startpunkt der Recherche. Geteilt zwischen allen Nutzern.
 */
class Museum extends Model
{
    /** @use HasFactory<MuseumFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'research' => 'array',
            'researched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Exhibition, $this> */
    public function exhibitions(): HasMany
    {
        return $this->hasMany(Exhibition::class);
    }

    /** @return HasMany<Artwork, $this> */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
