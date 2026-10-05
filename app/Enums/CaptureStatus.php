<?php

namespace App\Enums;

/**
 * Stand einer Aufnahme in der Pipeline (docs/grundgeruest.md, Ablauf).
 */
enum CaptureStatus: string
{
    case Uploaded = 'uploaded';
    case Recognized = 'recognized';
    case Researched = 'researched';
    case Scripted = 'scripted';
    case Done = 'done';
    case Failed = 'failed';

    public function isRunning(): bool
    {
        return ! in_array($this, [self::Done, self::Failed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Hochgeladen',
            self::Recognized => 'Erkannt',
            self::Researched => 'Recherchiert',
            self::Scripted => 'Skript fertig',
            self::Done => 'Fertig',
            self::Failed => 'Fehler',
        };
    }
}
