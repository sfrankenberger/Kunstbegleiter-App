<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Database\Factories\AudioGuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['capture_id', 'script', 'audio_path', 'duration_seconds', 'word_count', 'model', 'tts_provider', 'tts_characters', 'input_tokens', 'output_tokens', 'cost_cents', 'feedback', 'difficulty_feedback'])]
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
