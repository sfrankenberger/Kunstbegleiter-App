<?php

namespace App\Livewire\Pages;

use App\Enums\PhotoType;
use App\Models\AudioGuide;
use App\Models\Capture;
use App\Models\CapturePhoto;
use App\Services\Pipeline\ArtworkMatcher;
use App\Services\Pipeline\ContextBuilder;
use App\Services\Pipeline\Pipeline;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Seite einer Aufnahme (Werk-Seite, docs/grundgeruest.md): Fortschritt der Pipeline (alle paar Sekunden neu
 * geladen, solange sie laeuft), Rueckfrage bei unsicherer Erkennung (Vorschlag oder Alternative bestaetigen,
 * Titel eintippen), Player mit 15 Sekunden zurueck und Tempo, Fact Sheet, "Fuer deine Gaeste", Quellen, Skript
 * zum Mitlesen, Daumen und Schwierigkeit, "Erneut versuchen", Fotos mit korrigierbarem Typ, Papierkorb.
 */
#[Layout('components.layouts.app', ['title' => 'Aufnahme'])]
class Aufnahme extends Component
{
    public Capture $capture;

    public string $manualTitle = '';

    public string $manualArtist = '';

    public bool $showScript = false;

    public bool $showPhotos = false;

    public string $notice = '';

    public function mount(Capture $capture): void
    {
        $this->authorize('view', $capture);
        $this->capture = $capture;
        $this->showPhotos = ! $capture->isDone();
        $this->showScript = $capture->isQuick();
    }

    /**
     * Rueckfrage: Vorschlag (-1), eine Alternative (Index) oder die Handeingabe bestaetigen.
     */
    public function confirm(int $alternative = -1): void
    {
        $this->authorize('update', $this->capture);

        if (! $this->capture->needs_confirmation) {
            return;
        }

        $recognition = (array) ($this->capture->recognition ?? []);

        if ($alternative >= 0) {
            $chosen = (array) (($recognition['alternatives'] ?? [])[$alternative] ?? []);
            $recognition = array_merge($recognition, ['title' => $chosen['title'] ?? null, 'artist' => $chosen['artist'] ?? null]);
        }

        if (blank($recognition['title'] ?? null)) {
            $this->notice = 'Bitte einen Titel angeben.';

            return;
        }

        $this->startWith($recognition);
    }

    public function confirmManual(): void
    {
        $this->authorize('update', $this->capture);
        $title = trim($this->manualTitle);

        if ($title === '') {
            $this->notice = 'Bitte den Titel eintippen.';

            return;
        }

        $recognition = array_merge((array) ($this->capture->recognition ?? []), ['title' => $title, 'artist' => trim($this->manualArtist) !== '' ? trim($this->manualArtist) : null]);
        $this->startWith($recognition);
    }

    /**
     * Aus der Schnellstufe den ausfuehrlichen Guide nachbestellen.
     */
    public function upgrade(): void
    {
        $this->authorize('update', $this->capture);

        try {
            app(Pipeline::class)->upgrade($this->capture);
            $this->notice = '';
        } catch (RuntimeException $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function retry(): void
    {
        $this->authorize('update', $this->capture);

        try {
            app(Pipeline::class)->retry($this->capture);
            $this->notice = '';
        } catch (RuntimeException $e) {
            Pipeline::fail($this->capture, $e->getMessage());
        }
    }

    public function feedback(string $value): void
    {
        $this->authorize('update', $this->capture);
        $guide = $this->capture->audioGuide;

        if ($guide instanceof AudioGuide && in_array($value, ['up', 'down'], true)) {
            $guide->update(['feedback' => $guide->feedback === $value ? null : $value]);
        }
    }

    public function difficulty(string $value): void
    {
        $this->authorize('update', $this->capture);
        $guide = $this->capture->audioGuide;

        if ($guide instanceof AudioGuide && in_array($value, ['too_easy', 'right', 'too_hard'], true)) {
            $guide->update(['difficulty_feedback' => $guide->difficulty_feedback === $value ? null : $value]);
        }
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
        $this->capture->refresh()->load(['photos', 'artwork.artist', 'artwork.epoch', 'artwork.research', 'visit.museum', 'audioGuide', 'factSheet']);
        $research = $this->capture->artwork?->research;

        return view('livewire.pages.aufnahme', [
            'photoTypes' => PhotoType::cases(),
            'sources' => $research !== null ? ContextBuilder::sources($research) : [],
            'segments' => collect($this->capture->audioGuide?->script ?? [])->filter(fn (mixed $s): bool => is_array($s) && filled($s['text'] ?? null))->values(),
            'costCents' => (int) $this->capture->aiCalls()->sum('cost_cents'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $recognition
     */
    private function startWith(array $recognition): void
    {
        $this->capture->forceFill(['recognition' => $recognition])->save();
        app(ArtworkMatcher::class)->attach($this->capture, $recognition);

        try {
            app(Pipeline::class)->continueAfterRecognition($this->capture);
            $this->notice = '';
        } catch (RuntimeException $e) {
            Pipeline::fail($this->capture, $e->getMessage());
        }

        $this->manualTitle = '';
        $this->manualArtist = '';
    }
}
