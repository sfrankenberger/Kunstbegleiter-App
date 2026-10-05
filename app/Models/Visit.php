<?php

namespace App\Models;

use App\Models\Concerns\CascadesSoftDeletes;
use App\Models\Concerns\LogsChanges;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'museum_id', 'city_id', 'shared_with_user_id', 'lat', 'lng', 'accuracy_m', 'started_at', 'valid_until', 'notes'])]
/**
 * Der Museumsbesuch als Kontext (docs/grundgeruest.md): gilt 30 Minuten ab der letzten Aufnahme (`extend`).
 * Papierkorb mit Kaskade auf Aufnahmen und Stadt-Tipps.
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use CascadesSoftDeletes, HasFactory, LogsChanges, SoftDeletes;

    /** @var list<string> */
    protected array $softCascades = ['captures', 'cityTips'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'started_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    /** @return BelongsTo<Museum, $this> */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Capture, $this> */
    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    /** @return HasMany<CityTip, $this> */
    public function cityTips(): HasMany
    {
        return $this->hasMany(CityTip::class);
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('valid_until', '>', now());
    }

    public function isActive(): bool
    {
        return $this->valid_until->isFuture();
    }

    /**
     * Gueltigkeit um die Besuchsdauer aus der Config verlaengern (jede Aufnahme ruft das auf).
     */
    public function extend(): void
    {
        $this->forceFill(['valid_until' => now()->addMinutes((int) config('museumguide.visit.minutes', 30))])->save();
    }

    public function remainingMinutes(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->valid_until, false) / 60));
    }
}
