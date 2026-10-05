<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reiter "Entdecken": Tipps in der Stadt, Rundgang-Ideen, Vergleiche (Etappe 5). In Etappe 1 nur die Huelle.
 */
#[Layout('components.layouts.app', ['title' => 'Entdecken'])]
class Entdecken extends Component
{
    public function render(): View
    {
        return view('livewire.pages.entdecken');
    }
}
