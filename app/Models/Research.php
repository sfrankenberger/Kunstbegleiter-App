<?php

namespace App\Models;

use Database\Factories\ResearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artwork_id', 'place_id', 'summary', 'sources', 'existing_guides', 'model', 'input_tokens', 'output_tokens', 'cost_cents'])]
/**
 * Recherche je Werk, wiederverwendbar fuer alle Aufnahmen dieses Werks. Tabelle `research`.
 */
class Research extends Model
{
    /** @use HasFactory<ResearchFactory> */
    use HasFactory;

    protected $table = 'research';

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'existing_guides' => 'array',
        ];
    }

    /** @return BelongsTo<Artwork, $this> */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }
}
