<?php

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Segmente (MP3-Bytes) zu einer Datei zusammensetzen, mit kurzer Pause dazwischen, und ein leises Musikbett
 * darunterlegen (docs/konzept.md Abschnitt 16): zwei Sekunden Musik vorweg, dann abgesenkt unter der Stimme,
 * am Ende drei Sekunden ausgeblendet. Zwei Durchgaenge: erst die Stimme, dann die Musik (Laenge der Stimme bekannt). Jedes Segment wird vorher auf dieselbe Lautheit gebracht (EBU R128,
 * `museumguide.tts.loudness_lufs`, Sebastian 16.10.2026: "von der Lautstaerke gleich"). Braucht ffmpeg (am Server /usr/bin/ffmpeg); ohne ffmpeg oder bei einem
 * Fehler werden die Segmente nur aneinandergehaengt (MP3-Frames lassen sich direkt verketten), ohne Musik.
 */
class AudioMixer
{
    /**
     * @param  list<string>  $segments  MP3-Bytes je Segment
     * @return string MP3-Bytes
     */
    public function mix(array $segments, ?string $musicPath = null): string
    {
        if ($segments === []) {
            return '';
        }

        $ffmpeg = (string) config('museumguide.music.ffmpeg', 'ffmpeg');

        $lufs = config('museumguide.tts.loudness_lufs');

        if ((count($segments) === 1 && $musicPath === null && $lufs === null) || ! $this->hasFfmpeg($ffmpeg)) {
            return implode('', $segments);
        }

        $dir = storage_path('app/private/tmp/mix-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        try {
            // Durchgang 1: Stimme angleichen und zusammensetzen, als rohes PCM (mono, 44,1 kHz, 16 Bit), damit die
            // Laenge exakt aus der Dateigroesse folgt
            $args = [$ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y'];
            $filters = [];
            $concat = '';

            foreach ($segments as $i => $bytes) {
                file_put_contents($dir.'/'.$i.'.mp3', $bytes);
                $args[] = '-i';
                $args[] = $dir.'/'.$i.'.mp3';
                $norm = $lufs !== null ? 'loudnorm=I='.(float) $lufs.':TP=-1.5:LRA=11,aresample=44100,' : '';
                $pad = count($segments) > 1 ? 'apad=pad_dur='.(float) config('museumguide.music.gap_seconds', 0.5) : 'anull';
                $filters[] = '['.$i.':a]'.$norm.$pad.'[s'.$i.']';
                $concat .= '[s'.$i.']';
            }

            $filters[] = $concat.'concat=n='.count($segments).':v=0:a=1,aformat=sample_fmts=s16:channel_layouts=mono:sample_rates=44100[voice]';
            $this->run(array_merge($args, ['-filter_complex', implode(';', $filters), '-map', '[voice]', '-f', 's16le', $dir.'/voice.raw']));
            $seconds = (int) filesize($dir.'/voice.raw') / (44100 * 2);

            if ($seconds <= 0) {
                throw new RuntimeException('ffmpeg produced no voice');
            }

            // Durchgang 2: Musikbett darunter. Ausblenden mit fester Startzeit statt areverse: areverse nach
            // loudnorm ergab ab 16.10.2026 eine komplett stumme Datei (vertiefende Guides ohne Ton)
            $args = [$ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-f', 's16le', '-ar', '44100', '-ac', '1', '-i', $dir.'/voice.raw'];

            if ($musicPath !== null && is_file($musicPath)) {
                $intro = (float) config('museumguide.music.intro_seconds', 2);
                $outro = (float) config('museumguide.music.outro_seconds', 3);
                $args = array_merge($args, ['-stream_loop', '-1', '-i', $musicPath, '-filter_complex', implode(';', [
                    '[0:a]adelay='.(int) ($intro * 1000).':all=1,apad=pad_dur='.$outro.'[v]',
                    '[1:a]volume='.(float) config('museumguide.music.volume', 0.12).'[m]',
                    '[v][m]amix=inputs=2:duration=first:dropout_transition=0:normalize=0,afade=t=out:st='.round($intro + $seconds, 3).':d='.$outro.'[mix]',
                ]), '-map', '[mix]']);
            }

            $this->run(array_merge($args, ['-codec:a', 'libmp3lame', '-b:a', '128k', $dir.'/out.mp3']));

            if (! is_file($dir.'/out.mp3') || filesize($dir.'/out.mp3') === 0) {
                throw new RuntimeException('ffmpeg produced no output');
            }

            return (string) file_get_contents($dir.'/out.mp3');
        } catch (Throwable $e) {
            Log::warning('AudioMixer: ffmpeg failed, falling back to plain concat: '.mb_substr($e->getMessage(), 0, 500));

            return implode('', $segments);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * @param  list<string>  $args
     */
    private function run(array $args): void
    {
        $process = new Process($args);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput() ?: 'ffmpeg exit '.$process->getExitCode());
        }
    }

    public function hasFfmpeg(?string $ffmpeg = null): bool
    {
        $ffmpeg ??= (string) config('museumguide.music.ffmpeg', 'ffmpeg');

        if (str_contains($ffmpeg, '/')) {
            return is_executable($ffmpeg);
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if (is_executable($dir.'/'.$ffmpeg)) {
                return true;
            }
        }

        return false;
    }
}
