<?php

namespace App\Livewire\Pages;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\City;
use App\Models\Epoch;
use App\Models\Museum;
use App\Models\RoomText;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reiter "Archiv": alle Aufnahmen mit Werk, neueste zuerst, Suche ueber Titel und Kuenstler, Filter nach Museum,
 * Stadt, Kuenstler und Epoche (14.10.2026, nur Werte aus den eigenen Aufnahmen). Darunter Besuche mit Link zum
 * Rueckblick und Raumtexte.
 */
#[Layout('components.layouts.app', ['title' => 'Archiv'])]
class Archiv extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'museum')]
    public ?int $museumId = null;

    #[Url(as: 'stadt')]
    public ?int $cityId = null;

    #[Url(as: 'kuenstler')]
    public ?int $artistId = null;

    #[Url(as: 'epoche')]
    public ?int $epochId = null;

    public function resetFilters(): void
    {
        $this->reset('search', 'museumId', 'cityId', 'artistId', 'epochId');
    }

    public function render(): View
    {
        $user = auth()->user();

        $captures = Capture::query()
            ->whereBelongsTo($user)
            ->whereNotNull('artwork_id')
            ->with(['artwork.artist', 'artwork.museum', 'visit', 'photos'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->whereHas('artwork', fn ($q) => $q->where('title', 'like', $term)
                    ->orWhereHas('artist', fn ($a) => $a->where('name', 'like', $term)));
            })
            ->when($this->museumId, fn ($q) => $q->whereHas('artwork', fn ($a) => $a->where('museum_id', $this->museumId)))
            ->when($this->cityId, fn ($q) => $q->whereHas('artwork.museum', fn ($m) => $m->where('city_id', $this->cityId)))
            ->when($this->artistId, fn ($q) => $q->whereHas('artwork', fn ($a) => $a->where('artist_id', $this->artistId)))
            ->when($this->epochId, fn ($q) => $q->whereHas('artwork', fn ($a) => $a->where('epoch_id', $this->epochId)))
            ->latest()
            ->limit(100)
            ->get();

        // Filterwerte nur aus den eigenen Aufnahmen
        $artworks = Artwork::query()->whereIn('id', Capture::query()->whereBelongsTo($user)->whereNotNull('artwork_id')->select('artwork_id'));
        $museumIds = (clone $artworks)->whereNotNull('museum_id')->distinct()->pluck('museum_id');
        $filtered = $this->museumId || $this->cityId || $this->artistId || $this->epochId || trim($this->search) !== '';

        // Raumtexte (07.10.2026): zum Nachlesen, Suche ueber Titel und Text
        $roomTexts = RoomText::query()
            ->whereBelongsTo($user)
            ->with('museum')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('text', 'like', $term));
            })
            ->when($this->museumId, fn ($q) => $q->where('museum_id', $this->museumId))
            ->latest()
            ->limit(50)
            ->get();

        return view('livewire.pages.archiv', [
            'captures' => $captures,
            'roomTexts' => $roomTexts,
            'filtered' => $filtered,
            'visits' => $filtered ? collect() : Visit::query()->whereBelongsTo($user)->whereHas('captures', fn ($q) => $q->whereNotNull('artwork_id'))->withCount(['captures' => fn ($q) => $q->whereNotNull('artwork_id')])->with('museum')->latest()->limit(20)->get(),
            'museums' => Museum::query()->whereIn('id', $museumIds)->orderBy('name')->get(['id', 'name']),
            'cities' => City::query()->whereIn('id', Museum::query()->whereIn('id', $museumIds)->select('city_id'))->orderBy('name')->get(['id', 'name']),
            'artists' => Artist::query()->whereIn('id', (clone $artworks)->whereNotNull('artist_id')->select('artist_id'))->orderBy('name')->get(['id', 'name']),
            'epochs' => Epoch::query()->whereIn('id', (clone $artworks)->whereNotNull('epoch_id')->select('epoch_id'))->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }
}
