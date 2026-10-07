<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\AudioGuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable(['capture_id', 'kind', 'script', 'audio_path', 'duration_seconds', 'word_count', 'model', 'tts_provider', 'tts_characters', 'input_tokens', 'output_tokens', 'cost_cents', 'feedback', 'difficulty_feedback'])]
/**
 * Das Hoerstueck: Skript (Segmente mit Sprecherrolle), MP3, Dauer, Kosten, Rueckmeldung.
 */
class AudioGuide extends Model
{
    /** @use HasFactory<AudioGuideFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    protected function casts(): array
    {
        return [
            'script' => 'array',
        ];
    }

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }

    public function isFull(): bool
    {
        return $this->kind === 'full';
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    /**
     * Signierte Adresse fuer die MP3 (60 Minuten), Auslieferung ueber AudioController.
     */
    public function url(): ?string
    {
        return $this->hasAudio() ? URL::temporarySignedRoute('audio.show', now()->addHour(), ['guide' => $this->getKey()]) : null;
    }

    /**
     * Skript als Fliesstext zum Mitlesen.
     */
    public function scriptText(): string
    {
        return collect($this->script ?? [])
            ->map(fn (mixed $segment): string => is_array($segment) ? (string) ($segment['text'] ?? '') : (string) $segment)
            ->filter()
            ->implode("\n\n");
    }
}
