<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\Artist;
use App\Models\User;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\Schemas;
use App\Support\Prompts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kuenstlerprofil (docs/konzept.md Abschnitt 21): Leben, wichtigste Werke, Stil, Rezeption, einmal je Kuenstler
 * recherchiert (Sonnet mit wenigen Websuchen, auch fuer unbekannte Namen), in artists.profile abgelegt und fuer
 * alle Werke des Kuenstlers gezeigt. Laeuft nach dem Guide in der Queue.
 */
class ProfileArtist implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $artistId, public readonly ?int $userId = null, public readonly string $context = '') {}

    public function handle(): void
    {
        $artist = Artist::query()->find($this->artistId);

        if ($artist === null || ($artist->profile_checked_at !== null && $artist->profile_checked_at->gt(now()->subDays((int) config('museumguide.artist_profile.days', 180))))) {
            return;
        }

        $user = $this->userId !== null ? User::query()->find($this->userId) : null;
        $maxSearches = (int) config('museumguide.artist_profile.max_searches', 3);

        $result = app(ClaudeClient::class)->structured(AiPurpose::Artist, [['role' => 'user', 'content' => 'Schreibe das Profil als JSON.']], Schemas::artistProfile(), [
            'system' => Prompts::render('artist', [
                'name' => $artist->name,
                'life_dates' => trim(($artist->born_year ?? '').' bis '.($artist->died_year ?? '')) === 'bis' ? 'unbekannt' : trim(($artist->born_year ?? '').' bis '.($artist->died_year ?? '')),
                'context' => $this->context !== '' ? $this->context : 'keines',
                'max_searches' => $maxSearches,
            ]),
            'web_search' => true,
            'max_searches' => $maxSearches,
            'effort' => 'low',
            'max_tokens' => 4096,
        ], $user);

        $artist->forceFill([
            'profile' => $result,
            'profile_checked_at' => now(),
            'born_year' => $artist->born_year ?? self::year((string) ($result['born'] ?? '')),
            'died_year' => $artist->died_year ?? self::year((string) ($result['died'] ?? '')),
        ])->save();
    }

    private static function year(string $text): ?int
    {
        return preg_match('/\b(1[0-9]{3}|20[0-9]{2})\b/', $text, $m) === 1 ? (int) $m[1] : null;
    }
}
