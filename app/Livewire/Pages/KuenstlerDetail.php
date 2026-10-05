<?php

namespace App\Livewire\Pages;

use App\Models\Artist;
use App\Models\Capture;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kuenstler-Seite (docs/konzept.md Abschnitt 22): Portrait, Lebensdaten, Leben, Stil, Rezeption, wichtigste Werke
 * (Profil aus ProfileArtist), Quellen, dazu die eigenen Aufnahmen zu diesem Kuenstler. Der Audio-Player laeuft
 * beim Wechsel hierher weiter (Layout, @persist).
 */
#[Layout('components.layouts.app', ['title' => 'Künstler'])]
class KuenstlerDetail extends Component
{
    public Artist $artist;

    public function mount(Artist $artist): void
    {
        $this->artist = $artist;
    }

    public function render(): View
    {
        $user = auth()->user();
        $userIds = array_values(array_filter([$user?->getKey(), $user?->partner()?->getKey()]));

        $captures = Capture::query()
            ->whereIn('user_id', $userIds)
            ->whereHas('artwork', fn ($q) => $q->where('artist_id', $this->artist->getKey()))
            ->with(['artwork.museum', 'photos'])
            ->latest()
            ->get();

        return view('livewire.pages.kuenstler-detail', [
            'profile' => is_array($this->artist->profile) ? $this->artist->profile : [],
            'captures' => $captures,
        ]);
    }
}
