<?php

use App\Enums\GuideMode;
use App\Models\Capture;
use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use App\Services\Captures\CaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
 * Gefaelschte KI-Antworten fuer Pipeline-, Stadt- und Seitentests (nie echte Aufrufe).
 */

if (! function_exists('claudeJson')) {
    function claudeJson(array $json, array $usage = ['input_tokens' => 1000, 'output_tokens' => 200], string $stop = 'end_turn'): array
    {
        return ['content' => [['type' => 'text', 'text' => json_encode($json, JSON_UNESCAPED_UNICODE)]], 'usage' => $usage, 'stop_reason' => $stop];
    }
}

if (! function_exists('recognitionJson')) {
    function recognitionJson(float $confidence = 0.95): array
    {
        return [
            'photos' => [['index' => 0, 'type' => 'artwork', 'text' => null], ['index' => 1, 'type' => 'label', 'text' => 'Gustav Klimt, Der Kuss, 1908/09']],
            'title' => 'Der Kuss', 'artist' => 'Gustav Klimt', 'artist_life_dates' => '1862 bis 1918', 'dating' => '1908/09',
            'technique' => 'Öl auf Leinwand', 'dimensions' => '180 x 180 cm', 'inventory_number' => 'Inv. 912', 'epoch' => 'Jugendstil',
            'confidence' => $confidence, 'alternatives' => [['title' => 'Die Umarmung', 'artist' => 'Gustav Klimt', 'reason' => 'Ähnliches Motiv']], 'notes' => null,
        ];
    }
}

if (! function_exists('researchJson')) {
    function researchJson(): array
    {
        return [
            'summary' => 'Der Kuss entstand 1908/09 und hängt im Belvedere.',
            'facts' => [['statement' => 'Der Kuss wurde 1908 vom Staat gekauft.', 'source_url' => 'https://www.belvedere.at/kuss']],
            'quotes' => [['text' => 'Alle Kunst ist erotisch.', 'original' => null, 'speaker' => 'Gustav Klimt', 'context' => 'Zugeschrieben', 'source_url' => 'https://example.org/zitat']],
            'sources' => [['url' => 'https://www.belvedere.at/kuss', 'title' => 'Belvedere: Der Kuss', 'kind' => 'museum']],
            'existing_guides' => [], 'vienna_links' => [['title' => 'Secession', 'reason' => 'Klimt war Gründungsmitglied']],
            'artist_born' => 1862, 'artist_died' => 1918, 'wikidata_id' => 'Q698487',
        ];
    }
}

if (! function_exists('scriptJson')) {
    function scriptJson(): array
    {
        return [
            'segments' => [
                ['role' => 'narrator', 'text' => 'Vor dir hängt das wohl bekannteste Bild Wiens.'],
                ['role' => 'quote', 'text' => 'Alle Kunst ist erotisch.'],
                ['role' => 'narrator', 'text' => 'Das Blattgold stammt aus der Werkstatt seines Vaters.'],
            ],
            'fact_sheet' => [
                'key_facts' => [['label' => 'Entstanden', 'value' => '1908/09'], ['label' => 'Technik', 'value' => 'Öl und Blattgold auf Leinwand']],
                'key_statements' => ['Höhepunkt der Goldenen Periode.'],
                'cross_references' => ['Egon Schiele, Umarmung'],
                'sections' => [
                    'artist' => ['Klimt führte die Wiener Secession an.'], 'provenance' => ['Vom Staat 1908 gekauft', 'seit 1908 im Belvedere'],
                    'interpretation' => ['Verschmelzung zweier Menschen in Gold.'], 'epoch' => ['Jugendstil um 1900.'], 'look' => ['Die Füße am Rand der Wiese.'],
                    'quote_text' => 'Alle Kunst ist erotisch.', 'quote_speaker' => 'Gustav Klimt', 'quote_context' => 'zugeschrieben',
                    'anecdote' => 'Die Kaufsumme war die höchste je für ein lebendes Werk.', 'curator_text' => 'Das Bild ist ein Versprechen.', 'curator_name' => 'Kuratorin Belvedere', 'more' => [],
                ],
            ],
        ];
    }
}

if (! function_exists('knowledgeJson')) {
    function knowledgeJson(): array
    {
        return ['artist_summary' => 'Goldene Periode, Blattgold, Staatskauf 1908.', 'epoch_summary' => 'Jugendstil als Gesamtkunstwerk.'];
    }
}

if (! function_exists('captureWithPhotos')) {
    function captureWithPhotos(User $user, ?Museum $museum = null, GuideMode $mode = GuideMode::Full): Capture
    {
        $visit = Visit::factory()->for($user)->create(['museum_id' => $museum?->getKey()]);

        return app(CaptureService::class)->create($user, $visit, [UploadedFile::fake()->image('werk.jpg', 600, 400), UploadedFile::fake()->image('schild.jpg', 400, 300)], $mode);
    }
}
