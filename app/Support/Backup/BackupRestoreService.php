<?php

namespace App\Support\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Anbindung an das Server-Skript /usr/local/bin/app-restore (per sudo ohne Passwort): Snapshots auflisten,
 * Stand einer laufenden Wiederherstellung lesen, Wiederherstellung anstossen. Alle Aufrufe gehen als
 * Argument-Array an Process (nie als Shell-Zeile), der App-Name kommt immer aus config/backup-restore.php.
 * Jede Anfrage einer Wiederherstellung steht im Log-Kanal "backup-restore" (wer, wann, Snapshot, Quelle).
 */
class BackupRestoreService
{
    public const CACHE_KEY = 'backup-restore.snapshots';

    public const ID_PATTERN = '/^[0-9a-f]{8,64}$/';

    public const REQUESTED_BY_PATTERN = '/^[A-Za-z0-9@._+-]{1,100}$/';

    public const SOURCES = ['local', 'remote'];

    public const STATES = ['none', 'queued', 'running', 'done', 'failed'];

    public function enabled(): bool
    {
        return $this->app() !== '';
    }

    public function app(): string
    {
        return trim((string) config('backup-restore.app'));
    }

    /**
     * Snapshots, neueste zuerst; lokal und HiDrive derselben Minute zusammengefuehrt (lokal gewinnt).
     * Fehler einer Quelle stehen in remote_error bzw. local_error, die andere Quelle wird trotzdem gezeigt.
     * Ergebnis fuer list_cache_seconds im Cache.
     *
     * @return array{snapshots: list<array{id: string, time: string, source: string, pre_restore: bool}>, remote_error: string|null, local_error: string|null}
     */
    public function snapshots(): array
    {
        $seconds = max(1, (int) config('backup-restore.list_cache_seconds', 60));

        return Cache::remember(self::CACHE_KEY, $seconds, function (): array {
            $response = $this->call(['list', $this->app()]);

            return [
                'snapshots' => $this->mergeSnapshots(is_array($response['snapshots'] ?? null) ? $response['snapshots'] : []),
                'remote_error' => $this->errorText($response['remote_error'] ?? null),
                'local_error' => $this->errorText($response['local_error'] ?? null),
            ];
        });
    }

