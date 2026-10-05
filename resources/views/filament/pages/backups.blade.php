<x-filament-panels::page>
    @php
        $status = $this->status();
        $list = $this->snapshots();
        $state = $status['state'] ?? 'none';
        $stateLabel = \App\Filament\Pages\Backups::STATE_LABELS[$state] ?? $state;
        $running = in_array($state, ['queued', 'running'], true);
    @endphp

    <div id="backup-offline" class="hidden rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
        Die App ist kurz offline, die Seite lädt automatisch neu.
    </div>

    <div wire:poll.3s>
        <x-filament::section heading="Stand der Wiederherstellung" description="Wird alle 3 Sekunden aktualisiert.">
            <div @class([
                'flex items-center gap-2 text-sm font-semibold',
                'text-success-600 dark:text-success-400' => $state === 'done',
                'text-danger-600 dark:text-danger-400' => in_array($state, ['failed', 'error'], true),
                'text-primary-600 dark:text-primary-400' => $running,
                'text-gray-600 dark:text-gray-400' => $state === 'none',
            ])>
                @if ($running)
                    <x-filament::loading-indicator class="h-5 w-5" />
                @endif
                <span>{{ $stateLabel }}</span>
            </div>

            @if (($status['message'] ?? '') !== '')
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $status['message'] }}</p>
            @endif

            @if ($state !== 'none' && $state !== 'error')
                <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-gray-500">Stand vom:</dt> <dd class="inline">{{ \App\Filament\Pages\Backups::formatTime($status['snapshot_time'] ?? null, 'DD.MM.YYYY HH:mm') }}</dd></div>
                    <div><dt class="inline text-gray-500">Quelle:</dt> <dd class="inline">{{ \App\Filament\Pages\Backups::sourceLabel($status['source'] ?? null) }}</dd></div>
                    <div><dt class="inline text-gray-500">Snapshot:</dt> <dd class="inline"><code class="text-xs">{{ $status['snapshot'] ?? '-' }}</code></dd></div>
                    <div><dt class="inline text-gray-500">Angefordert von:</dt> <dd class="inline">{{ $status['requested_by'] ?? '-' }}</dd></div>
                    <div><dt class="inline text-gray-500">Stand vor Wiederherstellung:</dt> <dd class="inline"><code class="text-xs">{{ $status['safety_snapshot'] ?? '-' }}</code></dd></div>
                    <div><dt class="inline text-gray-500">Begonnen:</dt> <dd class="inline">{{ \App\Filament\Pages\Backups::formatTime($status['started_at'] ?? null, 'DD.MM.YYYY HH:mm:ss') }}</dd></div>
                    <div><dt class="inline text-gray-500">Zuletzt gemeldet:</dt> <dd class="inline">{{ \App\Filament\Pages\Backups::formatTime($status['updated_at'] ?? null, 'DD.MM.YYYY HH:mm:ss') }}</dd></div>
                    <div><dt class="inline text-gray-500">Beendet:</dt> <dd class="inline">{{ \App\Filament\Pages\Backups::formatTime($status['finished_at'] ?? null, 'DD.MM.YYYY HH:mm:ss') }}</dd></div>
                </dl>
            @endif

            @if (($status['log'] ?? []) !== [])
                <pre class="mt-3 max-h-64 overflow-auto rounded bg-gray-950 p-3 text-xs text-gray-100">{{ implode("\n", $status['log']) }}</pre>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Sicherungen" description="Quelle Server: Snapshots am Server. Quelle Strato HiDrive: Kopien auf HiDrive. Liste höchstens {{ (int) config('backup-restore.list_cache_seconds') }} Sekunden alt, App {{ $this->appName() }}.">
        @foreach (array_filter([$list['error'] ?? null, $list['local_error'] ?? null, $list['remote_error'] ?? null]) as $warning)
            <div class="mb-3 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">{{ $warning }}</div>
        @endforeach

        @if (($list['snapshots'] ?? []) === [])
            <p class="text-sm text-gray-500">Keine Sicherungen gefunden.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 dark:text-gray-400">
                        <tr><th class="py-1 pr-4">Zeitpunkt</th><th class="py-1 pr-4">Quelle</th><th class="py-1 pr-4">Kennzeichen</th><th class="py-1"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($list['snapshots'] as $snapshot)
                            <tr>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ \App\Filament\Pages\Backups::formatTime($snapshot['time']) }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ \App\Filament\Pages\Backups::sourceLabel($snapshot['source']) }}</td>
                                <td class="py-2 pr-4">
                                    @if ($snapshot['pre_restore'])
                                        <x-filament::badge color="warning">Stand vor Wiederherstellung</x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <x-filament::button color="danger" size="sm" :disabled="$running" wire:click="mountAction('restore', {{ \Illuminate\Support\Js::from(['id' => $snapshot['id'], 'source' => $snapshot['source'], 'time' => $snapshot['time']]) }})">
                                        Auf diesen Stand zurücksetzen
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="mt-3 text-sm text-gray-500">Vor jeder Wiederherstellung legt der Server einen eigenen Snapshot "Stand vor Wiederherstellung" an. Damit lässt sich eine Wiederherstellung wieder zurücknehmen. Hochgeladene Dateien bleiben unverändert, nur die Datenbank wird zurückgesetzt.</p>
    </x-filament::section>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 503) {
                        preventDefault();
                        document.getElementById('backup-offline')?.classList.remove('hidden');
                        setTimeout(() => window.location.reload(), 5000);
                    }
                });
            });
        });
    </script>
</x-filament-panels::page>
