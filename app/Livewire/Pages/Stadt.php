<?php

namespace App\Livewire\Pages;

use App\Enums\GuideMode;
use App\Models\Capture;
use App\Models\User;
use App\Services\Captures\CaptureService;
use App\Services\Places\WikiPlaces;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

/**
 * Reiter "Stadt" (docs/konzept.md Abschnitt 23): Begleiter unterwegs. Orte in der Naehe aus Wikidata (Statue,
 * Gebaeude, Platz, Kirche, Denkmal) mit Bild und Entfernung, Ort eingeben, oder Foto machen und erkennen lassen.
 * Ein Tipp auf einen Ort startet den Guide (schnell oder ausfuehrlich) und fuehrt zur Ort-Seite. Kein Archiv,
 * nur "Zuletzt angesehen".
 */
#[Layout('components.layouts.app', ['title' => 'Stadt'])]
class Stadt extends Component
{
    use WithFileUploads;

    /** @var list<array<string, mixed>> */
    public array $nearby = [];

    public bool $searched = false;

    public ?float $lat = null;

    public ?float $lng = null;

    public string $locationError = '';

    public string $manualName = '';

    public bool $showManual = false;

    public bool $showPhoto = false;

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public string $uploadError = '';

    public bool $full = false;

    public function mount(): void
    {
        $this->full = $this->user()->default_mode === GuideMode::Full;
    }

    public function locate(float $lat, float $lng, ?int $accuracy = null): void
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->locationError = '';
        $this->searched = true;

        try {
            $this->nearby = app(WikiPlaces::class)->nearby($lat, $lng, (int) config('museumguide.places.poi_radius_m', 400));
        } catch (Throwable $e) {
            report($e);
            $this->nearby = [];
        }

        if ($this->nearby === []) {
            $this->locationError = 'In der Nähe kennt Wikidata nichts. Ort eingeben oder Foto machen.';
        }
    }

    public function locationFailed(string $message = ''): void
    {
        $this->searched = true;
        $this->locationError = $message !== '' ? $message : 'Kein Standort. Ort eingeben oder Foto machen.';
        $this->showManual = true;
    }

    public function choose(string $wikidataId): void
    {
        $hit = collect($this->nearby)->firstWhere('wikidata_id', $wikidataId);

        if ($hit === null) {
            return;
        }

        $this->startFor($hit);
    }

    public function chooseManual(): void
    {
        $this->validate(['manualName' => ['required', 'string', 'min:2', 'max:150']]);
        $hit = app(WikiPlaces::class)->search($this->manualName);

        if ($hit === null) {
            $this->addError('manualName', 'Wikidata kennt diesen Ort nicht. Anders schreiben oder ein Foto machen.');

            return;
        }

        $this->startFor($hit);
    }

    public function updatedPhotos(): void
    {
        $this->uploadError = '';
        $max = (int) config('museumguide.photos.max_per_capture', 3);
        $bytes = (int) config('museumguide.photos.max_bytes', 8 * 1024 * 1024);

        try {
            $this->validate(['photos' => ['required', 'array', 'min:1', 'max:'.$max], 'photos.*' => ['image', 'max:'.(int) ($bytes / 1024)]], [
                'photos.max' => 'Höchstens '.$max.' Fotos je Aufnahme.',
                'photos.*.image' => 'Nur Bilder sind erlaubt.',
                'photos.*.max' => 'Ein Foto ist zu groß.',
            ]);
        } catch (ValidationException $e) {
            $this->uploadError = collect($e->errors())->flatten()->first() ?? 'Upload fehlgeschlagen.';
            $this->photos = [];
        }
    }

    public function createFromPhotos(): void
    {
        if ($this->photos === []) {
            $this->uploadError = 'Bitte zuerst ein Foto aufnehmen.';

            return;
        }

        try {
            $capture = app(CaptureService::class)->createForPlacePhoto($this->user(), $this->photos, $this->lat, $this->lng, $this->full ? GuideMode::Full : GuideMode::Quick);
        } catch (InvalidArgumentException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->photos = [];
        $this->redirectRoute('aufnahme', ['capture' => $capture], navigate: true);
    }

    public function render(): View
    {
        $recent = Capture::query()
            ->whereBelongsTo($this->user())
            ->whereNotNull('place_id')
            ->with('place')
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.pages.stadt', ['recent' => $recent]);
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function startFor(array $hit): void
    {
        $wiki = app(WikiPlaces::class);
        $place = $wiki->placeFromHit($hit);
        $capture = app(CaptureService::class)->createForPlace($this->user(), $place, $this->full ? GuideMode::Full : GuideMode::Quick, $this->lat, $this->lng);

        $this->redirectRoute('aufnahme', ['capture' => $capture], navigate: true);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
