<?php

namespace App\Livewire\Pages;

use App\Models\Capture;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reiter "Archiv": alle fertigen Aufnahmen des Nutzers, neueste zuerst, mit Volltextsuche ueber Titel und
 * Kuenstler. Filter nach Stadt, Museum, Epoche und Partner kommen in Etappe 4.
 */
#[Layout('components.layouts.app', ['title' => 'Archiv'])]
class Archiv extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function render(): View
    {
        $captures = Capture::query()
            ->whereBelongsTo(auth()->user())
            ->whereNotNull('artwork_id')
            ->with(['artwork.artist', 'artwork.museum', 'visit'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->whereHas('artwork', fn ($q) => $q->where('title', 'like', $term)
                    ->orWhereHas('artist', fn ($a) => $a->where('name', 'like', $term)));
            })
            ->latest()
            ->limit(100)
            ->get();

        return view('livewire.pages.archiv', ['captures' => $captures]);
    }
}
