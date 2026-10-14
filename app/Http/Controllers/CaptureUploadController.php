<?php

namespace App\Http\Controllers;

use App\Enums\GuideMode;
use App\Models\Capture;
use App\Models\RoomText;
use App\Models\User;
use App\Models\Visit;
use App\Services\Captures\CaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aufnahme hochladen aus der Offline-Warteschlange des Handys (public/js/offline.js, docs/konzept.md Abschnitt 25):
 * Fotos, Modus, Raumtext und eine Handy-ID in einem Aufruf. Dieselbe Handy-ID liefert die schon angelegte Aufnahme
 * (Wiederholung nach Funkloch). Der Besuch ist der gemerkte, auch wenn er inzwischen abgelaufen ist, sonst der
 * aktive. Die Pipeline startet im Hintergrund, die Antwort kommt sofort.
 */
class CaptureUploadController extends Controller
{
    public function __invoke(Request $request, CaptureService $captures): JsonResponse
    {
        $max = (int) config('museumguide.photos.max_per_capture', 3);
        $kb = (int) ((int) config('museumguide.photos.max_bytes', 8 * 1024 * 1024) / 1024);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'photos' => ['required', 'array', 'min:1', 'max:'.$max],
            'photos.*' => ['image', 'max:'.$kb],
            'full' => ['nullable', 'boolean'],
            'room_text_id' => ['nullable', 'integer'],
            'visit_id' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $existing = Capture::query()->whereBelongsTo($user)->where('client_id', $data['client_id'])->first();

        if ($existing !== null) {
            return response()->json(['url' => route('aufnahme', $existing), 'id' => $existing->getKey()]);
        }

        $visit = isset($data['visit_id']) ? Visit::query()->whereBelongsTo($user)->find($data['visit_id']) : null;
        $visit ??= $user->activeVisit();

        if ($visit === null) {
            return response()->json(['message' => 'Bitte zuerst einen Besuch starten.'], 422);
        }

        $roomText = isset($data['room_text_id']) ? RoomText::query()->whereBelongsTo($user)->find($data['room_text_id']) : null;

        $capture = $captures->create($user, $visit, array_values($request->file('photos')), ($data['full'] ?? false) ? GuideMode::Full : GuideMode::Quick, $roomText, queue: true, clientId: $data['client_id']);

        return response()->json(['url' => route('aufnahme', $capture), 'id' => $capture->getKey()], 201);
    }
}
