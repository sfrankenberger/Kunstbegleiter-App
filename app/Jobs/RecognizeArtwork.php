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
use App\Services\Pipeline\PlaceMatcher;
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
            'max_tokens' => 2048,
        ], $capture->user, $capture);

        if (self::store($capture, $result)) {
            app(Pipeline::class)->continueAfterRecognition($capture);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function imageBlock(CapturePhoto $photo): array
    {
        $path = app(CaptureService::class)->photoPath($photo);
        $mime = (string) (mime_content_type($path) ?: 'image/jpeg');
        $edge = (int) config('museumguide.vision_edge', 1024);
        $data = (string) file_get_contents($path);

        // Verkleinern spart Zeit (weniger Bytes hochladen, weniger Bildtokens), GD ist am Server da
        if ($edge > 0 && function_exists('imagecreatefromstring') && max((int) $photo->width, (int) $photo->height) > $edge) {
            $image = @imagecreatefromstring($data);

            if ($image !== false) {
                $scaled = imagescale($image, (int) $photo->width >= (int) $photo->height ? $edge : -1, (int) $photo->width >= (int) $photo->height ? -1 : $edge);

                if ($scaled !== false) {
                    ob_start();
                    imagejpeg($scaled, null, 82);
                    $data = (string) ob_get_clean();
                    $mime = 'image/jpeg';
                }
            }
        }

        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : 'image/jpeg', 'data' => base64_encode($data)]];
    }

    /**
     * Erkennung in die Aufnahme schreiben: Fototypen, OCR, Recognition. Liefert true, wenn sie sicher genug ist.
     *
     * @param  array<string, mixed>  $result
     */
    public static function store(Capture $capture, array $result): bool
    {
        foreach ((array) ($result['photos'] ?? []) as $row) {
            $photo = $capture->photos->get((int) ($row['index'] ?? -1));

            if ($photo instanceof CapturePhoto) {
                $photo->update(['type' => PhotoType::tryFrom((string) ($row['type'] ?? '')) ?? $photo->type, 'ocr_text' => $row['text'] ?? null]);
            }
        }

        $confidence = (float) ($result['confidence'] ?? 0);
        $recognition = array_intersect_key($result, array_flip(['photos', 'title', 'artist', 'artist_life_dates', 'dating', 'technique', 'dimensions', 'inventory_number', 'epoch', 'confidence', 'alternatives', 'notes']));
        $capture->forceFill(['recognition' => $recognition])->save();

        if (blank($result['title'] ?? null) || $confidence < (float) config('museumguide.pipeline.confidence_threshold', 0.7)) {
            $capture->forceFill(['status' => CaptureStatus::Recognized, 'step' => PipelineStep::Confirming, 'needs_confirmation' => true])->save();

            return false;
        }

        if ($capture->visit_id === null) {
            app(PlaceMatcher::class)->attach($capture, $recognition);
        } else {
            app(ArtworkMatcher::class)->attach($capture, $recognition);
        }

        return true;
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
