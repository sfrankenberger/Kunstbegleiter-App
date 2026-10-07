<?php

namespace App\Livewire\Pages;

use App\Models\RoomText;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Raumtext nachlesen (docs/konzept.md Abschnitt 26): Foto, abgelesener Text, die Werke, die ihn genutzt haben.
 */
#[Layout('components.layouts.app', ['title' => 'Raumtext'])]
class RaumText extends Component
{
    use AuthorizesRequests;

    public RoomText $roomText;

    public function mount(RoomText $roomText): void
    {
        $this->authorize('view', $roomText);
        $this->roomText = $roomText;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->roomText);
        $this->roomText->delete();

        $this->redirectRoute('archiv', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pages.raumtext', [
            'captures' => $this->roomText->captures()->with(['artwork.artist', 'photos'])->latest()->get(),
        ]);
    }
}
