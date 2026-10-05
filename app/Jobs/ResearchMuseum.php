<?php

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\Museum;
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
 * Beim Start eines Besuchs (docs/grundgeruest.md, "Beim Oeffnen der App" Punkt 3): kurze Recherche zum Museum
 * (Sonderausstellungen, Sammlung, Online-Sammlung, Audioguide), am Museum gespeichert und fuer alle Aufnahmen
 * wiederverwendet. Hoechstens einmal je Woche neu.
 */
class ResearchMuseum implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $museumId, public readonly ?int $userId = null) {}

    public function handle(): void
    {
        $museum = Museum::query()->with('city')->find($this->museumId);

        if ($museum === null || ($museum->researched_at !== null && $museum->researched_at->gt(now()->subWeek()))) {
            return;
        }

        $result = app(ClaudeClient::class)->structured(AiPurpose::Museum, [['role' => 'user', 'content' => 'Recherchiere das Museum und liefere das JSON.']], Schemas::museum(), [
            'system' => Prompts::render('museum', [
                'museum' => $museum->name,
                'city' => $museum->city?->name ?? '',
                'website' => $museum->website ?? 'unbekannt',
                'today' => now()->format('d.m.Y'),
            ]),
            'web_search' => true,
            'max_searches' => (int) config('museumguide.research.museum_max_searches', 4),
            'effort' => 'low',
            'max_tokens' => 4096,
        ], $this->userId ? User::query()->find($this->userId) : null);

        $museum->forceFill(['research' => $result, 'researched_at' => now()])->save();

        foreach ((array) ($result['exhibitions'] ?? []) as $exhibition) {
            if (! is_array($exhibition) || blank($exhibition['title'] ?? null)) {
                continue;
            }

            $museum->exhibitions()->firstOrCreate(['title' => (string) $exhibition['title']], ['source_url' => $exhibition['url'] ?? null, 'fetched_at' => now()]);
        }
    }
}
