<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\RoomTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'visit_id', 'museum_id', 'path', 'width', 'height', 'title', 'text', 'error', 'cost_cents'])]
/**
 * Ein gescannter Raum- oder Saaltext (docs/konzept.md Abschnitt 26): Foto auf der privaten Platte, abgelesener
 * Text, im Archiv nachlesbar und als Kontext fuer die naechsten Werk-Aufnahmen waehlbar.
 */
class RoomText extends Model
{
    /** @use HasFactory<RoomTextFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<Museum, $this> */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /** @return HasMany<Capture, $this> */
    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    public function label(): string
    {
        return filled($this->title) ? (string) $this->title : 'Raumtext von '.$this->created_at?->format('H:i');
    }

    public function excerpt(int $words = 18): string
    {
        return Str::words(trim(preg_replace('/\s+/u', ' ', (string) $this->text) ?? ''), $words, ' ...');
    }

    /**
     * Fuer die Prompts: Titel und Text, leer wenn nichts abgelesen wurde.
     */
    public function promptText(): string
    {
        return trim((filled($this->title) ? $this->title."\n" : '').(string) $this->text);
    }

    /**
     * Signierte Adresse fuer das Foto (60 Minuten), Auslieferung ueber RoomTextPhotoController.
     */
    public function hasPhoto(): bool
    {
        return $this->path !== '' && $this->path !== null;
    }

    public function url(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        return URL::temporarySignedRoute('raumtexte.foto', now()->addHour(), ['roomText' => $this->getKey()]);
    }
}
