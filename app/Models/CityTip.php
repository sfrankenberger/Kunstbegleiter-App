<?php

namespace App\Models;

use App\Enums\TipKind;
use Database\Factories\CityTipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['visit_id', 'title', 'kind', 'url', 'reason', 'sort_order'])]
/**
 * Tipp in der Stadt nach einem Besuch (Etappe 5), mit Begruendung und Link.
 */
class CityTip extends Model
{
    /** @use HasFactory<CityTipFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => TipKind::class,
        ];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
