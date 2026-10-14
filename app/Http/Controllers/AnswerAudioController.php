<?php

namespace App\Http\Controllers;

use App\Models\CaptureQuestion;
use App\Services\Captures\CaptureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gesprochene Antwort einer Nachfrage: nur signiert und nur fuer, wer die Aufnahme sehen darf.
 */
class AnswerAudioController extends Controller
{
    public function __invoke(Request $request, CaptureQuestion $question): BinaryFileResponse
    {
        $request->user()?->can('view', $question->capture) || abort(403);
        $question->hasAudio() || abort(404);

        return response()->file(Storage::disk(CaptureService::DISK)->path((string) $question->audio_path), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
