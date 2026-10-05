<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sort_name', 'born_year', 'died_year', 'wikidata_id', 'gnd_id', 'short_bio', 'portrait_url', 'portrait_credit', 'portrait_checked_at'])]
/**
 * Kuenstler, geteilt. Normdaten-IDs (Wikidata, GND) erkennen Wiederholungen ueber Schreibweisen hinweg.
 */
class Artist extends Model
{
    protected function casts(): array
    {
        return ['portrait_checked_at' => 'datetime'];
    }

    /** @use HasFactory<ArtistFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Artist $artist): void {
            if (blank($artist->sort_name)) {
                $parts = explode(' ', trim($artist->name));
                $artist->sort_name = count($parts) > 1 ? array_pop($parts).', '.implode(' ', $parts) : $artist->name;
            }
        });
    }

    /** @return HasMany<Artwork, $this> */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /** @return MorphMany<KnowledgeItem, $this> */
    public function knowledgeItems(): MorphMany
    {
        return $this->morphMany(KnowledgeItem::class, 'subject');
    }

    public function lifeDates(): string
    {
        if ($this->born_year === null && $this->died_year === null) {
            return '';
        }

        return ($this->born_year ?? '?').' bis '.($this->died_year ?? 'heute');
    }
}
