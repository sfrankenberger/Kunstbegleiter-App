<?php

namespace App\Jobs;

use App\Models\Artwork;
use App\Models\RelatedWork;
use App\Services\Images\WikiImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bilder nachladen (docs/konzept.md Abschnitt 20): Portraet des Kuenstlers, Abbildung des Werks und die
 * Vergleichswerke aus dem Fact Sheet (sections.related_works), jeweils einmal je Kuenstler oder Werk, danach aus
 * der Datenbank. Laeuft nach dem Guide in der Queue, stoert die Zeitvorgaben nicht.
 */
class FetchImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  list<array<string, mixed>>  $relatedWorks
     */
    public function __construct(public readonly int $artworkId, public readonly array $relatedWorks = []) {}

    /**
     * Nachname fuer den Abgleich mit der Wikidata-Beschreibung ("Gemaelde von Gustav Klimt").
     */
    public static function surname(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return (string) end($parts);
    }

    public function handle(WikiImages $images): void
    {
        $artwork = Artwork::query()->with('artist')->find($this->artworkId);

        if ($artwork === null) {
            return;
        }

        $artist = $artwork->artist;

        if ($artist !== null && $artist->portrait_checked_at === null) {
            $found = $images->find($artist->name, $artist->wikidata_id, null);
            $artist->forceFill([
                'portrait_url' => $found['url'] ?? null,
                'portrait_credit' => $found['credit'] ?? null,
                'wikidata_id' => $artist->wikidata_id ?: ($found['wikidata_id'] ?? null),
                'portrait_checked_at' => now(),
            ])->save();
        }

        if ($artwork->image_checked_at === null) {
            $found = $images->find($artwork->title, $artwork->wikidata_id, $artist ? self::surname($artist->name) : null);
            $artwork->forceFill([
                'image_url' => $found['url'] ?? null,
                'image_credit' => $found['credit'] ?? null,
                'wikidata_id' => $artwork->wikidata_id ?: ($found['wikidata_id'] ?? null),
                'image_checked_at' => now(),
            ])->save();
        }

        if ($this->relatedWorks === []) {
            return;
        }

        $artwork->relatedWorks()->delete();
        $max = (int) config('museumguide.images.max_related', 6);

        foreach (array_slice($this->relatedWorks, 0, $max) as $i => $work) {
            $title = trim((string) ($work['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $who = trim((string) ($work['artist'] ?? ''));
            $found = $images->find($title, null, $who !== '' ? self::surname($who) : null);

            RelatedWork::query()->create([
                'artwork_id' => $artwork->getKey(),
                'title' => $title,
                'artist' => $who !== '' ? $who : null,
                'year' => filled($work['year'] ?? null) ? mb_substr((string) $work['year'], 0, 40) : null,
                'reason' => filled($work['reason'] ?? null) ? mb_substr((string) $work['reason'], 0, 500) : null,
                'wikidata_id' => $found['wikidata_id'] ?? null,
                'image_url' => $found['url'] ?? null,
                'image_credit' => $found['credit'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }
}
