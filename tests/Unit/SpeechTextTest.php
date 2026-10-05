<?php

use App\Services\Tts\SpeechText;

test('years, dates, centuries and decades are spelled out in german', function () {
    expect(SpeechText::normalize('Gemalt 1889 in Arles.'))->toBe('Gemalt achtzehnhundertneunundachtzig in Arles.')
        ->and(SpeechText::normalize('Im 19. Jahrhundert, das 18. Jahrhundert'))->toBe('Im neunzehnten Jahrhundert, das achtzehnte Jahrhundert')
        ->and(SpeechText::normalize('am 12. Jänner 2003 und der 1. Mai 1066'))->toBe('am zwölften Jänner zweitausenddrei und der erste Mai tausendsechsundsechzig')
        ->and(SpeechText::normalize('die 1880er Jahre, 1880/81, 1880-1890'))->toBe('die achtzehnhundertachtziger Jahre, achtzehnhundertachtzig bis achtzehnhunderteinundachtzig, achtzehnhundertachtzig bis achtzehnhundertneunzig')
        ->and(SpeechText::normalize('1200 Gulden, 1500 cm, um 1500'))->toBe('1200 Gulden, 1500 cm, um fünfzehnhundert')
        ->and(SpeechText::normalize('ca. 20 Jh. später'))->toBe('zirka 20 Jahrhundert später')
        ->and(SpeechText::ordinal(20))->toBe('zwanzigsten')
        ->and(SpeechText::ordinal(21, 'te'))->toBe('einundzwanzigste')
        ->and(SpeechText::ordinal(7))->toBe('siebten')
        ->and(SpeechText::cardinal(1901))->toBe('tausendneunhunderteins')
        ->and(SpeechText::year(1901))->toBe('neunzehnhunderteins')
        ->and(SpeechText::year(2010))->toBe('zweitausendzehn');
});
