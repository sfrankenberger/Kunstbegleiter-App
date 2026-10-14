<?php

namespace App\Services\Questions;

use App\Enums\AiPurpose;
use App\Models\Capture;
use App\Models\CaptureQuestion;
use App\Services\Ai\ClaudeClient;
use App\Services\Pipeline\ContextBuilder;
use App\Services\Pipeline\Voice;
use App\Support\Prompts;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Nachfragen zum Werk oder Ort (Sebastian, 14.10.2026, docs/konzept.md Abschnitt 25): ein schneller Aufruf ohne
 * Websuche mit allem, was die Aufnahme schon weiss (Recherche, Schild- und Raumtext, Guide, fruehere Nachfragen),
 * dann die Erzaehlerstimme. Ziel: Antwort in unter 15 Sekunden.
 */
class QuestionService
{
    public function ask(Capture $capture, string $question): CaptureQuestion
    {
        $question = trim(Str::limit(trim($question), 500, ''));

        if ($question === '') {
            throw new RuntimeException('Bitte eine Frage eingeben.');
        }

        $capture->loadMissing(['user', 'artwork.artist', 'artwork.research', 'artwork.museum.city', 'place.city', 'place.research', 'roomText', 'photos', 'quickGuide', 'fullGuide', 'factSheets']);
        $before = (int) $capture->aiCalls()->sum('cost_cents');

        $answer = trim(app(ClaudeClient::class)->text(AiPurpose::Question, [['role' => 'user', 'content' => $question]], [
            'system' => Prompts::render('question', [
                'subject' => $this->subject($capture),
                'knowledge_profile' => filled($capture->user->knowledge_profile) ? (string) $capture->user->knowledge_profile : 'Austria Guide in Wien, breites Vorwissen zur Kunstgeschichte.',
                'context' => $this->context($capture),
                'history' => $this->history($capture),
            ]),
            'effort' => 'low',
            'max_tokens' => 1200,
        ], $capture->user, $capture));

        if ($answer === '') {
            throw new RuntimeException('Darauf kam gerade keine Antwort. Bitte noch einmal fragen.');
        }

        $entry = $capture->questions()->create([
            'user_id' => $capture->user_id,
            'question' => $question,
            'answer' => $answer,
        ]);

        $audio = app(Voice::class)->speak($capture, $answer, 'captures/'.$capture->getKey().'/question-'.$entry->getKey().'.mp3');

        $entry->forceFill([
            'audio_path' => $audio['path'] ?? null,
            'duration_seconds' => $audio['duration'] ?? null,
            'cost_cents' => max(0, (int) $capture->aiCalls()->sum('cost_cents') - $before),
        ])->save();

        return $entry;
    }

    private function subject(Capture $capture): string
    {
        if ($capture->place !== null) {
            $p = $capture->place;

            return 'Ort: '.$p->name.' ('.$p->kindLabel().($p->city ? ', '.$p->city->name : '').')'.($p->architect ? ', Architekt oder Bildhauer: '.$p->architect : '').($p->built ? ', '.$p->built : '');
        }

        $a = $capture->artwork;

        if ($a === null) {
            return 'Werk: '.($capture->recognition['title'] ?? 'noch nicht sicher erkannt').(filled($capture->recognition['artist'] ?? null) ? ' von '.$capture->recognition['artist'] : '');
        }

        return 'Werk: '.$a->title.' von '.($a->artist?->name ?? 'unbekannt').($a->dating ? ', '.$a->dating : '').($a->technique ? ', '.$a->technique : '').($a->museum ? ', '.$a->museum->name.($a->museum->city ? ' ('.$a->museum->city->name.')' : '') : '');
    }

    private function context(Capture $capture): string
    {
        $research = $capture->place?->research ?? $capture->artwork?->research;
        $guide = $capture->fullGuide ?? $capture->quickGuide;
        $sheet = $capture->factSheets->sortByDesc('id')->first();

        $parts = [
            'Recherche: '.ContextBuilder::researchText($research),
            'Schild- und Raumtexte: '.$capture->labelText(),
            'Kernaussagen: '.implode(' ', array_map('strval', (array) ($sheet?->key_statements ?? []))),
            'Bisheriger Guide: '.Str::limit($guide?->scriptText() ?? 'keiner', 6000),
        ];

        return implode("\n\n", $parts);
    }

    private function history(Capture $capture): string
    {
        return $capture->questions()->latest('id')->limit(5)->get()->reverse()
            ->map(fn (CaptureQuestion $q): string => 'Frage: '.$q->question."\nAntwort: ".$q->answer)
            ->implode("\n\n") ?: 'keine';
    }
}
