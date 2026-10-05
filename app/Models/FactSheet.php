<?php

namespace App\Models;

use Database\Factories\FactSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['capture_id', 'key_facts', 'key_statements', 'guest_ideas', 'cross_references'])]
/**
 * Bildschirm-Zusammenfassung je Aufnahme: Kurzfakten, Kernaussagen, "Fuer deine Gaeste", Querverweise.
 */
class FactSheet extends Model
{
    /** @use HasFactory<FactSheetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'key_facts' => 'array',
            'key_statements' => 'array',
            'guest_ideas' => 'array',
            'cross_references' => 'array',
        ];
    }

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }
}
