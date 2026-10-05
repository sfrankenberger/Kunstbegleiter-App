<?php

namespace App\Http\Controllers;

use App\Models\CapturePhoto;
use App\Services\Captures\CaptureService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fotos einer Aufnahme ausliefern: nur ueber signierte Adressen (CapturePhoto::url, 60 Minuten) und nur fuer
 * Nutzer, die die Aufnahme sehen duerfen (CapturePolicy). Dateien liegen in storage/app/private.
 */
class PhotoController extends Controller
{
    public function __invoke(Request $request, CapturePhoto $photo, CaptureService $captures): BinaryFileResponse
    {
        $request->user()?->can('view', $photo->capture) || abort(403);

        return response()->file($captures->photoPath($photo), ['Cache-Control' => 'private, max-age=3600']);
    }
}
