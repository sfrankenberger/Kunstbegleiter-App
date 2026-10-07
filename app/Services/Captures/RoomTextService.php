<?php

namespace App\Services\Captures;

use App\Enums\AiPurpose;
use App\Jobs\RecognizeArtwork;
use App\Models\RoomText;
use App\Models\User;
use App\Models\Visit;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Raumtext scannen (Sebastian, 07.10.2026: "nur Raumtext scannen und es passiert nichts"): Foto speichern, den
 * Text mit einem Vision-Aufruf ablesen (keine Pipeline, kein Guide), fertig. Liest der Aufruf nichts, bleibt
 * das Foto mit Fehlermeldung erhalten.
 */
class RoomTextService
{
    public function create(User $user, ?Visit $visit, UploadedFile $file): RoomText
    {
        $roomText = RoomText::query()->create([
            'user_id' => $user->getKey(),
            'visit_id' => $visit?->getKey(),
            'museum_id' => $visit?->museum_id,
            'path' => '',
        ]);

        $path = $file->storeAs('room-texts', $roomText->getKey().'.'.($file->guessExtension() ?: 'jpg'), CaptureService::DISK);
        [$width, $height] = app(CaptureService::class)->dimensions($file);
        $roomText->forceFill(['path' => (string) $path, 'width' => $width, 'height' => $height])->save();

        try {
            $result = app(ClaudeClient::class)->structured(AiPurpose::Recognize, [['role' => 'user', 'content' => [
                RecognizeArtwork::imageBlockFromFile(Storage::disk(CaptureService::DISK)->path((string) $path), $width, $height),
                ['type' => 'text', 'text' => 'Lies den Raumtext vollständig ab und liefere JSON nach dem Schema.'],
            ]]], Schemas::roomText(), ['system' => Prompts::render('room-text', []), 'effort' => 'low', 'max_tokens' => 4096], $user);

            $roomText->forceFill([
                'title' => $result['title'] ?? null,
                'text' => $result['text'] ?? null,
                'error' => null,
                'cost_cents' => (int) $user->aiCalls()->latest('id')->value('cost_cents'),
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $roomText->forceFill(['error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }

        $visit?->extend();

        return $roomText;
    }
}
