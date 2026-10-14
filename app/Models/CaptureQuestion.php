<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable(['capture_id', 'user_id', 'question', 'answer', 'audio_path', 'duration_seconds', 'cost_cents'])]
/**
 * Eine Nachfrage zum Werk oder Ort (docs/konzept.md Abschnitt 25): Frage, kurze Antwort, gesprochen.
 */
class CaptureQuestion extends Model
{
    use LogsChanges, SoftDeletes;

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    public function url(): ?string
    {
        return $this->hasAudio() ? URL::temporarySignedRoute('antwort.audio', now()->addHour(), ['question' => $this->getKey()]) : null;
    }
}
