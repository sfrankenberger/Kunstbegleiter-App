<?php

namespace App\Contracts;

/**
 * Ergebnis eines TTS-Aufrufs: Audiodaten, Zeichen (fuer die Kosten) und Dauer, falls der Anbieter sie liefert.
 */
final class TtsResult
{
    public function __construct(
        public readonly string $audio,
        public readonly int $characters,
        public readonly ?int $durationSeconds = null,
        public readonly string $mimeType = 'audio/mpeg',
    ) {}
}
