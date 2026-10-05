<?php

namespace App\Models;

use App\Enums\PairingStatus;
use Database\Factories\PairingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_a_id', 'user_b_id', 'requested_by_id', 'status', 'token', 'accepted_at'])]
/**
 * Kopplung zweier Nutzer (Etappe 5): angefragt per Einladung, aktiv nach Annahme, jederzeit loesbar.
 * user_a_id ist immer die kleinere ID (siehe `between`).
 */
class Pairing extends Model
{
    /** @use HasFactory<PairingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PairingStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * @param  Builder<Pairing>  $query
     * @return Builder<Pairing>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PairingStatus::Active);
    }

    /**
     * @param  Builder<Pairing>  $query
     * @return Builder<Pairing>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_a_id', $user->getKey())->orWhere('user_b_id', $user->getKey()));
    }

    public function partnerOf(User $user): ?User
    {
        if ((int) $this->user_a_id === (int) $user->getKey()) {
            return $this->userB;
        }

        if ((int) $this->user_b_id === (int) $user->getKey()) {
            return $this->userA;
        }

        return null;
    }

    /**
     * IDs in fester Reihenfolge (kleinere zuerst), damit ein Paar nur einmal vorkommt.
     *
     * @return array{user_a_id: int, user_b_id: int}
     */
    public static function between(User $one, User $two): array
    {
        $ids = [(int) $one->getKey(), (int) $two->getKey()];
        sort($ids);

        return ['user_a_id' => $ids[0], 'user_b_id' => $ids[1]];
    }
}
