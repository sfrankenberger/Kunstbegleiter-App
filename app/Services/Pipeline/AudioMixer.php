<?php

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Segmente (MP3-Bytes) zu einer Datei zusammensetzen, mit kurzer Pause dazwischen, und ein leises Musikbett
 * darunterlegen (docs/konzept.md Abschnitt 16): zwei Sekunden Musik vorweg, dann abgesenkt unter der Stimme,
 * am Ende drei Sekunden ausgeblendet. Braucht ffmpeg (am Server /usr/bin/ffmpeg); ohne ffmpeg oder bei einem
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

        if ((count($segments) === 1 && $musicPath === null) || ! $this->hasFfmpeg($ffmpeg)) {
            return implode('', $segments);
        }

        $dir = storage_path('app/private/tmp/mix-'.Str::random(8));
        File::ensureDirectoryExists($dir);

        try {
            $args = [$ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y'];
            $filters = [];
            $concat = '';

            foreach ($segments as $i => $bytes) {
                file_put_contents($dir.'/'.$i.'.mp3', $bytes);
                $args[] = '-i';
                $args[] = $dir.'/'.$i.'.mp3';
                $filters[] = '['.$i.':a]apad=pad_dur='.(float) config('museumguide.music.gap_seconds', 0.5).'[s'.$i.']';
                $concat .= '[s'.$i.']';
            }

            $filters[] = $concat.'concat=n='.count($segments).':v=0:a=1[voice]';
            $out = '[voice]';

            if ($musicPath !== null && is_file($musicPath)) {
                $m = count($segments);
                $args[] = '-stream_loop';
                $args[] = '-1';
                $args[] = '-i';
                $args[] = $musicPath;
                $intro = (int) ((float) config('museumguide.music.intro_seconds', 2) * 1000);
                $filters[] = '[voice]adelay='.$intro.'|'.$intro.',apad=pad_dur='.(float) config('museumguide.music.outro_seconds', 3).'[v]';
                $filters[] = '['.$m.':a]volume='.(float) config('museumguide.music.volume', 0.12).'[m]';
                $filters[] = '[v][m]amix=inputs=2:duration=first:dropout_transition=0:normalize=0,areverse,afade=t=in:d='.(float) config('museumguide.music.outro_seconds', 3).',areverse[mix]';
                $out = '[mix]';
            }

            $args[] = '-filter_complex';
            $args[] = implode(';', $filters);
            $args[] = '-map';
            $args[] = $out;
            $args[] = '-codec:a';
            $args[] = 'libmp3lame';
            $args[] = '-b:a';
            $args[] = '128k';
            $args[] = $dir.'/out.mp3';

            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($dir.'/out.mp3')) {
                Log::warning('AudioMixer: ffmpeg failed, falling back to plain concat: '.mb_substr($process->getErrorOutput(), 0, 500));

                return implode('', $segments);
            }

            return (string) file_get_contents($dir.'/out.mp3');
        } catch (Throwable $e) {
            Log::warning('AudioMixer: '.$e->getMessage());

            return implode('', $segments);
        } finally {
            File::deleteDirectory($dir);
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
