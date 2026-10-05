<?php

namespace App\Services\Pipeline;

use App\Models\Artist;
use App\Models\Capture;
use App\Models\Epoch;
use App\Models\KnowledgeItem;
use App\Models\Research;

/**
 * Schritt 4 (BuildContext): Vorwissen-Profil, Werke dieses Besuchs (auch des gekoppelten Nutzers), bisherige
 * Eintraege im Lerngedaechtnis zu Kuenstler und Epoche (hoechstens zwei Rueckbezuege), Recherche und Zitate.
 */
class ContextBuilder
{
    /**
     * @return array{knowledge_profile: string, research: string, quotes: array<int, mixed>, visit_context: string, knowledge_context: string}
     */
    public function build(Capture $capture): array
    {
        $user = $capture->user;
        $artwork = $capture->artwork;
        $research = $artwork?->research;
        $max = (int) config('museumguide.script.max_back_references', 2);

        $visitCaptures = $capture->visit?->captures()
            ->whereKeyNot($capture->getKey())
            ->whereNotNull('artwork_id')
            ->with('artwork.artist')
            ->get() ?? collect();

        $visitContext = $visitCaptures->map(fn (Capture $c): string => ($c->artwork?->artist?->name ? $c->artwork->artist->name.': ' : '').($c->artwork?->title ?? '').($c->user_id !== $user->getKey() ? ' (vom Partner fotografiert)' : ''))->filter()->implode('; ');

        $knowledge = KnowledgeItem::query()->whereBelongsTo($user)
            ->where(function ($q) use ($artwork): void {
                $q->where(fn ($a) => $a->where('subject_type', Artist::class)->where('subject_id', $artwork?->artist_id ?? 0));
                $q->orWhere(fn ($e) => $e->where('subject_type', Epoch::class)->where('subject_id', $artwork?->epoch_id ?? 0));
            })
            ->whereNot('capture_id', $capture->getKey())
            ->latest('told_at')
            ->limit($max)
            ->get()
            ->map(fn (KnowledgeItem $item): string => $item->told_at->translatedFormat('F Y').': '.$item->summary)
            ->implode("\n");

        return [
            'knowledge_profile' => filled($user->knowledge_profile) ? (string) $user->knowledge_profile : 'Austria Guide in Wien, breites Vorwissen zur Kunstgeschichte.',
            'research' => self::researchText($research),
            'quotes' => self::quotes($research),
            'visit_context' => $visitContext !== '' ? $visitContext : 'noch nichts',
            'knowledge_context' => $knowledge !== '' ? $knowledge : 'nichts',
        ];
    }

    public static function researchText(?Research $research): string
    {
        if ($research === null) {
            return 'keine';
        }

        $facts = collect(self::meta($research, '_facts'))->map(fn (mixed $f): string => is_array($f) ? '- '.($f['statement'] ?? '').' (Quelle: '.($f['source_url'] ?? '').')' : '')->filter()->implode("\n");

        return trim($research->summary."\n\nBelegte Aussagen:\n".$facts);
    }

    /**
     * @return array<int, mixed>
     */
    public static function quotes(?Research $research): array
    {
        return $research === null ? [] : self::meta($research, '_quotes');
    }

    /**
     * Fakten, Zitate und Wien-Bezuege liegen als Sondereintraege in research.sources.
     *
     * @return array<int, mixed>
     */
    public static function meta(Research $research, string $key): array
    {
        foreach ((array) $research->sources as $row) {
            if (is_array($row) && array_key_exists($key, $row)) {
                return (array) $row[$key];
            }
        }

        return [];
    }

    /**
     * Echte Quellen ohne die Sondereintraege.
     *
     * @return list<array{url: string, title: ?string, kind: string}>
     */
    public static function sources(Research $research): array
    {
        return collect((array) $research->sources)->filter(fn (mixed $row): bool => is_array($row) && isset($row['url']))->values()->all();
    }
}
