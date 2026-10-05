<?php

namespace App\Services\Ai;

/**
 * Kosten in Cent aus Tokens, Suchen und Zeichen. Preise in config/museumguide.php unter `pricing` (USD je Million
 * Tokens, Stand 05.10.2026 aus platform.claude.com/docs/en/about-claude/pricing), Umrechnung ueber
 * `pricing_eur_per_usd`. Unbekannte Modelle kosten 0 (die Uebersicht zeigt dann nur Tokens).
 */
class Pricing
{
    public static function cents(string $model, int $inputTokens, int $outputTokens): int
    {
        $prices = self::modelPrices($model);
        $usd = $inputTokens / 1_000_000 * (float) ($prices['input_per_million'] ?? 0)
            + $outputTokens / 1_000_000 * (float) ($prices['output_per_million'] ?? 0);

        return (int) round($usd * self::rate() * 100);
    }

    /**
     * Websuche: USD je 1000 Suchen (config pricing.web_search_per_1000).
     */
    public static function searchCents(int $searches): int
    {
        if ($searches <= 0) {
            return 0;
        }

        return (int) round($searches / 1000 * (float) config('museumguide.pricing.web_search_per_1000', 10) * self::rate() * 100);
    }

    /**
     * Stimme: USD je 1000 Zeichen je Anbieter (config pricing.tts.<anbieter>).
     */
    public static function ttsCents(string $provider, int $characters): int
    {
        if ($characters <= 0) {
            return 0;
        }

        return (int) round($characters / 1000 * (float) config('museumguide.pricing.tts.'.$provider, 0) * self::rate() * 100);
    }

    /**
     * @return array<string, float>
     */
    private static function modelPrices(string $model): array
    {
        $table = (array) config('museumguide.pricing.models', []);

        if (isset($table[$model])) {
            return (array) $table[$model];
        }

        foreach ($table as $prefix => $prices) {
            if (str_starts_with($model, (string) $prefix)) {
                return (array) $prices;
            }
        }

        return [];
    }

    private static function rate(): float
    {
        return (float) config('museumguide.pricing_eur_per_usd', 1.0);
    }
}
