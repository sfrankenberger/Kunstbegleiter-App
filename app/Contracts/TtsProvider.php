<?php

namespace App\Contracts;

/**
 * Text-zu-Sprache hinter einer Schnittstelle (docs/grundgeruest.md): ElevenLabs, OpenAI TTS oder Google sind
 * austauschbar, Tests benutzen den FakeTtsProvider. Welcher Anbieter laeuft, steht in config/museumguide.php.
 */
interface TtsProvider
{
    /**
     * Ein Segment einsprechen. Liefert die Audiodaten (MP3) als Bytes.
     *
     * @param  string  $voice  Kennung der Stimme beim Anbieter (Erzaehler, zweite Stimme, Zitat)
     */
    public function synthesize(string $text, string $voice): TtsResult;

    /**
     * Mehrere Segmente auf einmal (zwei Sprecher), moeglichst parallel. Reihenfolge wie die Eingabe.
     *
     * @param  list<array{text: string, voice: string}>  $segments
     * @return list<TtsResult>
     */
    public function synthesizeMany(array $segments): array;

    /**
     * Verfuegbare Stimmen: Kennung => Anzeigename.
     *
     * @return array<string, string>
     */
    public function voices(): array;

    public function name(): string;
}
