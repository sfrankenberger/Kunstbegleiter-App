<?php

namespace App\Services\Ai;

/**
 * Kosten eines Aufrufs in Cent aus Tokens. Die Preise je Modell (USD je Million Tokens) stehen in
 * config/museumguide.php unter `pricing` und werden beim Bau der Pipeline (Etappe 3) mit aktuellen Werten
 * befuellt; ohne Eintrag ist der Preis 0 und die Uebersicht zeigt nur Tokens.
 */
class Pricing
{
    public static function cents(string $model, int $inputTokens, int $outputTokens): int
    {
        $prices = (array) config('museumguide.pricing.'.$model, []);
        $input = (float) ($prices['input_per_million'] ?? 0);
        $output = (float) ($prices['output_per_million'] ?? 0);
        $rate = (float) config('museumguide.pricing_eur_per_usd', 1.0);

        $usd = $inputTokens / 1_000_000 * $input + $outputTokens / 1_000_000 * $output;

        return (int) round($usd * $rate * 100);
    }
}
