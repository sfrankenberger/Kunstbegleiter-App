<?php

namespace App\Models;

use App\Enums\CaptureStatus;
use App\Enums\GuideLength;
use App\Models\Concerns\CascadesSoftDeletes;
use App\Models\Concerns\LogsChanges;
use Database\Factories\CaptureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['visit_id', 'user_id', 'artwork_id', 'status', 'length', 'recognition', 'confirmed_at', 'error_message'])]
/**
 * Eine Analyse aus 1 bis 3 Fotos: Erkennung, Recherche, Skript, Audio (Etappe 3). Papierkorb mit Kaskade auf
 * Fotos, Audioguide und Fact Sheet.
 */
class Capture extends Model
{
    /** @use HasFactory<CaptureFactory> */
    use CascadesSoftDeletes, HasFactory, LogsChanges, SoftDeletes;

    /** @var list<string> */
    protected array $softCascades = ['photos', 'audioGuides', 'factSheets'];

    protected function casts(): array
    {
        return [
            'status' => CaptureStatus::class,
            'length' => GuideLength::class,
            'recognition' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Artwork, $this> */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /** @return HasMany<CapturePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(CapturePhoto::class)->orderBy('sort_order');
    }

    /** @return HasMany<AudioGuide, $this> */
    public function audioGuides(): HasMany
    {
        return $this->hasMany(AudioGuide::class);
    }

    /** @return HasOne<AudioGuide, $this> */
    public function audioGuide(): HasOne
    {
        return $this->hasOne(AudioGuide::class)->latestOfMany();
    }

    /** @return HasMany<FactSheet, $this> */
    public function factSheets(): HasMany
    {
        return $this->hasMany(FactSheet::class);
    }

    /** @return HasOne<FactSheet, $this> */
    public function factSheet(): HasOne
    {
        return $this->hasOne(FactSheet::class)->latestOfMany();
    }

    /** @return HasMany<AiCall, $this> */
    public function aiCalls(): HasMany
    {
        return $this->hasMany(AiCall::class);
    }

    public function isDone(): bool
    {
        return $this->status === CaptureStatus::Done;
    }
}
