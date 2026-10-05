<?php

namespace App\Services\Tts;

use App\Contracts\TtsProvider;
use App\Contracts\TtsResult;

/**
 * Stimme fuer Tests und die Bauphase: keine Kosten, liefert eine leere MP3-Huelle und schaetzt die Dauer
 * (etwa 150 Woerter je Minute).
 */
class FakeTtsProvider implements TtsProvider
{
    public function synthesize(string $text, string $voice): TtsResult
    {
        $words = max(1, str_word_count(strip_tags($text)));

        return new TtsResult(
            audio: "ID3\x03\x00\x00\x00\x00\x00\x00",
            characters: mb_strlen($text),
            durationSeconds: (int) ceil($words / 150 * 60),
        );
    }

    public function synthesizeMany(array $segments): array
    {
        return array_map(fn (array $s): TtsResult => $this->synthesize($s['text'], $s['voice']), $segments);
    }

    public function voices(): array
    {
        return [
            'narrator' => 'Erzählerin (Fake)',
            'second' => 'Zweite Stimme (Fake)',
            'quote' => 'Zitat-Stimme (Fake)',
        ];
    }

    public function name(): string
    {
        return 'fake';
    }
}
