<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\CaptureStatus;
use App\Enums\PhotoType;
use App\Enums\PipelineStep;
use App\Models\Capture;
use App\Models\CapturePhoto;
use App\Models\Epoch;
use App\Services\Ai\ClaudeClient;
use App\Services\Captures\CaptureService;
use App\Services\Pipeline\ArtworkMatcher;
use App\Services\Pipeline\Pipeline;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;

/**
 * Schritt 1: Claude Vision liest die Schilder (OCR), ordnet die Fototypen zu und erkennt das Werk. Unter dem
 * Schwellwert wartet die Aufnahme auf eine Bestaetigung (needs_confirmation), sonst geht es gleich weiter.
 */
class RecognizeArtwork extends PipelineJob
{
    protected function run(Capture $capture): void
    {
        $capture->forceFill(['step' => PipelineStep::Recognizing])->save();

        $museum = $capture->visit?->museum;
        $content = [];

        foreach ($capture->photos as $index => $photo) {
            $content[] = ['type' => 'text', 'text' => 'Foto '.($index + 1).':'];
            $content[] = self::imageBlock($photo);
        }

        $content[] = ['type' => 'text', 'text' => 'Erkenne das Werk auf diesen Fotos und lies die Texte ab. Foto-Index beginnt bei 0.'];

        $result = app(ClaudeClient::class)->structured(AiPurpose::Recognize, [['role' => 'user', 'content' => $content]], Schemas::recognition(), [
            'system' => Prompts::render('recognize', [
                'museum' => $museum?->name ?? 'unbekannt',
                'city' => $capture->visit?->city?->name ?? $museum?->city?->name ?? 'unbekannt',
                'museum_notes' => self::museumNotes($museum?->research),
                'epochs' => Epoch::query()->orderBy('sort_order')->pluck('name')->implode(', '),
            ]),
            'effort' => 'low',
            'max_tokens' => 4096,
        ], $capture->user, $capture);

        foreach ((array) ($result['photos'] ?? []) as $row) {
            $photo = $capture->photos->get((int) ($row['index'] ?? -1));

            if ($photo instanceof CapturePhoto) {
                $photo->update(['type' => PhotoType::tryFrom((string) ($row['type'] ?? '')) ?? $photo->type, 'ocr_text' => $row['text'] ?? null]);
            }
        }

        $confidence = (float) ($result['confidence'] ?? 0);
        $capture->forceFill(['recognition' => $result])->save();

        if (blank($result['title'] ?? null) || $confidence < (float) config('museumguide.pipeline.confidence_threshold', 0.7)) {
            $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Confirming, 'needs_confirmation' => true])->save();

            return;
        }

        app(ArtworkMatcher::class)->attach($capture, $result);
        app(Pipeline::class)->continueAfterRecognition($capture);
    }

    /**
     * @return array<string, mixed>
     */
    public static function imageBlock(CapturePhoto $photo): array
    {
        $path = app(CaptureService::class)->photoPath($photo);
        $mime = (string) (mime_content_type($path) ?: 'image/jpeg');

        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : 'image/jpeg', 'data' => base64_encode((string) file_get_contents($path))]];
    }

    /**
     * @param  array<string, mixed>|null  $research
     */
    public static function museumNotes(?array $research): string
    {
        if ($research === null) {
            return 'keine';
        }

        $parts = [];

        foreach ((array) ($research['exhibitions'] ?? []) as $exhibition) {
            $parts[] = 'Ausstellung: '.($exhibition['title'] ?? '').(filled($exhibition['period'] ?? null) ? ' ('.$exhibition['period'].')' : '');
        }

        if (filled($research['collection'] ?? null)) {
            $parts[] = 'Sammlung: '.$research['collection'];
        }

        foreach ((array) ($research['notes'] ?? []) as $note) {
            $parts[] = (string) $note;
        }

        return $parts === [] ? 'keine' : implode("\n", $parts);
    }
}
