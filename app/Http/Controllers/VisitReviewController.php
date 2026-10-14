<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Besuchsrueckblick (Sebastian, 14.10.2026, docs/konzept.md Abschnitt 25): alle Werke eines Besuchs mit
 * Kernaussagen, Nachfragen und Raumtexten. Fuer Besitzer und Partner mit Anmeldung (`show`), als geteilter Link
 * 30 Tage lang ohne Anmeldung (`shared`, signiert, ohne eigene Fotos). Drucken ergibt ein PDF.
 */
class VisitReviewController extends Controller
{
    public function show(Request $request, Visit $visit): View
    {
        $user = $request->user();
        ((int) $visit->user_id === (int) $user?->getKey() || (int) $visit->shared_with_user_id === (int) $user?->getKey()) || abort(403);

        return $this->review($visit, shared: false, shareUrl: URL::temporarySignedRoute('rueckblick.shared', now()->addDays(30), ['visit' => $visit->getKey()]));
    }

    public function shared(Visit $visit): View
    {
        return $this->review($visit, shared: true, shareUrl: null);
    }

    private function review(Visit $visit, bool $shared, ?string $shareUrl): View
    {
        $visit->load([
            'museum.city', 'city', 'user',
            'captures' => fn ($q) => $q->oldest()->with(['artwork.artist', 'place', 'photos', 'factSheets', 'questions', 'roomText']),
            'roomTexts' => fn ($q) => $q->oldest(),
        ]);

        return view('rueckblick', [
            'visit' => $visit,
            'captures' => $visit->captures->filter(fn ($c) => $c->artwork_id !== null || $c->place_id !== null)->values(),
            'shared' => $shared,
            'shareUrl' => $shareUrl,
        ]);
    }
}
