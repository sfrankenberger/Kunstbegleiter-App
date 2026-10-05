<?php

namespace App\Livewire\Pages;

use App\Enums\PhotoType;
use App\Models\Capture;
use App\Models\CapturePhoto;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seite einer Aufnahme (Werk-Seite, docs/grundgeruest.md): Fotos mit korrigierbarem Typ, Stand der Pipeline,
 * spaeter Player, Fact Sheet und Skript (Etappe 3). Loeschen in den Papierkorb.
 */
#[Layout('components.layouts.app', ['title' => 'Aufnahme'])]
class Aufnahme extends Component
{
    public Capture $capture;

    public function mount(Capture $capture): void
    {
        $this->authorize('view', $capture);
        $this->capture = $capture;
    }

    public function setType(int $photoId, string $type): void
    {
        $this->authorize('update', $this->capture);
        $photo = $this->capture->photos()->whereKey($photoId)->first();

        if ($photo instanceof CapturePhoto && PhotoType::tryFrom($type) !== null) {
            $photo->update(['type' => PhotoType::from($type)]);
        }
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->capture);
        $this->capture->delete();

        $this->redirectRoute('jetzt', navigate: true);
    }

    public function render(): View
    {
        $this->capture->load(['photos', 'artwork.artist', 'visit.museum']);

        return view('livewire.pages.aufnahme', [
            'photoTypes' => PhotoType::cases(),
        ]);
    }
}