    public function forgetSnapshots(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Stand der (letzten) Wiederherstellung: state none|queued|running|done|failed mit Meldung, Snapshot,
     * Zeitpunkt, Quelle, Anfragender, Sicherheits-Snapshot, Zeiten und bis zu 40 Logzeilen.
     *
     * @return array{state: string, message: string, snapshot: string|null, snapshot_time: string|null, source: string|null, requested_by: string|null, safety_snapshot: string|null, started_at: string|null, updated_at: string|null, finished_at: string|null, log: list<string>}
     */
    public function status(): array
    {
        $response = $this->call(['status', $this->app()]);
        $state = (string) ($response['state'] ?? 'none');
        $log = is_array($response['log'] ?? null) ? array_values(array_map(fn ($line): string => (string) $line, $response['log'])) : [];

        return [
            'state' => in_array($state, self::STATES, true) ? $state : 'none',
            'message' => (string) ($response['message'] ?? ''),
            'snapshot' => $this->text($response['snapshot'] ?? null),
            'snapshot_time' => $this->text($response['snapshot_time'] ?? null),
            'source' => $this->text($response['source'] ?? null),
            'requested_by' => $this->text($response['requested_by'] ?? null),
            'safety_snapshot' => $this->text($response['safety_snapshot'] ?? null),
            'started_at' => $this->text($response['started_at'] ?? null),
            'updated_at' => $this->text($response['updated_at'] ?? null),
            'finished_at' => $this->text($response['finished_at'] ?? null),
            'log' => array_slice($log, -40),
        ];
    }

    /**
     * Wiederherstellung anstossen. Prueft die Eingaben vor dem Aufruf (InvalidArgumentException) und schreibt
     * Anfrage und Ergebnis in den Log-Kanal. Server-Fehler (ok=false, z. B. "Es laeuft bereits eine
     * Wiederherstellung") kommen als RuntimeException mit der Server-Meldung.
     *
     * @return array<string, mixed>
     */
    public function restore(string $id, string $source, string $requestedBy): array
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException('Ungültige Snapshot-Kennung.');
        }

        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException('Ungültige Quelle.');
        }

        if (preg_match(self::REQUESTED_BY_PATTERN, $requestedBy) !== 1) {
            throw new InvalidArgumentException('Ungültiger Anfragender.');
        }

        $context = ['app' => $this->app(), 'requested_by' => $requestedBy, 'snapshot' => $id, 'source' => $source, 'time' => now()->toIso8601String()];
        Log::channel('backup-restore')->info('restore requested', $context);

        try {
            $response = $this->call(['restore', $this->app(), $id, $source, $requestedBy]);
        } catch (Throwable $e) {
            Log::channel('backup-restore')->error('restore request failed', $context + ['error' => $e->getMessage()]);

            throw $e;
        }

        Log::channel('backup-restore')->info('restore started', $context + ['state' => (string) ($response['state'] ?? '')]);

        return $response;
    }

    /**
     * Ruft sudo -n <command> <args...> auf und liefert das JSON als Array. Exit-Code ungleich 0, kaputtes JSON
     * oder ok=false ergeben eine RuntimeException mit der Server-Meldung.
     *
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function call(array $arguments): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Datensicherungen sind nicht eingerichtet (BACKUP_APP fehlt).');
        }

        $command = array_merge(['sudo', '-n', (string) config('backup-restore.command')], $arguments);

        try {
            $result = Process::timeout(120)->run($command);
        } catch (Throwable $e) {
            throw new RuntimeException('Sicherungs-Skript nicht erreichbar: '.Str::limit($e->getMessage(), 300));
        }

        $output = trim($result->output());
        $decoded = $output !== '' ? json_decode($output, true) : null;
        $decoded = is_array($decoded) ? $decoded : null;

        if ($result->exitCode() !== 0 || $decoded === null || ($decoded['ok'] ?? false) !== true) {
            $message = $this->errorText($decoded['error'] ?? null)
                ?? ($decoded === null && $output !== '' ? 'Unlesbare Antwort des Sicherungs-Skripts: '.Str::limit($output, 200) : null)
                ?? Str::limit(trim($result->errorOutput()) !== '' ? trim($result->errorOutput()) : $output, 300)
                ?: 'Sicherungs-Skript ohne Antwort (Exit-Code '.$result->exitCode().').';

            throw new RuntimeException($message);
        }

        return $decoded;
    }

    /**
     * Gleiche Minute lokal und remote zusammenfuehren (lokal bevorzugt), nur gueltige Kennungen, neueste zuerst.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{id: string, time: string, source: string, pre_restore: bool}>
     */
    protected function mergeSnapshots(array $rows): array
    {
        $byMinute = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? '');
            $source = (string) ($row['source'] ?? 'local');

            if (preg_match(self::ID_PATTERN, $id) !== 1 || ! in_array($source, self::SOURCES, true)) {
                continue;
            }

            try {
                $time = Carbon::parse((string) ($row['time'] ?? ''));
            } catch (Throwable) {
                continue;
            }

            $minute = $time->copy()->utc()->format('Y-m-d H:i');
            $snapshot = ['id' => $id, 'time' => $time->toIso8601String(), 'source' => $source, 'pre_restore' => (bool) ($row['pre_restore'] ?? false)];

            // Nur lokal und HiDrive derselben Minute zusammenfuehren; zwei Server-Snapshots in einer Minute
            // (Sicherheits-Snapshot direkt nach einem stuendlichen) bleiben beide erhalten.
            $key = $minute;

            if (isset($byMinute[$key]) && $byMinute[$key]['source'] === $source) {
                $key = $minute.'#'.$id;
            }

            if (! isset($byMinute[$key]) || ($source === 'local' && $byMinute[$key]['source'] !== 'local')) {
                $byMinute[$key] = $snapshot;
            }
        }

        return collect($byMinute)->sortByDesc(fn (array $snapshot): string => $snapshot['time'].$snapshot['id'])->values()->all();
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function errorText(mixed $value): ?string
    {
        return $this->text(is_string($value) ? Str::limit($value, 300) : null);
    }
}
