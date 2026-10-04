<?php

namespace App\Models;

use Database\Factories\EpochFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'slug', 'from_year', 'to_year', 'sort_order'])]
/**
 * Epoche fuer das Archiv (Seeder database/seeders/EpochSeeder.php).
 */
class Epoch extends Model
{
    /** @use HasFactory<EpochFactory> */
    use HasFactory;

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
}
