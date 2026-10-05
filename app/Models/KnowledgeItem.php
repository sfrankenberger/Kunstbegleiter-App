<?php

namespace App\Models;

use Database\Factories\KnowledgeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'subject_type', 'subject_id', 'capture_id', 'summary', 'told_at'])]
/**
 * Lerngedaechtnis je Nutzer: was zu einem Kuenstler oder einer Epoche (Morph `subject`) schon erzaehlt wurde.
 */
class KnowledgeItem extends Model
{
    /** @use HasFactory<KnowledgeItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'told_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }
}
