<?php

namespace App\Http\Controllers;

use App\Models\AudioGuide;
use App\Services\Captures\CaptureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * MP3 eines Audioguides ausliefern: nur ueber signierte Adressen (AudioGuide::url, 60 Minuten) und nur fuer
 * Nutzer, die die Aufnahme sehen duerfen (CapturePolicy). Dateien liegen in storage/app/private.
 */
class AudioController extends Controller
{
    public function __invoke(Request $request, AudioGuide $guide): BinaryFileResponse
    {
        $request->user()?->can('view', $guide->capture) || abort(403);
        $guide->hasAudio() || abort(404);

        return response()->file(Storage::disk(CaptureService::DISK)->path((string) $guide->audio_path), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
