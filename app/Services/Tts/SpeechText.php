<?php

namespace App\Services\Tts;

/**
 * Text fuer die Sprachsynthese vorbereiten: Jahreszahlen, Daten, Jahrhunderte und Jahrzehnte werden deutsch
 * ausgeschrieben, weil die ElevenLabs-Stimmen Ziffern oft englisch oder als "eintausend..." lesen
 * (Sebastian, 05.10.2026: "bei Jahreszahlen haben die beiden Sprecher ziemliche Probleme").
 */
class SpeechText
{
    private const ONES = ['null', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn', 'achtzehn', 'neunzehn'];

    private const TENS = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

    private const ORDINALS = [1 => 'erste', 3 => 'dritte', 7 => 'siebte', 8 => 'achte'];

    public static function normalize(string $text): string
    {
        // Abkuerzungen, die Stimmen buchstabieren
        $text = preg_replace(['/\bJh\.\s*/u', '/\bca\.\s*/u', '/\bbzw\.\s*/u', '/\bz\.\s?B\.\s*/u', '/\bu\.\s?a\.\s*/u'], ['Jahrhundert ', 'zirka ', 'beziehungsweise ', 'zum Beispiel ', 'unter anderem '], $text) ?? $text;

        // Jahrhunderte: "im 19. Jahrhundert", "das 19. Jahrhundert"
        $text = preg_replace_callback('/\b(das\s+)?(\d{1,2})\.\s+(Jahrhundert)/u', fn (array $m): string => $m[1].self::ordinal((int) $m[2], $m[1] !== '' ? 'te' : 'ten').' '.$m[3], $text) ?? $text;

        // Datum mit Monat: "am 12. Jänner 1889", "der 1. Mai"
        $months = 'Jänner|Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember';
        $text = preg_replace_callback('/\b(der\s+)?(\d{1,2})\.\s+('.$months.')\b/u', fn (array $m): string => $m[1].self::ordinal((int) $m[2], $m[1] !== '' ? 'te' : 'ten').' '.$m[3], $text) ?? $text;

        // Jahrzehnte: "1880er Jahre", "20er-Jahre"
        $text = preg_replace_callback('/\b(1\d{3}|20\d{2}|\d{2})er(\b|-)/u', fn (array $m): string => self::year((int) $m[1]).'er'.$m[2], $text) ?? $text;

        // Jahreszahlen 1000 bis 2099, auch in Spannen "1880/81", "1880-1890", nicht mit Einheit oder Prozent dahinter
        $text = preg_replace_callback('/\b(1\d{3}|20\d{2})(?:\s?[\/\-–]\s?(\d{2}|1\d{3}|20\d{2}))?\b(?!\s?(?:%|cm|mm|m\b|kg|g\b|€|Euro|Gulden|Kronen|Mark|Franken|Stück|Seiten|Exemplare))/u', function (array $m): string {
            $first = (int) $m[1];
            $words = self::year($first);

            if (isset($m[2]) && $m[2] !== '') {
                $second = strlen($m[2]) === 2 ? (int) (intdiv($first, 100).$m[2]) : (int) $m[2];
                $words .= ' bis '.self::year($second);
            }

            return $words;
        }, $text) ?? $text;

        return $text;
    }

    /** Jahreszahl deutsch: 1889 = achtzehnhundertneunundachtzig, 2003 = zweitausenddrei, 1066 = tausendsechsundsechzig. */
    public static function year(int $year): string
    {
        if ($year >= 1100 && $year <= 1999) {
            $rest = $year % 100;

            return self::cardinal(intdiv($year, 100)).'hundert'.($rest > 0 ? self::cardinal($rest) : '');
        }

        return self::cardinal($year);
    }

    /** Grundzahl 0 bis 9999 als ein Wort ("ein" bleibt ohne "s", wie in "einundzwanzig" und "tausendeins" ueblich). */
    public static function cardinal(int $n): string
    {
        if ($n < 20) {
            return $n === 1 ? 'eins' : self::ONES[$n];
        }

        if ($n < 100) {
            $one = $n % 10;

            return ($one > 0 ? self::ONES[$one].'und' : '').self::TENS[intdiv($n, 10)];
        }

        if ($n < 1000) {
            $rest = $n % 100;
            $hundreds = intdiv($n, 100);

            return ($hundreds === 1 ? 'ein' : self::ONES[$hundreds]).'hundert'.($rest > 0 ? self::cardinal($rest) : '');
        }

        $rest = $n % 1000;
        $thousands = intdiv($n, 1000);

        return ($thousands === 1 ? '' : self::ONES[$thousands]).'tausend'.($rest > 0 ? self::cardinal($rest) : '');
    }

    /** Ordnungszahl mit Endung: ordinal(19, 'ten') = neunzehnten, ordinal(1, 'te') = erste. */
    public static function ordinal(int $n, string $ending = 'ten'): string
    {
        if ($n < 20 && isset(self::ORDINALS[$n])) {
            $stem = self::ORDINALS[$n];
        } elseif ($n < 20) {
            $stem = self::ONES[$n].'te';
        } else {
            $stem = self::cardinal($n).'ste';
        }

        return $stem.substr($ending, 2);
    }
}
