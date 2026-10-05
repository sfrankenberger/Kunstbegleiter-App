<?php

namespace App\Providers;

use App\Contracts\PlacesClient;
use App\Contracts\TtsProvider;
use App\Models\Passkey;
use App\Services\Places\FakePlacesClient;
use App\Services\Places\GooglePlacesClient;
use App\Services\Tts\FakeTtsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Anbieter hinter Schnittstellen (config/museumguide.php): in der Bauphase die Fakes, Etappe 2 und 3 bringen
        // Google Places und einen echten TTS-Anbieter.
        $this->app->bind(TtsProvider::class, fn (): TtsProvider => match ((string) config('museumguide.tts.provider')) {
            default => new FakeTtsProvider,
        });

        $this->app->bind(PlacesClient::class, fn (): PlacesClient => match ((string) config('museumguide.places.provider')) {
            'google' => new GooglePlacesClient,
            default => new FakePlacesClient,
        });
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Passkeys::usePasskeyModel(Passkey::class);
    }
}
