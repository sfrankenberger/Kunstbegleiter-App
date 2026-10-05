<?php

namespace App\Livewire\Pages;

use App\Enums\GuideMode;
use App\Enums\PhotoType;
use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use App\Services\Captures\CaptureService;
use App\Services\Visits\VisitService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

/**
 * Reiter "Jetzt" (docs/grundgeruest.md, Bildschirme): Besuch starten (Standort aus dem Browser, Museen in der
 * Naehe, Auswahl oder "Ich bin im ..."), aktueller Besuch mit Restzeit, Kamera-Knopf mit 1 bis 3 Fotos (im
 * Browser verkleinert, dann hochgeladen), Werke dieses Besuchs. Die Pipeline startet ab Etappe 3 beim Anlegen.
 */
#[Layout('components.layouts.app', ['title' => 'Jetzt'])]
class Jetzt extends Component
{
    use WithFileUploads;

    /** @var list<array{place_id: string, name: string, address: ?string, lat: float, lng: float, website: ?string, distance_m: int}> */
    public array $nearby = [];

    public bool $searched = false;

    public ?float $lat = null;

    public ?float $lng = null;

    public ?int $accuracy = null;

    public string $locationError = '';

    public string $manualName = '';

    public string $manualCity = '';

    public bool $showManual = false;

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public string $uploadError = '';

    /** Ausfuehrlichen Guide gleich erstellen (sonst Schnellstufe, Standard aus dem Profil). */
    public bool $full = false;

    public function mount(): void
    {
        $this->full = $this->user()->default_mode === GuideMode::Full;
    }

    /**
     * Standort vom Browser: Museen in der Naehe suchen. Genau ein Treffer startet den Besuch sofort.
     */
    public function locate(float $lat, float $lng, ?int $accuracy = null): void
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->accuracy = $accuracy;
        $this->locationError = '';
        $this->searched = true;

        try {
            $this->nearby = app(VisitService::class)->nearbyMuseums($lat, $lng);
        } catch (Throwable $e) {
            report($e);
            $this->nearby = [];
            $this->locationError = 'Die Museumssuche hat nicht geantwortet. Du kannst das Museum unten eintippen.';
        }

        if (count($this->nearby) === 1) {
            $this->chooseMuseum($this->nearby[0]['place_id']);
        }
    }

    public function locationFailed(string $message = ''): void
    {
        $this->searched = true;
        $this->nearby = [];
        $this->locationError = $message !== '' ? $message : 'Kein Standort. Du kannst das Museum unten eintippen.';
        $this->showManual = true;
    }

    public function chooseMuseum(string $placeId): void
    {
        $place = collect($this->nearby)->firstWhere('place_id', $placeId);

        if ($place === null) {
            return;
        }

        $service = app(VisitService::class);
        $museum = $service->museumFromPlace($place);
        $this->startOrSwitch($service, $museum);
    }

    public function chooseManual(): void
    {
        $this->validate(['manualName' => ['required', 'string', 'min:2', 'max:150'], 'manualCity' => ['nullable', 'string', 'max:100']]);

        $service = app(VisitService::class);
        $museum = $service->museumByName($this->manualName, $this->manualCity ?: null);
        $this->startOrSwitch($service, $museum);
    }

    public function startWithoutMuseum(): void
    {
        app(VisitService::class)->start($this->user(), $this->lat, $this->lng, $this->accuracy);
        $this->reset('nearby', 'searched', 'showManual', 'manualName', 'manualCity');
    }

    public function endVisit(): void
    {
        app(VisitService::class)->end($this->user());
        $this->reset('nearby', 'searched', 'showManual', 'lat', 'lng', 'accuracy');
    }

    public function updatedPhotos(): void
    {
        $this->uploadError = '';
        $max = (int) config('museumguide.photos.max_per_capture', 3);
        $bytes = (int) config('museumguide.photos.max_bytes', 8 * 1024 * 1024);

        try {
            $this->validate([
                'photos' => ['required', 'array', 'min:1', 'max:'.$max],
                'photos.*' => ['image', 'max:'.(int) ($bytes / 1024)],
            ], [
                'photos.max' => 'Höchstens '.$max.' Fotos je Aufnahme.',
                'photos.*.image' => 'Nur Bilder sind erlaubt.',
                'photos.*.max' => 'Ein Foto ist zu groß.',
            ]);
        } catch (ValidationException $e) {
            $this->uploadError = collect($e->errors())->flatten()->first() ?? 'Upload fehlgeschlagen.';
            $this->photos = [];
        }
    }

    public function createCapture(): void
    {
        $visit = $this->user()->activeVisit();

        if ($visit === null) {
            $this->uploadError = 'Bitte zuerst einen Besuch starten.';

            return;
        }

        if ($this->photos === []) {
            $this->uploadError = 'Bitte zuerst Fotos aufnehmen.';

            return;
        }

        try {
            $capture = app(CaptureService::class)->create($this->user(), $visit, $this->photos, $this->full ? GuideMode::Full : GuideMode::Quick);
        } catch (InvalidArgumentException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->photos = [];

        $this->redirectRoute('aufnahme', ['capture' => $capture], navigate: true);
    }

    public function render(): View
    {
        /** @var Visit|null $visit */
        $visit = $this->user()->activeVisit();

        return view('livewire.pages.jetzt', [
            'visit' => $visit,
            'captures' => $visit?->captures()->with(['artwork.artist', 'photos'])->latest()->get() ?? collect(),
            'photoTypes' => PhotoType::cases(),
            'maxPhotos' => (int) config('museumguide.photos.max_per_capture', 3),
            'maxEdge' => (int) config('museumguide.photos.max_edge', 2000),
        ]);
    }

    private function startOrSwitch(VisitService $service, Museum $museum): void
    {
        $visit = $this->user()->activeVisit();

        if ($visit !== null) {
            $service->setMuseum($visit, $museum);
        } else {
            $service->start($this->user(), $this->lat, $this->lng, $this->accuracy, $museum);
        }

        $this->reset('nearby', 'searched', 'showManual', 'manualName', 'manualCity');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
