<?php

namespace App\Models;

use Database\Factories\ExhibitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['museum_id', 'title', 'starts_on', 'ends_on', 'source_url', 'fetched_at'])]
/**
 * Sonderausstellung oder Sammlung eines Museums, aus der Museums-Recherche (Etappe 3).
 */
class Exhibition extends Model
{
    /** @use HasFactory<ExhibitionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Museum, $this> */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }
}
