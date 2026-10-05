<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\ArtworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['artist_id', 'epoch_id', 'museum_id', 'title', 'dating', 'technique', 'dimensions', 'inventory_number', 'wikidata_id', 'facts', 'sources'])]
/**
 * Das Werk, einmal angelegt und geteilt. Papierkorb ohne Kaskade auf Aufnahmen: ein Werk im Papierkorb laesst die
 * Aufnahmen beider Nutzer stehen (docs/plan-etappe-1.md Abschnitt 4).
 */
class Artwork extends Model
{
    /** @use HasFactory<ArtworkFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'sources' => 'array',
        ];
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /** @return BelongsTo<Epoch, $this> */
    public function epoch(): BelongsTo
    {
        return $this->belongsTo(Epoch::class);
    }

    /** @return BelongsTo<Museum, $this> */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
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
}
