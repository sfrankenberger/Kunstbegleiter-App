<?php

namespace App\Livewire\Pages;

use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reiter "Jetzt": aktueller Besuch (Museum, Restzeit), Kamera-Knopf, Werke dieses Besuchs. In Etappe 1 nur die
 * Huelle: der aktive Besuch, falls es einen gibt, und der Hinweis, dass Kamera und Ort in Etappe 2 kommen.
 */
#[Layout('components.layouts.app', ['title' => 'Jetzt'])]
class Jetzt extends Component
{
    public function render(): View
    {
        /** @var Visit|null $visit */
        $visit = auth()->user()?->activeVisit();

        return view('livewire.pages.jetzt', [
            'visit' => $visit,
            'captures' => $visit?->captures()->with('artwork.artist')->latest()->get() ?? collect(),
        ]);
    }
}
