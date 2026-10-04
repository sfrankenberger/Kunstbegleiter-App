@php
    $fmt = fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format('d.m.Y H:i') : '-';
@endphp
<div
    class="mt-2 flex flex-col gap-3"
    x-data="{
        supported: false, busy: false, error: '', name: '',
        async lib() { return window.KunstPasskeys || await import('/js/passkeys.js?v={{ @filemtime(public_path('js/passkeys.js')) ?: 1 }}'); },
        async init() { try { this.supported = (await this.lib()).passkeySupported(); } catch (e) { this.supported = false; } },
        async add() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                await (await this.lib()).registerPasskey(this.name.trim() || 'Passkey');
                this.name = '';
                $wire.$refresh();
            } catch (e) {
                this.error = window.KunstPasskeys ? window.KunstPasskeys.passkeyErrorMessage(e) : (e.message || 'Das hat nicht geklappt.');
            } finally {
                this.busy = false;
            }
        },
    }"
>
    <p class="text-sm text-stone-600">Mit einem Passkey meldest du dich ohne Passwort an: Face ID, Touch ID oder Geräte-Code. Je Gerät ein Passkey.</p>

    @if ($passkeys === [])
        <p class="text-sm text-stone-500">Noch kein Passkey angelegt.</p>
    @else
        <ul class="divide-y divide-stone-100">
            @foreach ($passkeys as $passkey)
                <li class="flex items-center justify-between gap-2 py-2" wire:key="passkey-{{ $passkey['id'] }}">
                    <div>
                        <p class="text-sm font-medium">{{ $passkey['name'] }}</p>
                        <p class="text-xs text-stone-500">angelegt {{ $fmt($passkey['created_at']) }}, zuletzt {{ $fmt($passkey['last_used_at']) }}</p>
                    </div>
                    <button type="button" class="text-sm font-medium text-red-700" wire:click="delete({{ $passkey['id'] }})" wire:confirm="Passkey &quot;{{ $passkey['name'] }}&quot; wirklich löschen?">Löschen</button>
                </li>
            @endforeach
        </ul>
    @endif

    <div x-show="supported" x-cloak class="flex flex-col gap-2">
        <input type="text" x-model="name" class="kb-input" placeholder="Name des Geräts, z. B. iPhone" maxlength="100" x-on:keydown.enter.prevent="add">
        <button type="button" class="kb-button-secondary" x-on:click="add" x-bind:disabled="busy">
            <span x-show="!busy">Passkey hinzufügen</span>
            <span x-show="busy" x-cloak>Warte auf das Gerät ...</span>
        </button>
    </div>
    <p x-show="!supported" x-cloak class="text-xs text-stone-500">Dieser Browser kann keine Passkeys anlegen (nur über HTTPS und in aktuellen Browsern).</p>
    <p x-show="error" x-text="error" x-cloak class="text-sm text-red-700"></p>
</div>
