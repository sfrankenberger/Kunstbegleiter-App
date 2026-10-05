<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artwork_id', 'place_id', 'title', 'artist', 'year', 'reason', 'wikidata_id', 'image_url', 'image_credit', 'sort_order'])]
/**
 * Vergleichswerk, auf das der Guide verweist, mit Bild von Wikimedia Commons (App\Services\Images\WikiImages).
 * Kein Papierkorb, kein Protokoll: wird je Werk neu aus der Recherche befuellt.
 */
class RelatedWork extends Model
{
    /** @return BelongsTo<Artwork, $this> */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }
}
