<x-filament-panels::page>
    <x-filament::section heading="Stand" description="Woher die Schlüssel kommen. Werte aus dem Admin haben Vorrang vor der .env.">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 dark:text-gray-400">
                <tr><th class="py-1 pr-4">Schlüssel</th><th class="py-1 pr-4">Herkunft</th><th class="py-1 pr-4">Endung</th><th class="py-1"></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($this->rows() as $row)
                    <tr>
                        <td class="py-2 pr-4">{{ $row['label'] }}</td>
                        <td class="py-2 pr-4">
                            @if ($row['source'] === 'admin')
                                <x-filament::badge color="success">Admin</x-filament::badge>
                            @elseif ($row['source'] === 'env')
                                <x-filament::badge color="gray">.env</x-filament::badge>
                            @else
                                <x-filament::badge color="warning">fehlt</x-filament::badge>
                            @endif
                        </td>
                        <td class="py-2 pr-4"><code class="text-xs">{{ $row['hint'] ?? '-' }}</code></td>
                        <td class="py-2 text-right">
                            @if ($row['source'] === 'admin')
                                {{ ($this->forgetAction)(['name' => $row['name']]) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <form wire:submit="save" class="flex flex-col gap-4">
        {{ $this->form }}
        <div><x-filament::button type="submit">Speichern</x-filament::button></div>
    </form>
</x-filament-panels::page>
