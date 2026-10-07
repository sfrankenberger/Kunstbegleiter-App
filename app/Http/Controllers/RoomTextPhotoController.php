<?php

namespace App\Http\Controllers;

use App\Models\RoomText;
use App\Services\Captures\CaptureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Foto eines Raumtexts ausliefern: nur signiert (RoomText::url) und nur fuer den Besitzer.
 */
class RoomTextPhotoController extends Controller
{
    public function __invoke(Request $request, RoomText $roomText): BinaryFileResponse
    {
        $request->user()?->can('view', $roomText) || abort(403);

        return response()->file(Storage::disk(CaptureService::DISK)->path($roomText->path), ['Cache-Control' => 'private, max-age=3600']);
    }
}
