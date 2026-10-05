<?php

namespace App\Livewire\Pages;

use App\Models\Artist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reiter "Kuenstler" (docs/konzept.md Abschnitt 22, statt Entdecken): alle Kuenstler, zu denen es Aufnahmen gibt,
 * mit Portrait, Lebensdaten und Zahl der Werke, Suche nach Name. Die eigenen zuerst, dann die des Partners.
 */
#[Layout('components.layouts.app', ['title' => 'Künstler'])]
class Kuenstler extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function render(): View
    {
        $user = auth()->user();
        $userIds = array_values(array_filter([$user?->getKey(), $user?->partner()?->getKey()]));

        $artists = Artist::query()
            ->whereHas('artworks.captures', fn ($q) => $q->whereIn('user_id', $userIds))
            ->withCount(['artworks as works_count' => fn ($q) => $q->whereHas('captures', fn ($c) => $c->whereIn('user_id', $userIds))])
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('sort_name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('livewire.pages.kuenstler', ['artists' => $artists]);
    }
}
