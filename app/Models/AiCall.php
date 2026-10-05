<?php

namespace App\Models;

use App\Enums\AiPurpose;
use Database\Factories\AiCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'capture_id', 'purpose', 'model', 'input_tokens', 'output_tokens', 'characters', 'cost_cents', 'duration_ms', 'succeeded', 'error'])]
/**
 * Kostenprotokoll je KI- oder TTS-Aufruf. Grundlage fuer das Monatslimit (`monthCents`) und die Uebersicht im Admin.
 */
class AiCall extends Model
{
    /** @use HasFactory<AiCallFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'purpose' => AiPurpose::class,
            'succeeded' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }

    /**
     * @param  Builder<AiCall>  $query
     * @return Builder<AiCall>
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    /**
     * Kosten eines Nutzers im laufenden Monat in Cent.
     */
    public static function monthCents(User $user): int
    {
        return (int) static::query()->whereBelongsTo($user)->thisMonth()->sum('cost_cents');
    }
}
