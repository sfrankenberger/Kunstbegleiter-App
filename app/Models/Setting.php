<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'value', 'updated_by_id'])]
/**
 * Einstellung aus dem Admin, Wert verschluesselt (App\Support\Secrets). Kein Aenderungsprotokoll, weil die Werte
 * Geheimnisse sind; wer zuletzt geaendert hat, steht in updated_by_id.
 */
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
