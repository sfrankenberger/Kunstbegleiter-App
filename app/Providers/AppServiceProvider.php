<?php

namespace App\Providers;

use App\Contracts\PlacesClient;
use App\Contracts\TtsProvider;
use App\Models\Passkey;
use App\Services\Places\FakePlacesClient;
use App\Services\Places\GooglePlacesClient;
use App\Services\Tts\FakeTtsProvider;
use App\Support\Secrets;
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
        // Stimmen: bis Etappe 3 immer der Fake (ElevenLabs und OpenAI folgen)
        $this->app->bind(TtsProvider::class, fn (): TtsProvider => new FakeTtsProvider);

        // Ort: "auto" nimmt Google, sobald ein Schluessel gesetzt ist (Admin > Zugaenge oder .env)
        $this->app->bind(PlacesClient::class, function (): PlacesClient {
            $provider = (string) config('museumguide.places.provider', 'auto');

            if ($provider === 'google' || ($provider === 'auto' && Secrets::has('google_places_key'))) {
                return new GooglePlacesClient;
            }

            return new FakePlacesClient;
        });
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Passkeys::usePasskeyModel(Passkey::class);
    }
}
